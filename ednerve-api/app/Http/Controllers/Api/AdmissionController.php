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

        DB::transaction(function () use ($validated, $institutionId) {
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

            return $intake;
        });

        // Re-fetch to include relations for the audit record.
        $intake = AdmissionIntake::where('institution_id', $institutionId)
            ->where('programme_id', $programme->id)
            ->where('academic_session_id', $session->id)
            ->firstOrFail();

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
        $institutionId = $request->user()->institution_id;

        $query = Application::where('institution_id', $institutionId)
            ->with(['intake:id,name,programme_id', 'intake.programme:id,code,name'])
            ->orderByDesc('created_at');

        if ($intakeId = $request->query('intake_id')) {
            $query->where('intake_id', (int) $intakeId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn($where) => $where
                ->where('application_number', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"));
        }

        return response()->json(
            $query->paginate((int) $request->query('per_page', 20) ?: 20)
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
            ])
            ->findOrFail($id);

        return response()->json([
            'application' => $application,
        ]);
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

        $before = $application->toArray();

        $application->update([
            'status' => $validated['status'],
            'review_notes' => $validated['notes'] ?? $application->review_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

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

        $result = DB::transaction(function () use ($id, $institutionId) {
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
