<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamEligibility;
use App\Models\ExamSubject;
use App\Services\AuditService;
use App\Services\ExamConflictService;
use App\Services\OrdinanceService;
use Illuminate\Http\Request;

/**
 * Schedule execution support: conflict checks, per-subject room/session
 * assignment, and the attendance-eligibility gate with audited override.
 */
class ExamSchedulingController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly OrdinanceService $ordinances,
        private readonly ExamConflictService $conflicts,
    ) {}

    private function ensureView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isInstitutionAdmin() || $user->isFaculty()
                || $user->hasRole('registrar') || $user->hasRole('examination_officer')),
            403,
            'You are not allowed to view exam scheduling.'
        );
    }

    private function ensureManage(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isInstitutionAdmin() || $user->hasRole('examination_officer')),
            403,
            'Institution administrator or examination officer required.'
        );
    }

    private function findExam(int $institutionId, int $examId): Exam
    {
        return Exam::where('institution_id', $institutionId)->findOrFail($examId);
    }

    public function assignSchedule(Request $request, int $examId, int $entryId)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        abort_unless($exam->status === 'scheduled', 409, 'Only scheduled exams can have room/session assignments changed.');

        $entry = ExamSubject::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->findOrFail($entryId);

        $validated = $request->validate([
            'exam_session_id' => ['nullable', 'integer', 'exists:exam_sessions,id'],
            'exam_room_id' => ['nullable', 'integer', 'exists:exam_rooms,id'],
            'eligibility_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'instructions' => ['nullable', 'string'],
        ]);

        foreach (['exam_session_id', 'exam_room_id'] as $key) {
            if (!empty($validated[$key])) {
                $table = $key === 'exam_session_id' ? 'exam_sessions' : 'exam_rooms';
                abort_unless(
                    \DB::table($table)->where('institution_id', $institutionId)->where('id', $validated[$key])->exists(),
                    422,
                    "Selected {$key} does not belong to this institution."
                );
            }
        }

        $entry->update($validated);

        $this->audit->record($request, event: 'examination.schedule_assigned', module: 'institution',
            subject: $entry->fresh(), newValues: $validated, institutionId: $institutionId);

        return response()->json(['message' => 'Schedule updated.', 'subject' => $entry->fresh('session', 'room')]);
    }

    public function conflicts(Request $request, int $examId)
    {
        $this->ensureView($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId)->load('programme:id,code,name', 'academicSession:id,start_date,end_date');

        $entries = ExamSubject::where('exam_id', $exam->id)
            ->with(['subject:id,code,name', 'room'])
            ->get();

        // Let the service see the raw collection with relations.
        $conflicts = $this->conflicts->checkForExam($exam, $entries);

        return response()->json([
            'exam_id' => $exam->id,
            'conflicts' => $conflicts,
            'count' => count($conflicts),
            'checked' => count($entries),
        ]);
    }

    public function computeEligibility(Request $request, int $examId)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $from = null;
        $to = null;
        if ($exam->academic_session_id) {
            $session = \DB::table('academic_sessions')->where('id', $exam->academic_session_id)->first();
            $from = $session->start_date;
            $to = $session->end_date;
        }

        $defaultThreshold = $this->ordinances->attendanceThreshold(
            $this->ordinances->resolveOrNull($institutionId)
        );

        $entries = ExamSubject::where('exam_id', $exam->id)->with('subject:id,code,name')->get();

        $students = \DB::table('student_profiles')
            ->where('institution_id', $institutionId)
            ->where('programme_id', $exam->programme_id)
            ->where('enrollment_status', 'current')
            ->pluck('id');

        if ($students->isEmpty()) {
            return response()->json(['message' => 'No enrolled students in this exam cohort.', 'computed' => 0]);
        }

        $summary = ['eligible' => 0, 'short' => 0, 'kept_override' => 0, 'no_data' => 0];

        \DB::transaction(function () use ($entries, $students, $institutionId, $defaultThreshold, $from, $to, &$summary) {
            foreach ($entries as $entry) {
                $threshold = $entry->eligibility_threshold !== null
                    ? (float) $entry->eligibility_threshold
                    : $defaultThreshold;

                foreach ($students as $studentId) {
                    $pct = $this->ordinances->attendancePercent($institutionId, (int) $studentId, $from, $to);
                    $status = $pct === null ? 'short' : ($pct >= $threshold ? 'eligible' : 'short');

                    $existing = ExamEligibility::where('exam_subject_id', $entry->id)
                        ->where('student_profile_id', $studentId)
                        ->first();

                    if ($existing && $existing->status === 'overridden') {
                        $existing->update([
                            'attendance_pct' => $pct,
                            'computed_at' => now(),
                        ]);
                        $summary['kept_override']++;
                        continue;
                    }

                    ExamEligibility::updateOrCreate(
                        ['exam_subject_id' => $entry->id, 'student_profile_id' => $studentId],
                        [
                            'institution_id' => $institutionId,
                            'status' => $status,
                            'attendance_pct' => $pct,
                            'reason' => null,
                            'decided_by' => null,
                            'computed_at' => now(),
                        ]
                    );

                    $summary[$status]++;
                    if ($pct === null) {
                        $summary['no_data']++;
                    }
                }
            }
        });

        $this->audit->record($request, event: 'examination.eligibility_computed', module: 'institution',
            subject: $exam, newValues: ['summary' => $summary], institutionId: $institutionId);

        return response()->json(['message' => 'Eligibility computed.', 'summary' => $summary]);
    }

    public function eligibilityReport(Request $request, int $examId)
    {
        $this->ensureView($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);
        $kind = $request->query('status');

        $entries = ExamSubject::where('exam_id', $exam->id)
            ->with(['subject:id,code,name'])
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'code' => $e->subject->code, 'name' => $e->subject->name, 'threshold' => $e->eligibility_threshold]);

        $eligibilities = ExamEligibility::where('institution_id', $institutionId)
            ->whereIn('exam_subject_id', $entries->pluck('id'))
            ->when($kind, fn ($q) => $q->where('status', $kind))
            ->orderBy('exam_subject_id')
            ->orderByDesc('status')
            ->get();

        $profileIds = $eligibilities->pluck('student_profile_id')->unique()->values();
        $names = \DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->whereIn('student_profiles.id', $profileIds)
            ->select(['student_profiles.id as profile_id', 'student_profiles.application_number', 'users.name as user_name'])
            ->get()
            ->mapWithKeys(fn ($row) => [$row->profile_id => $row]);

        $rows = $eligibilities->map(function ($row) use ($names) {
            $profile = $names->get($row->student_profile_id);

            return [
                'id' => $row->id,
                'exam_subject_id' => $row->exam_subject_id,
                'student_profile_id' => $row->student_profile_id,
                'application_number' => $profile->application_number ?? null,
                'student_name' => $profile->user_name ?? null,
                'status' => $row->status,
                'attendance_pct' => $row->attendance_pct,
                'reason' => $row->reason,
            ];
        })->values();

        return response()->json([
            'subjects' => $entries,
            'data' => $rows,
            'summary' => [
                'eligible' => $rows->where('status', 'eligible')->count(),
                'short' => $rows->where('status', 'short')->count(),
                'overridden' => $rows->where('status', 'overridden')->count(),
            ],
        ]);
    }

    public function overrideEligibility(Request $request)
    {
        // Route is protected by the mfa.privileged middleware.
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'exam_subject_id' => ['required', 'integer'],
            'student_profile_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $entry = ExamSubject::where('institution_id', $institutionId)->findOrFail($validated['exam_subject_id']);
        \App\Models\StudentProfile::where('institution_id', $institutionId)->findOrFail($validated['student_profile_id']);

        $eligibility = ExamEligibility::firstOrCreate(
            ['exam_subject_id' => $entry->id, 'student_profile_id' => $validated['student_profile_id']],
            ['institution_id' => $institutionId, 'status' => 'short', 'computed_at' => now()]
        );

        $eligibility->update([
            'status' => 'overridden',
            'reason' => $validated['reason'],
            'decided_by' => $request->user()->id,
        ]);

        $this->audit->record($request, event: 'examination.eligibility_override', module: 'institution',
            subject: $eligibility, newValues: ['reason' => $validated['reason'], 'student_profile_id' => $validated['student_profile_id'], 'exam_subject_id' => $entry->id],
            institutionId: $institutionId);

        return response()->json(['message' => 'Eligibility override recorded.', 'eligibility' => $eligibility->fresh()]);
    }
}
