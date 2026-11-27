<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionIntakeRequest;
use App\Http\Requests\UpdateAdmissionIntakeRequest;
use App\Models\AcademicSession;
use App\Models\AdmissionIntake;
use App\Models\Application;
use App\Models\Programme;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountActivationNotification;
use App\Services\AccountActivationService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdmissionController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit,
        private readonly AccountActivationService $activation
    ) {}

    // ========== INCOMES ==========

    /**
     * List intakes for the caller's institution with counts per intake.
     */
    public function intakes(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $intakes = AdmissionIntake::where('institution_id', $institutionId)
            ->with(['programme:id,code,name', 'academicSession:id,code,name'])
            ->withCount(['applications', 'applications as enrolled_count' => fn($q) => $q->where('status', 'enrolled')])
            ->orderByDesc('open_date')
            ->get()
            ->map(fn($intake) => [
                'id' => $intake->id,
                'name' => $intake->name,
                'programme' => $intake->programme ? [
                    'id' => $intake->programme->id,
                    'code' => $intake->programme->code,
                    'name' => $intake->programme->name,
                ] : null,
                'session' => $intake->academicSession ? [
                    'id' => $intake->academicSession->id,
                    'code' => $intake->academicSession->code,
                    'name' => $intake->academicSession->name,
                ] : null,
                'open_date' => optional($intake->open_date)->toDateString(),
                'close_date' => optional($intake->close_date)->toDateString(),
                'seat_capacity' => $intake->seat_capacity,
                'status' => $intake->status,
                'application_fee' => $intake->application_fee,
                'applications_count' => $intake->applications_count,
                'admitted_count' => Application::where('institution_id', $institutionId)
                    ->where('intake_id', $intake->id)
                    ->where('status', 'admitted')
                    ->count(),
                'enrolled_count' => (int) $intake->enrolled_count,
                'seats_remaining' => max(0, $intake->seat_capacity - (int) $intake->enrolled_count),
            ]);

        return response()->json([
            'intakes' => $intakes,
        ]);
    }

    public function storeIntake(StoreAdmissionIntakeRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $programme = Programme::where('institution_id', $institutionId)
            ->findOrFail($validated['programme_id']);
        $session = AcademicSession::where('institution_id', $institutionId)
            ->findOrFail($validated['academic_session_id']);

        $intakeId = DB::transaction(function () use ($validated, $institutionId) {
            $intake = AdmissionIntake::create([
                'institution_id' => $institutionId,
                'name' => $validated['name'],
                'programme_id' => $validated['programme_id'],
                'academic_session_id' => $validated['academic_session_id'],
                'open_date' => $validated['open_date'],
                'close_date' => $validated['close_date'],
                'seat_capacity' => $validated['seat_capacity'],
                'status' => $validated['status'] ?? 'open',
                'application_fee' => $validated['application_fee'] ?? null,
            ]);

            return $intake->id;
        });

        // Re-fetch the exact record created inside the transaction.
        $intake = AdmissionIntake::where('institution_id', $institutionId)
            ->with(['programme:id,code,name', 'academicSession:id,code,name'])
            ->findOrFail($intakeId);

        $this->audit->record(
            $request,
            event: 'admission.intake_created',
            module: 'institution',
            subject: $intake,
            newValues: $intake->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Admission intake created successfully.',
            'intake' => $intake,
        ], 201);
    }

    public function updateIntake(UpdateAdmissionIntakeRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $intake = AdmissionIntake::where('institution_id', $institutionId)
            ->withCount('applications')
            ->findOrFail($id);

        $before = $intake->toArray();

        // Programme/session changes are not allowed once applications exist.
        if (
            $intake->applications_count > 0 &&
            (
                $request->has('programme_id') ||
                $request->has('academic_session_id')
            )
        ) {
            abort(422, 'Programme and session cannot be changed after applications have been received.');
        }

        $intake->update($request->validated());

        $this->audit->record(
            $request,
            event: 'admission.intake_updated',
            module: 'institution',
            subject: $intake,
            oldValues: $before,
            newValues: $intake->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Admission intake updated successfully.',
            'intake' => $intake->fresh(['programme', 'academicSession']),
        ]);
    }

    public function destroyIntake(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $intake = AdmissionIntake::where('institution_id', $institutionId)
            ->withCount('applications')
            ->findOrFail($id);

        abort_if(
            $intake->applications_count > 0,
            422,
            'This intake cannot be deleted because applications have been received.'
        );

        $before = $intake->toArray();
        $intake->delete();

        $this->audit->record(
            $request,
            event: 'admission.intake_deleted',
            module: 'institution',
            subject: $intake,
            oldValues: $before,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Admission intake deleted successfully.',
        ]);
    }

    // ========== APPLICATIONS ==========

    /**
     * List applications for the caller's institution.
     * Filters: ?intake_id=, ?status=, ?q=search, ?page=, ?per_page=
     */
    public function applications(Request $request)
    {
        $validated = $request->validate([
            'intake_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:new,under_review,shortlisted,waitlisted,rejected,admitted,enrolled'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $institutionId = $request->user()->institution_id;

        $query = Application::where('institution_id', $institutionId)
            ->with(['intake:id,name,programme_id', 'intake.programme:id,code,name'])
            ->orderByDesc('created_at');

        if ($intakeId = $validated['intake_id'] ?? null) {
            $query->where('intake_id', (int) $intakeId);
        }

        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($q = trim((string) ($validated['q'] ?? ''))) {
            $query->where(fn($where) => $where
                ->where('application_number', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"));
        }

        return response()->json(
            $query->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function show(Request $request, int $id)
    {
        $application = Application::where('institution_id', $request->user()->institution_id)
            ->with([
                'intake.programme:id,code,name',
                'intake.academicSession:id,code,name',
                'reviewer:id,name,email',
                'enrolledUser:id,name,email,status',
                'applicant:id,full_name,email,mobile_country_code,mobile',
            ])
            ->findOrFail($id);

        $canViewDocuments = $this->userCanViewAdmissionDocuments($request);
        $canScrutinize = $this->userHasAdmissionPermission($request, 'admissions.applications.scrutinize');
        $canVerifyDocuments = $this->userHasAdmissionPermission($request, 'admissions.documents.verify');

        return response()->json([
            'application' => $application,
            'review' => $this->buildApplicationReviewData($application, $canViewDocuments, $canScrutinize, $canVerifyDocuments),
        ]);
    }

    /** Download one applicant document through an authenticated, institution-scoped route. */
    public function downloadApplicationDocument(Request $request, int $id, int $documentId)
    {
        abort_unless(
            $this->userCanViewAdmissionDocuments($request),
            403,
            'You do not have permission to view applicant documents.'
        );

        $application = Application::query()
            ->where('institution_id', $request->user()->institution_id)
            ->findOrFail($id);

        $document = DB::table('admission_application_documents')
            ->where('institution_id', $application->institution_id)
            ->where('application_id', $application->id)
            ->where('id', $documentId)
            ->first();

        abort_if(!$document, 404, 'Application document not found.');

        abort_unless(in_array($document->storage_disk, ['local', 's3'], true), 404, 'Application document storage is unavailable.');

        try {
            $disk = Storage::disk($document->storage_disk);
            abort_unless($disk->exists($document->storage_key), 404, 'Application document file not found.');

            return $disk->download(
                $document->storage_key,
                $document->original_filename,
                [
                    'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                    'Cache-Control' => 'private, no-store, max-age=0',
                ]
            );
        } catch (\InvalidArgumentException $exception) {
            abort(404, 'Application document storage is unavailable.');
        }
    }

    private function userCanViewAdmissionDocuments(Request $request): bool
    {
        $user = $request->user();
        if (!$user || !$user->isInstitutionUser()) {
            return false;
        }

        // Institution administrators receive this permission in the canonical
        // AdmissionWorkflowPermissionSeeder; retain their established admin access.
        if ($user->isInstitutionAdmin()) {
            return true;
        }

        return DB::table('user_role_assignments as ura')
            ->join('roles as r', 'r.id', '=', 'ura.role_id')
            ->join('role_permission as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ura.user_id', $user->id)
            ->where('ura.institution_id', $user->institution_id)
            ->where('ura.status', 'active')
            ->where('r.status', 'active')
            ->where('p.status', 'active')
            ->where('p.code', 'admissions.documents.view')
            ->where(fn ($query) => $query->whereNull('ura.starts_at')->orWhere('ura.starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ura.ends_at')->orWhere('ura.ends_at', '>=', now()))
            ->exists();
    }

    private function buildApplicationReviewData(Application $application, bool $canViewDocuments, bool $canScrutinize, bool $canVerifyDocuments): array
    {
        $sections = [];
        if ($application->form_version_id) {
            $answers = DB::table('admission_application_answers')
                ->where('institution_id', $application->institution_id)
                ->where('application_id', $application->id)
                ->where('form_version_id', $application->form_version_id)
                ->pluck('answer_value', 'field_key');

            $sectionRows = DB::table('admission_form_sections')
                ->where('institution_id', $application->institution_id)
                ->where('form_version_id', $application->form_version_id)
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($sectionRows as $section) {
                $fields = DB::table('admission_form_fields')
                    ->where('institution_id', $application->institution_id)
                    ->where('section_id', $section->id)
                    ->where('is_visible', true)
                    ->orderBy('sort_order')
                    ->get();

                $sections[] = [
                    'id' => $section->id,
                    'key' => $section->section_key,
                    'title' => $section->title,
                    'description' => $section->description,
                    'fields' => $fields->map(function ($field) use ($answers) {
                        $raw = $answers->get($field->field_key);
                        $value = $raw === null ? null : json_decode($raw, true);
                        $options = DB::table('admission_form_field_options')
                            ->where('field_id', $field->id)
                            ->where('institution_id', $field->institution_id)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->get(['option_value as value', 'option_label as label']);

                        return [
                            'key' => $field->field_key,
                            'label' => $field->label,
                            'type' => $field->field_type,
                            'answer' => $value,
                            'options' => $options,
                        ];
                    })->values(),
                ];
            }
        }

        $qualifications = DB::table('admission_application_qualifications')
            ->where('institution_id', $application->institution_id)
            ->where('application_id', $application->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $documents = $canViewDocuments
            ? DB::table('admission_application_documents')
                ->where('institution_id', $application->institution_id)
                ->where('application_id', $application->id)
                ->orderBy('document_type')
                ->orderByDesc('created_at')
                ->get(['id', 'document_type', 'original_filename', 'mime_type', 'file_size', 'verification_status', 'review_notes', 'reviewed_at', 'created_at'])
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'original_filename' => $document->original_filename,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'verification_status' => $document->verification_status,
                    'review_notes' => $document->review_notes,
                    'reviewed_at' => $document->reviewed_at,
                    'uploaded_at' => $document->created_at,
                    'download_url' => "/api/admissions/applications/{$application->id}/documents/{$document->id}",
                ])->values()
            : collect();

        $clarifications = DB::table('admission_clarification_requests')
            ->where('institution_id', $application->institution_id)
            ->where('application_id', $application->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => $this->clarificationPayload($row))
            ->values();

        $events = DB::table('admission_application_events')
            ->where('institution_id', $application->institution_id)
            ->where('application_id', $application->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'event_type', 'from_status', 'to_status', 'remarks', 'actor_type', 'actor_id', 'metadata', 'occurred_at'])
            ->map(function ($event) {
                $event->metadata = $event->metadata ? json_decode($event->metadata, true) : null;
                return $event;
            });

        return [
            'form_sections' => $sections,
            'qualifications' => $qualifications,
            'documents' => $documents,
            'can_view_documents' => $canViewDocuments,
            'clarifications' => $clarifications,
            'can_scrutinize' => $canScrutinize,
            'can_verify_documents' => $canVerifyDocuments,
            'events' => $events,
        ];
    }

    /** Create an applicant clarification request for a submitted application. */
    public function createClarification(Request $request, int $id)
    {
        abort_unless($this->userHasAdmissionPermission($request, 'admissions.applications.scrutinize'), 403);

        $institutionId = $request->user()->institution_id;
        $application = Application::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', ['new', 'under_review', 'shortlisted', 'waitlisted'])
            ->findOrFail($id);

        $validated = $request->validate([
            'request_text' => ['required', 'string', 'min:5', 'max:5000'],
            'requested_fields' => ['sometimes', 'nullable', 'array', 'max:50'],
            'requested_fields.*' => ['string', 'max:120'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $clarification = DB::transaction(function () use ($request, $application, $validated, $institutionId) {
            $clarification = DB::table('admission_clarification_requests')->insertGetId([
                'institution_id' => $institutionId,
                'application_id' => $application->id,
                'request_text' => trim($validated['request_text']),
                'requested_fields' => isset($validated['requested_fields'])
                    ? json_encode(array_values(array_unique($validated['requested_fields'])), JSON_THROW_ON_ERROR)
                    : null,
                'status' => 'open',
                'due_at' => $validated['due_at'] ?? null,
                'requested_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('admission_application_events')->insert([
                'institution_id' => $institutionId,
                'application_id' => $application->id,
                'event_type' => 'clarification_requested',
                'from_status' => null,
                'to_status' => 'open',
                'remarks' => trim($validated['request_text']),
                'actor_type' => 'institution_user',
                'actor_id' => $request->user()->id,
                'metadata' => json_encode(['clarification_id' => $clarification], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $clarification;
        });

        return response()->json([
            'message' => 'Clarification request sent to the applicant.',
            'clarification' => $this->clarificationPayload(
                DB::table('admission_clarification_requests')->where('id', $clarification)->first()
            ),
        ], 201);
    }

    /** Resolve a clarification after the institution has reviewed the applicant response. */
    public function resolveClarification(Request $request, int $id, int $clarificationId)
    {
        abort_unless($this->userHasAdmissionPermission($request, 'admissions.applications.scrutinize'), 403);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($request, $id, $clarificationId, $institutionId, $validated) {
            $application = Application::query()
                ->where('institution_id', $institutionId)
                ->findOrFail($id);

            $clarification = DB::table('admission_clarification_requests')
                ->where('institution_id', $institutionId)
                ->where('application_id', $application->id)
                ->where('id', $clarificationId)
                ->lockForUpdate()
                ->first();

            abort_if(!$clarification, 404, 'Clarification request not found.');
            abort_if($clarification->status === 'resolved', 409, 'This clarification is already resolved.');
            abort_if($clarification->status !== 'responded', 422, 'The applicant must respond before this clarification can be resolved.');

            DB::table('admission_clarification_requests')
                ->where('id', $clarificationId)
                ->update(['status' => 'resolved', 'updated_at' => now()]);

            DB::table('admission_application_events')->insert([
                'institution_id' => $institutionId,
                'application_id' => $application->id,
                'event_type' => 'clarification_resolved',
                'from_status' => 'responded',
                'to_status' => 'resolved',
                'remarks' => $validated['resolution_notes'] ?? null,
                'actor_type' => 'institution_user',
                'actor_id' => $request->user()->id,
                'metadata' => json_encode(['clarification_id' => $clarificationId], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('admission_clarification_requests')->where('id', $clarificationId)->first();
        });

        return response()->json([
            'message' => 'Clarification marked as resolved.',
            'clarification' => $this->clarificationPayload($result),
        ]);
    }

    /** Verify or return an uploaded applicant document. */
    public function verifyApplicationDocument(Request $request, int $id, int $documentId)
    {
        abort_unless($this->userHasAdmissionPermission($request, 'admissions.documents.verify'), 403);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'verification_status' => ['required', 'in:verified,returned'],
            'review_notes' => ['required_if:verification_status,returned', 'nullable', 'string', 'max:2000'],
        ]);

        $document = DB::transaction(function () use ($request, $id, $documentId, $institutionId, $validated) {
            $application = Application::query()
                ->where('institution_id', $institutionId)
                ->findOrFail($id);

            $document = DB::table('admission_application_documents')
                ->where('institution_id', $institutionId)
                ->where('application_id', $application->id)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            abort_if(!$document, 404, 'Application document not found.');
            abort_if($document->verification_status === 'verified' && $validated['verification_status'] === 'verified', 409, 'This document is already verified.');

            DB::table('admission_application_documents')
                ->where('id', $documentId)
                ->update([
                    'verification_status' => $validated['verification_status'],
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_notes' => $validated['review_notes'] ?? null,
                    'updated_at' => now(),
                ]);

            DB::table('admission_application_events')->insert([
                'institution_id' => $institutionId,
                'application_id' => $application->id,
                'event_type' => $validated['verification_status'] === 'verified'
                    ? 'document_verified' : 'document_returned',
                'from_status' => $document->verification_status,
                'to_status' => $validated['verification_status'],
                'remarks' => $validated['review_notes'] ?? null,
                'actor_type' => 'institution_user',
                'actor_id' => $request->user()->id,
                'metadata' => json_encode(['document_id' => $documentId, 'document_type' => $document->document_type], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('admission_application_documents')->where('id', $documentId)->first();
        });

        return response()->json([
            'message' => $validated['verification_status'] === 'verified'
                ? 'Document verified successfully.' : 'Document returned to the applicant.',
            'document' => [
                'id' => $document->id,
                'verification_status' => $document->verification_status,
                'review_notes' => $document->review_notes,
                'reviewed_at' => $document->reviewed_at,
            ],
        ]);
    }

    private function userHasAdmissionPermission(Request $request, string $permissionCode): bool
    {
        $user = $request->user();
        if (!$user || !$user->isInstitutionUser() || !$user->institution_id) {
            return false;
        }

        if ($user->isInstitutionAdmin()) {
            return true;
        }

        return DB::table('user_role_assignments as ura')
            ->join('roles as r', 'r.id', '=', 'ura.role_id')
            ->join('role_permission as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ura.user_id', $user->id)
            ->where('ura.institution_id', $user->institution_id)
            ->where('ura.status', 'active')
            ->where('r.status', 'active')
            ->where('p.status', 'active')
            ->where('p.code', $permissionCode)
            ->where(fn ($query) => $query->whereNull('ura.starts_at')->orWhere('ura.starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ura.ends_at')->orWhere('ura.ends_at', '>=', now()))
            ->exists();
    }

    private function clarificationPayload(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'request_text' => $row->request_text,
            'requested_fields' => $row->requested_fields ? json_decode($row->requested_fields, true) : [],
            'status' => $row->status,
            'due_at' => $row->due_at,
            'applicant_response' => $row->applicant_response,
            'responded_at' => $row->responded_at,
            'requested_by' => $row->requested_by,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Review decision: under_review | shortlisted | waitlisted | rejected | admitted.
     */
    public function decide(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $application = Application::where('institution_id', $institutionId)
            ->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:under_review,shortlisted,waitlisted,rejected,admitted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if($application->status === 'enrolled', 409, 'An enrolled application cannot receive another admission decision.');

        $before = $application->toArray();

        DB::transaction(function () use ($application, $validated, $request, $institutionId, $before) {
            $application->update([
                'status' => $validated['status'],
                'review_notes' => $validated['notes'] ?? $application->review_notes,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            DB::table('admission_application_events')->insert([
                'institution_id' => $institutionId,
                'application_id' => $application->id,
                'event_type' => 'admission_decision_recorded',
                'from_status' => $before['status'],
                'to_status' => $validated['status'],
                'remarks' => $validated['notes'] ?? null,
                'actor_type' => 'institution_user',
                'actor_id' => $request->user()->id,
                'metadata' => json_encode(['reviewed_by' => $request->user()->id], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->audit->record(
            $request,
            event: 'admission.application_reviewed',
            module: 'institution',
            subject: $application,
            oldValues: ['status' => $before['status']],
            newValues: [
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Decision recorded.',
            'application' => $application->fresh(['intake.programme']),
        ]);
    }

    /**
     * Enroll an admitted applicant: create (or adopt) a student account, assign the
     * student role, mark the application enrolled, and return a one-time password
     * for a newly created account.
     */

    /**
     * Enroll an admitted applicant: create (or adopt) a student account,
     * assign the student role, mark the application enrolled, and create
     * or update the student profile. New accounts require activation.
     */
    public function enroll(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $result = DB::transaction(function () use ($id, $institutionId, $request) {
            // Serialize enrollment operations within this institution.
            \App\Models\Institution::whereKey($institutionId)
                ->lockForUpdate()
                ->firstOrFail();

            // Reload and lock the application; do not trust pre-transaction state.
            $application = Application::where('institution_id', $institutionId)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $application->status === 'admitted',
                422,
                'Only admitted applications can be enrolled.'
            );

            abort_if(
                $application->enrolled_user_id !== null,
                422,
                'This application is already enrolled.'
            );

            // Lock the intake before checking its available capacity.
            $intake = AdmissionIntake::where('institution_id', $institutionId)
                ->whereKey($application->intake_id)
                ->lockForUpdate()
                ->firstOrFail();

            $enrolledCount = Application::where('institution_id', $institutionId)
                ->where('intake_id', $intake->id)
                ->where('status', 'enrolled')
                ->count();

            abort_if(
                $enrolledCount >= (int) $intake->seat_capacity,
                422,
                'Intake is full: all ' . $intake->seat_capacity
                    . ' seats for this intake are enrolled. Increase seat capacity before enrolling more applicants.'
            );

            $studentRoleId = Role::where('code', 'student')
                ->where('status', 'active')
                ->value('id');

            abort_if(
                !$studentRoleId,
                500,
                'Student role is not seeded. Run RoleSeeder.'
            );

            $email = strtolower(trim($application->email));

            // Check for an existing account in this institution.
            $existing = User::where('email', $email)
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->first();

            // Do not adopt an account belonging to another institution or platform.
            $foreignOwner = User::where('email', $email)
                ->where(function ($query) use ($institutionId) {
                    $query->where('institution_id', '!=', $institutionId)
                        ->orWhere('account_type', 'platform');
                })
                ->lockForUpdate()
                ->first();

            abort_if(
                $existing === null && $foreignOwner !== null,
                422,
                'This email address already belongs to another account. Resolve the conflict before enrolling.'
            );

            $isNewAccount = $existing === null;
            $activationToken = null;

            if ($existing) {
                $user = $existing;
            } else {
                // Account remains unable to log in until the activation process is completed.
                $user = User::create([
                    'account_type' => 'institution',
                    'institution_id' => $institutionId,
                    'name' => $application->full_name,
                    'email' => $email,
                    'phone' => $application->phone,
                    'password' => \Illuminate\Support\Str::password(40),
                    'status' => 'pending_activation',
                ]);

                $activationToken = $this->activation->issue($user);
            }

            // Assign the student role within the same transaction.
            DB::table('user_role_assignments')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'user_id' => $user->id,
                    'role_id' => $studentRoleId,
                ],
                [
                    'academic_unit_id' => null,
                    'is_primary' => false,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $application->update([
                'status' => 'enrolled',
                'enrolled_user_id' => $user->id,
            ]);

            DB::table('admission_application_events')->insert([
                'institution_id' => $institutionId,
                'application_id' => $application->id,
                'event_type' => 'applicant_enrolled',
                'from_status' => 'admitted',
                'to_status' => 'enrolled',
                'remarks' => null,
                'actor_type' => 'institution_user',
                'actor_id' => $request->user()->id,
                'metadata' => json_encode(['user_id' => $user->id], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \App\Models\StudentProfile::updateOrCreate(
                [
                    'institution_id' => $institutionId,
                    'user_id' => $user->id,
                ],
                [
                    'application_number' => $application->application_number,
                    'programme_id' => $intake->programme_id,
                    'academic_session_id' => $intake->academic_session_id,
                    'enrollment_status' => 'current',
                    'admitted_on' => now()->toDateString(),
                    'father_name' => $application->father_name,
                    'mother_name' => $application->mother_name,
                    'dob' => $application->dob,
                    'gender' => $application->gender,
                    'nationality' => $application->nationality,
                    'category' => $application->category,
                    'guardian_name' => $application->father_name,
                    'guardian_relation' => 'Father',
                    'guardian_phone' => $application->phone,
                    'address_line' => $application->address_line,
                    'city' => $application->city,
                    'state' => $application->state,
                    'pin_code' => $application->pin_code,
                ]
            );

            return [
                'application' => $application->fresh(),
                'user' => $user,
                'is_new_account' => $isNewAccount,
                'activation_token' => $activationToken,
            ];
        });

        $application = $result['application'];
        $user = $result['user'];

        $this->audit->record(
            $request,
            event: 'admission.application_enrolled',
            module: 'institution',
            subject: $application,
            newValues: [
                'application_number' => $application->application_number,
                'user_id' => $user->id,
                'user_email' => $user->email,
            ],
            institutionId: $institutionId,
        );

        $activationEmailSent = false;

        if ($result['is_new_account'] && $result['activation_token']) {
            try {
                (new AnonymousNotifiable())
                    ->route('mail', $user->email)
                    ->notify(
                        new AccountActivationNotification(
                            $user,
                            $result['activation_token']
                        )
                    );

                $activationEmailSent = true;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'message' => $result['is_new_account']
                ? ($activationEmailSent
                    ? 'Applicant enrolled. Student account created and activation email sent.'
                    : 'Applicant enrolled. Student account created, but activation email delivery failed.')
                : 'Applicant enrolled and linked to the existing account with the student role.',
            'activation_email_sent' => $result['is_new_account']
                ? $activationEmailSent
                : null,
            'application' => $application->fresh([
                'intake.programme',
                'enrolledUser:id,name,email,status',
            ]),
        ], 201);
    }
}
