<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionIntake;
use App\Models\Application;
use App\Models\Institution;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public applicant portal — no authentication required.
 * Every endpoint is institution-scoped and deliberately leaks nothing
 * beyond what an applicant needs (their own application, matched by
 * application number + date-of-birth as the "credential").
 */
class PublicAdmissionController extends Controller
{
    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * Show the public portal page data for an institution: name,
     * institution info and currently open intakes.
     */
    public function institution(string $institutionCode)
    {
        $institution = Institution::where('slug', $institutionCode)
            ->orWhere('code', strtoupper($institutionCode))
            ->where('status', 'active')
            ->firstOrFail();

        $intakes = AdmissionIntake::where('institution_id', $institution->id)
            ->where('status', 'open')
            ->where('close_date', '>=', now()->toDateString())
            ->with(['programme:id,code,name,level,duration_semesters', 'academicSession:id,code,name'])
            ->withCount([
                'applications as submitted_count' => fn ($q) => $q->whereIn('status', [
                    'new', 'under_review', 'shortlisted', 'waitlisted', 'admitted', 'enrolled',
                ]),
                'applications as enrolled_count' => fn ($q) => $q->where('status', 'enrolled'),
            ])
            ->orderBy('close_date')
            ->get()
            ->map(fn ($intake) => [
                'id' => $intake->id,
                'name' => $intake->name,
                'programme_code' => $intake->programme?->code,
                'programme_name' => $intake->programme?->name,
                'programme_level' => $intake->programme?->level,
                'session_code' => $intake->academicSession?->code,
                'open_date' => optional($intake->open_date)->toDateString(),
                'close_date' => optional($intake->close_date)->toDateString(),
                'seat_capacity' => $intake->seat_capacity,
                'enrolled_count' => (int) $intake->enrolled_count,
                'seats_remaining' => max(0, $intake->seat_capacity - (int) $intake->enrolled_count),
                'application_fee' => $intake->application_fee,
            ]);

        return response()->json([
            'institution' => [
                'code' => $institution->code,
                'name' => $institution->name,
                'institution_type' => $institution->institution_type,
                'city' => $institution->city,
                'state' => $institution->state,
                'website' => $institution->website,
            ],
            'intakes' => $intakes,
        ]);
    }

    /**
     * Submit an application to an open intake.
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'intake_id' => ['required', 'integer', 'exists:admission_intakes,id'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'mother_name' => ['nullable', 'string', 'max:120'],
            'dob' => ['required', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:20'],
            'nationality' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pin_code' => ['nullable', 'string', 'max:12'],
            'last_exam_name' => ['nullable', 'string', 'max:120'],
            'last_exam_board_or_univ' => ['nullable', 'string', 'max:150'],
            'last_exam_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'last_exam_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $intake = AdmissionIntake::with(['programme', 'academicSession'])
            ->findOrFail($validated['intake_id']);

        // Portal guarantees an institution-active, open intake that has not passed its close date.
        $institution = $intake->institution()->where('status', 'active')->firstOrFail();

        abort_unless($intake->status === 'open', 422, 'This intake is closed for applications.');
        abort_if(
            now()->toDateString() > optional($intake->close_date)->toDateString(),
            422,
            'The application window for this intake has closed.'
        );

        $application = DB::transaction(function () use ($validated, $intake) {
            $year = date('Y');
            $seq = Application::where('institution_id', $intake->institution_id)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count() + 1;

            $applicationNumber = strtoupper($intake->institution->code ?? 'APP')
                . '-' . $year . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

            return Application::create([
                ...$validated,
                'institution_id' => $intake->institution_id,
                'intake_id' => $intake->id,
                'application_number' => $applicationNumber,
                'status' => 'new',
            ]);
        });

        $this->audit->record(
            $request,
            event: 'admission.application_submitted',
            module: 'institution',
            subject: $application,
            newValues: [
                'application_number' => $application->application_number,
                'intake_id' => $intake->id,
                'intake' => $intake->name,
                'status' => 'new',
            ],
            institutionId: $intake->institution_id,
        );

        return response()->json([
            'message' => 'Application submitted successfully.',
            'application_number' => $application->application_number,
            'status' => $application->status,
            'intake' => $intake->name,
        ], 201);
    }

    /**
     * Applicant status lookup: application number + date of birth (as credential).
     */
    public function status(Request $request)
    {
        $validated = $request->validate([
            'application_number' => ['required', 'string', 'max:32'],
            'dob' => ['required', 'date', 'before:today'],
        ]);

        $application = Application::where('application_number', strtoupper(trim($validated['application_number'])))
            ->whereDate('dob', $validated['dob'])
            ->with(['intake.programme:id,code,name'])
            ->first();

        abort_if(!$application, 404, 'No application found for these details.');

        return response()->json([
            'application_number' => $application->application_number,
            'status' => $application->status,
            'status_label' => __(str_replace('_', ' ', $application->status)),
            'applicant_name' => $application->full_name,
            'programme' => $application->intake?->programme ? [
                'code' => $application->intake->programme->code,
                'name' => $application->intake->programme->name,
            ] : null,
            'intake' => $application->intake?->name,
            'submitted_at' => optional($application->created_at)->toDateTimeString(),
            'last_update' => optional($application->reviewed_at ?? $application->updated_at)->toDateTimeString(),
        ]);
    }
}
