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
    /**
     * Show the public portal page data for an institution: name,
     * institution info and currently open intakes.
     */
    public function institution(string $institutionCode)
    {
        $institution = Institution::where(function ($query) use ($institutionCode) {
            $query->where('slug', $institutionCode)
                ->orWhere('code', strtoupper($institutionCode));
        })
            ->where('status', 'active')
            ->firstOrFail();

        $intakes = AdmissionIntake::where('institution_id', $institution->id)
            ->where('status', 'open')
            ->where('close_date', '>=', now()->toDateString())
            ->with(['programme:id,code,name,level,duration_semesters', 'academicSession:id,code,name'])
            ->withCount([
                'applications as submitted_count' => fn($q) => $q->whereIn('status', [
                    'new',
                    'under_review',
                    'shortlisted',
                    'waitlisted',
                    'admitted',
                    'enrolled',
                ]),
                'applications as enrolled_count' => fn($q) => $q->where('status', 'enrolled'),
            ])
            ->orderBy('close_date')
            ->get()
            ->map(fn($intake) => [
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
     * Anonymous application submission is retired. Applicants must register
     * and submit through the authenticated applicant workspace.
     */
    public function submit(Request $request)
    {
        return response()->json([
            'message' => 'Anonymous application submission is no longer available. Please create an applicant account and submit through the applicant portal.',
            'code' => 'ACCOUNT_REQUIRED',
        ], 410);
    }

    /**
     * Public, read-only application tracking.
     * The institution is resolved first and the application query is scoped to it.
     * A generic 404 is used for every mismatch to avoid application enumeration.
     */
    public function status(Request $request, string $institutionCode)
    {
        $validated = $request->validate([
            'application_number' => ['required', 'string', 'max:40'],
            'dob' => ['required', 'date', 'before:today'],
        ]);

        $institution = Institution::query()
            ->where(function ($query) use ($institutionCode) {
                $query->where('slug', strtolower($institutionCode))
                    ->orWhere('code', strtoupper($institutionCode));
            })
            ->where('status', 'active')
            ->first();

        if (!$institution) {
            return response()->json(['message' => 'We could not find an application with these details.'], 404)->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
        }

        $application = Application::query()
            ->where('institution_id', $institution->id)
            ->where('application_number', strtoupper(trim($validated['application_number'])))
            ->whereDate('dob', $validated['dob'])
            ->whereNotIn('status', ['draft'])
            ->with(['intake.programme:id,code,name'])
            ->first();

        if (!$application) {
            return response()->json(['message' => 'We could not find an application with these details.'], 404)->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
        }

        $submittedAt = DB::table('admission_application_events')
            ->where('institution_id', $institution->id)
            ->where('application_id', $application->id)
            ->whereIn('event_type', ['applicant_submitted', 'application_submitted'])
            ->orderByDesc('occurred_at')
            ->value('occurred_at');

        return response()->json([
            'application_number' => $application->application_number,
            'status' => $application->status,
            'status_label' => __(str_replace('_', ' ', $application->status)),
            'applicant_name' => $application->full_name,
            'institution' => $institution->name,
            'programme' => $application->intake?->programme ? [
                'code' => $application->intake->programme->code,
                'name' => $application->intake->programme->name,
            ] : null,
            'intake' => $application->intake?->name,
            'submitted_at' => $submittedAt ? \Illuminate\Support\Carbon::parse($submittedAt)->toIso8601String() : optional($application->created_at)->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }

}
