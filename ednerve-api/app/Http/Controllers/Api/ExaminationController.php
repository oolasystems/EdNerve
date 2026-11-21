<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamComponent;
use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyRole;
use App\Models\ExamInvigilation;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Programme;
use App\Models\RevaluationRequest;
use App\Models\Result;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OrdinanceService;
use App\Services\ExamConflictService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExaminationController extends Controller
{
    use RequiresAccountType;

    private readonly OrdinanceService $ordinances;
    private readonly ExamConflictService $conflicts;

    public function __construct(
        private readonly AuditService $audit,
    ) {
        $this->ordinances = new OrdinanceService();
        $this->conflicts = new ExamConflictService($this->ordinances);
    }

    /* =========================
     |  Authorization helpers
     |========================= */

    private function ensureExamView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isInstitutionAdmin()
                || $user->isFaculty()
                || $user->hasRole('registrar')
                || $user->hasRole('examination_officer'),
            403,
            'You are not allowed to view examinations.'
        );
    }

    private function ensureExamManage(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isInstitutionAdmin() || $user->hasRole('examination_officer'),
            403,
            'Institution administrator or examination officer access required.'
        );
    }

    private function isExamOfficer(Request $request): bool
    {
        return $request->user()->hasRole('examination_officer');
    }

    private function assertKindAllowed(int $institutionId, string $kind): void
    {
        $configured = \App\Models\ExamKind::where('institution_id', $institutionId)->where('active', true);
        if ($configured->exists()) {
            abort_unless(
                $configured->where('code', strtolower($kind))->exists(),
                422,
                'That examination kind is not enabled for this institution. Configure it under exam kinds.'
            );
        }
    }

    private function gradeFor(float $percent): string
    {
        // Obsolete call sites: grades come from the institution's ordinance.
        // Kept for backward compatibility (mirrors legacy bands).
        return $this->ordinances->gradeForBands(OrdinanceService::LEGACY_BANDS, $percent);
    }

    /* =========================
     |  Exams CRUD
     |========================= */

    public function index(Request $request)
    {
        $this->ensureExamView($request);

        $institutionId = $request->user()->institution_id;

        $query = Exam::where('institution_id', $institutionId)
            ->with(['programme:id,code,name', 'academicSession:id,code,name'])
            ->withCount('subjects')
            ->orderByDesc('created_at');

        if ($programmeId = $request->query('programme_id')) {
            $query->where('programme_id', (int) $programmeId);
        }
        if ($semester = $request->query('semester')) {
            $query->where('semester', (int) $semester);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'exams' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', 'string', 'max:30'],
            'academic_session_id' => ['nullable', 'integer'],
            'programme_id' => ['required', 'integer'],
            'semester' => ['required', 'integer', 'min:1', 'max:12'],
            'section' => ['nullable', 'string', 'max:20'],
            'instructions' => ['nullable', 'string'],
        ]);

        $this->assertKindAllowed($institutionId, $validated['kind']);

        Programme::where('institution_id', $institutionId)->findOrFail($validated['programme_id']);
        if (!empty($validated['academic_session_id'])) {
            \App\Models\AcademicSession::where('institution_id', $institutionId)
                ->findOrFail($validated['academic_session_id']);
        }

        $exam = Exam::create([
            'institution_id' => $institutionId,
            'title' => $validated['title'],
            'kind' => $validated['kind'],
            'academic_session_id' => $validated['academic_session_id'] ?? null,
            'programme_id' => $validated['programme_id'],
            'semester' => $validated['semester'],
            'section' => $validated['section'] ?? 'A',
            'instructions' => $validated['instructions'] ?? null,
            'status' => 'scheduled',
        ]);

        $this->audit->record(
            $request,
            event: 'examination.exam_created',
            module: 'institution',
            subject: $exam,
            newValues: $exam->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Examination created.',
            'exam' => $exam->load(['programme:id,code,name', 'academicSession:id,code']),
        ], 201);
    }

    public function show(Request $request, int $examId)
    {
        $this->ensureExamView($request);

        $exam = $this->findExam($request->user()->institution_id, $examId);
        $examsSubjects = $this->examSubjectsPayload($exam);

        return response()->json([
            'exam' => $exam,
            'subjects' => $examsSubjects,
            'invigilations' => $this->invigilationsPayload($exam),
            'results_published' => $exam->status === 'published',
            'marks_state' => $this->marksState($exam),
        ]);
    }

    public function update(Request $request, int $examId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:150'],
            'kind' => ['sometimes', 'string', 'max:30'],
            'academic_session_id' => ['sometimes', 'nullable', 'integer'],
            'programme_id' => ['sometimes', 'integer'],
            'semester' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'section' => ['sometimes', 'string', 'max:20'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:scheduled,completed'],
        ]);

        if (isset($validated['kind'])) {
            $this->assertKindAllowed($institutionId, $validated['kind']);
        }

        if (isset($validated['programme_id'])) {
            Programme::where('institution_id', $institutionId)->findOrFail($validated['programme_id']);
        }
        if (array_key_exists('academic_session_id', $validated) && $validated['academic_session_id'] !== null) {
            \App\Models\AcademicSession::where('institution_id', $institutionId)->findOrFail($validated['academic_session_id']);
        }

        $exam->update($validated);

        $this->audit->record(
            $request,
            event: 'examination.exam_updated',
            module: 'institution',
            subject: $exam->fresh(),
            newValues: $validated,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Examination updated.',
            'exam' => $exam->fresh(['programme:id,code,name', 'academicSession:id,code']),
        ]);
    }

    public function destroy(Request $request, int $examId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        abort_unless($exam->status === 'scheduled', 409, 'Only scheduled examinations can be deleted.');

        $hasMarks = Mark::where('institution_id', $institutionId)
            ->whereIn('exam_component_id', ExamComponent::where('institution_id', $institutionId)
                ->whereIn('exam_subject_id', ExamSubject::where('exam_id', $exam->id)->pluck('id'))
                ->pluck('id'))
            ->exists();
        abort_if($hasMarks, 409, 'Cannot delete an examination that has marks recorded.');

        $exam->delete();

        return response()->json(['message' => 'Examination deleted.']);
    }

    /* =========================
     |  Exam subjects + components
     |========================= */

    public function storeSubject(Request $request, int $examId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        abort_unless($exam->status === 'scheduled', 409, 'Subjects can only be changed while the exam is scheduled.');

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:50'],
            'pass_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.name' => ['required', 'string', 'max:60'],
            'components.*.max_marks' => ['required', 'numeric', 'min:0.01'],
            'components.*.position' => ['nullable', 'integer', 'min:1'],
        ]);

        Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);
        $duplicate = ExamSubject::where('exam_id', $exam->id)
            ->where('subject_id', $validated['subject_id'])
            ->exists();
        abort_if($duplicate, 422, 'That subject is already scheduled for this examination.');

        $entry = DB::transaction(function () use ($institutionId, $exam, $validated) {
            $entry = ExamSubject::create([
                'institution_id' => $institutionId,
                'exam_id' => $exam->id,
                'subject_id' => $validated['subject_id'],
                'exam_date' => $validated['exam_date'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'venue' => $validated['venue'] ?? null,
                'pass_percent' => $validated['pass_percent'] ?? 40,
            ]);
            foreach ($validated['components'] as $index => $component) {
                ExamComponent::create([
                    'institution_id' => $institutionId,
                    'exam_subject_id' => $entry->id,
                    'name' => trim($component['name']),
                    'max_marks' => $component['max_marks'],
                    'position' => $component['position'] ?? ($index + 1),
                ]);
            }
            return $entry;
        });

        $this->audit->record(
            $request,
            event: 'examination.subject_added',
            module: 'institution',
            subject: $entry,
            newValues: $entry->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject added to examination.',
            'subject_entry' => $this->examSubjectPayload($entry->fresh()),
        ], 201);
    }

    public function updateSubject(Request $request, int $examId, int $subjectEntryId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        abort_unless($exam->status === 'scheduled', 409, 'Schedule can only be changed while the exam is scheduled.');

        $entry = ExamSubject::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->findOrFail($subjectEntryId);

        $validated = $request->validate([
            'exam_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pass_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'components' => ['sometimes', 'array', 'min:1'],
            'components.*.name' => ['required_with:components', 'string', 'max:60'],
            'components.*.max_marks' => ['required_with:components', 'numeric', 'min:0.01'],
            'components.*.position' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($institutionId, $entry, $validated) {
            $entry->fill($validated);
            $entry->save();
            if (array_key_exists('components', $validated)) {
                $componentIds = $entry->components()->pluck('id');
                // Deleting components cascades their marks — only allowed when nothing is marked.
                $marked = Mark::where('institution_id', $institutionId)
                    ->whereIn('exam_component_id', $componentIds)
                    ->exists();
                abort_if($marked, 409, 'Cannot change components after marks have been entered.');
                $entry->components()->delete();
                foreach ($validated['components'] as $index => $component) {
                    ExamComponent::create([
                        'institution_id' => $institutionId,
                        'exam_subject_id' => $entry->id,
                        'name' => trim($component['name']),
                        'max_marks' => $component['max_marks'],
                        'position' => $component['position'] ?? ($index + 1),
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Subject entry updated.',
            'subject_entry' => $this->examSubjectPayload($entry->fresh()),
        ]);
    }

    public function destroySubject(Request $request, int $examId, int $subjectEntryId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        abort_unless($exam->status === 'scheduled', 409, 'Subjects can only be removed while the exam is scheduled.');

        $entry = ExamSubject::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->findOrFail($subjectEntryId);

        $marked = Mark::where('institution_id', $institutionId)
            ->whereIn('exam_component_id', $entry->components()->pluck('id'))
            ->exists();
        abort_if($marked, 409, 'Cannot remove a subject with marks recorded.');

        $entry->delete();

        return response()->json(['message' => 'Subject removed from examination.']);
    }

    /* =========================
     |  Marks
     |========================= */

    public function marksGrid(Request $request, int $examId)
    {
        $this->ensureExamView($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $subjectEntryId = (int) $request->query('subject_id');
        abort_if(!$subjectEntryId, 422, 'subject_id is required.');

        $entry = ExamSubject::where('exam_id', $exam->id)
            ->with(['components' => fn ($q) => $q->orderBy('position'), 'subject:id,code,name'])
            ->findOrFail($subjectEntryId);

        $students = $this->rosterStudents($institutionId, $exam);

        $componentIds = $entry->components->pluck('id');
        $marks = Mark::where('institution_id', $institutionId)
            ->whereIn('exam_component_id', $componentIds)
            ->get()
            ->groupBy('student_profile_id');

        $rows = $students->map(fn ($student) => [
            'student_profile_id' => (int) $student->profile_id,
            'application_number' => $student->application_number,
            'name' => $student->name,
            'email' => $student->email,
            'marks' => $componentIds->mapWithKeys(function ($componentId) use ($marks, $student) {
                $mark = $marks->get($student->profile_id)?->firstWhere('exam_component_id', $componentId);
                return [
                    (string) $componentId => $mark ? [
                        'status' => $mark->status,
                        'marks_obtained' => $mark->marks_obtained,
                    ] : null,
                ];
            }),
        ]);

        return response()->json([
            'subject_entry' => $this->examSubjectPayload($entry),
            'students' => $rows,
        ]);
    }

    public function saveMarks(Request $request, int $examId)
    {
        $this->ensureExamManage($request); // examination officer or admin

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        abort_unless(in_array($exam->status, ['scheduled', 'completed'], true), 409, 'Marks cannot be changed after results are published.');

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'component_id' => ['required', 'integer'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_profile_id' => ['required', 'integer'],
            'records.*.status' => ['required', 'in:present,absent'],
            'records.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'records.*.remarks' => ['nullable', 'string', 'max:150'],
        ]);

        $entry = ExamSubject::where('exam_id', $exam->id)->findOrFail($validated['subject_id']);
        $component = ExamComponent::where('exam_subject_id', $entry->id)->findOrFail($validated['component_id']);

        $maxMarks = (float) $component->max_marks;
        $validStudentIds = DB::table('student_profiles')
            ->where('institution_id', $institutionId)
            ->where('programme_id', $exam->programme_id)
            ->where('enrollment_status', 'current')
            ->whereIn('id', collect($validated['records'])->pluck('student_profile_id')->all())
            ->pluck('id')
            ->all();

        $saved = 0;
        $enteredBy = $request->user()->id;
        DB::transaction(function () use ($institutionId, $component, $maxMarks, $validated, $validStudentIds, &$saved, $enteredBy) {
            foreach ($validated['records'] as $record) {
                $studentId = (int) $record['student_profile_id'];
                if (!in_array($studentId, $validStudentIds, true)) {
                    continue; // student not in this programme — skip silently
                }
                if ($record['status'] === 'absent') {
                    $marks = null;
                } else {
                    $marks = $record['marks_obtained'] ?? null;
                    abort_if($marks === null, 422, 'Marks are required for present students.');
                    abort_if((float) $marks > $maxMarks, 422, "Marks cannot exceed {$maxMarks} for component {$component->name}.");
                }
                Mark::updateOrCreate(
                    [
                        'exam_component_id' => $component->id,
                        'student_profile_id' => $studentId,
                    ],
                    [
                        'institution_id' => $institutionId,
                        'status' => $record['status'],
                        'marks_obtained' => $marks,
                        'remarks' => $record['remarks'] ?? null,
                        'entered_by' => $enteredBy,
                    ]
                );
                $saved++;
            }
        });

        $this->audit->record(
            $request,
            event: 'examination.marks_saved',
            module: 'institution',
            subject: $component,
            newValues: ['saved' => $saved, 'component_id' => $component->id],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Marks saved.',
            'saved' => $saved,
        ]);
    }

    /* =========================
     |  Results + publish
     |========================= */

    public function results(Request $request, int $examId)
    {
        $this->ensureExamView($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $rows = $this->computeResultRows($exam);

        return response()->json([
            'exam' => $exam,
            'published' => $exam->status === 'published',
            'results' => $rows,
        ]);
    }

    public function publish(Request $request, int $examId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        abort_unless(in_array($exam->status, ['scheduled', 'completed', 'published'], true), 409, 'Cannot publish in current state.');

        // Separation of duties: the person who entered marks cannot publish them.
        $selfEntered = Mark::where('institution_id', $institutionId)
            ->whereIn('exam_component_id', ExamComponent::whereIn('exam_subject_id', ExamSubject::where('exam_id', $exam->id)->pluck('id'))->pluck('id'))
            ->where('entered_by', $request->user()->id)
            ->exists();
        abort_if($selfEntered, 403, 'Separation of duties: you entered marks for this examination and cannot publish it. Ask another privileged user to publish.');

        $rows = DB::transaction(function () use ($request, $exam, $institutionId) {
            $ordinance = $this->ordinances->resolveOrNull($institutionId);
            $rows = $this->computeResultRows($exam);

            // Students with a completed revaluation get the flag on their frozen row.
            $revaluedStudentIds = RevaluationRequest::where('exam_id', $exam->id)
                ->where('status', 'completed')
                ->pluck('student_profile_id')
                ->all();

            foreach ($rows as $row) {
                $existing = Result::where('institution_id', $institutionId)
                    ->where('exam_id', $exam->id)
                    ->where('student_profile_id', $row['student']['id'])
                    ->first();

                $newVersion = 1;
                if ($existing) {
                    \App\Models\ResultHistory::create([
                        'institution_id' => $institutionId,
                        'exam_id' => $exam->id,
                        'student_profile_id' => $existing->student_profile_id,
                        'total_obtained' => $existing->total_obtained,
                        'total_max' => $existing->total_max,
                        'percentage' => $existing->percentage,
                        'grade' => $existing->grade,
                        'result_status' => $existing->result_status,
                        'revaluation_applied' => $existing->revaluation_applied,
                        'version' => $existing->version,
                        'superseded_by' => $request->user()->id,
                        'computed_at' => $existing->computed_at,
                        'superseded_at' => now(),
                    ]);
                    $newVersion = $existing->version + 1;
                }

                Result::updateOrCreate(
                    [
                        'institution_id' => $institutionId,
                        'exam_id' => $exam->id,
                        'student_profile_id' => $row['student']['id'],
                    ],
                    [
                        'total_obtained' => $row['total']['obtained'],
                        'total_max' => $row['total']['max'],
                        'percentage' => $row['total']['percent'],
                        'grade' => $row['total']['grade'],
                        'result_status' => $row['total']['status'],
                        'revaluation_applied' => in_array($row['student']['id'], $revaluedStudentIds, true),
                        'ordinance_id' => $ordinance?->id ?? null,
                        'external_percent' => $row['total']['external_percent'] ?? null,
                        'breakdown' => $row['total']['breakdown'] ?? null,
                        'version' => $newVersion,
                        'computed_at' => now(),
                    ]
                );
            }

            $exam->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $request->user()->id,
            ]);

            $this->audit->record(
                $request,
                event: 'examination.results_published',
                module: 'institution',
                subject: $exam->fresh(),
                newValues: ['students' => count($rows)],
                institutionId: $institutionId,
            );

            return $rows;
        });

        return response()->json([
            'message' => 'Results published.',
            'published' => count($rows),
        ]);
    }

    private function computeResultRows(Exam $exam): array
    {
        $institutionId = $exam->institution_id;

        // Ordinance (grading bands + pass criteria) — falls back to legacy constants.
        $ordinance = $this->ordinances->resolveOrNull($institutionId);
        $bands = $this->ordinances->bandsFor($ordinance);
        $criteria = $this->ordinances->criteriaFor($ordinance);
        $schemeFlagsCache = [];

        $entries = ExamSubject::where('exam_id', $exam->id)
            ->with(['components' => fn ($q) => $q->orderBy('position'), 'subject:id,code,name'])
            ->get();

        $students = $this->rosterStudents($institutionId, $exam);

        $componentIds = $entries->flatMap(fn ($e) => $e->components->pluck('id'))->all();
        $marks = Mark::where('institution_id', $institutionId)
            ->whereIn('exam_component_id', $componentIds)
            ->get()
            ->groupBy('student_profile_id');

        $rows = [];
        foreach ($students as $student) {
            $studentMarks = $marks->get($student->profile_id) ?? collect();
            $subjectRows = [];
            $grandObtained = 0.0;
            $grandMax = 0.0;
            $extObtainedTotal = 0.0;
            $extMaxTotal = 0.0;
            $intObtainedTotal = 0.0;
            $intMaxTotal = 0.0;
            $allPass = true;

            foreach ($entries as $entry) {
                // External-component flags: subject scheme wins over programme scheme.
                $flagKey = $entry->subject_id . '|' . ($exam->programme_id ?? 0);
                if (!isset($schemeFlagsCache[$flagKey])) {
                    $schemeFlagsCache[$flagKey] = $this->ordinances->externalComponentFlags(
                        $institutionId,
                        $entry->subject_id,
                        $exam->programme_id
                    );
                }
                $extFlags = $schemeFlagsCache[$flagKey];

                $entryMax = $entry->components->sum('max_marks');
                $obtained = 0.0;
                $extObtained = 0.0;
                $extMax = 0.0;
                foreach ($entry->components as $component) {
                    $mark = $studentMarks->firstWhere('exam_component_id', $component->id);
                    $componentObtained = ($mark && $mark->status === 'present' && $mark->marks_obtained !== null)
                        ? (float) $mark->marks_obtained
                        : 0.0;
                    $obtained += $componentObtained;

                    if ($extFlags !== [] && ($extFlags[mb_strtolower(trim($component->name))] ?? false) === false) {
                        continue; // not an external component (flag present but false)
                    }
                    if ($extFlags === []) {
                        continue; // no scheme flags -> external check disabled (legacy)
                    }
                    $extObtained += $componentObtained;
                    $extMax += (float) $component->max_marks;
                }
                $extObtainedTotal += $extObtained;
                $extMaxTotal += $extMax;
                $intObtainedTotal += $obtained - $extObtained;
                $intMaxTotal += $entryMax - $extMax;

                $percent = $entryMax > 0 ? round($obtained / $entryMax * 100, 1) : 0.0;
                $pass = $percent >= (float) $entry->pass_percent;
                if (!$pass) {
                    $allPass = false;
                }
                $grandObtained += $obtained;
                $grandMax += $entryMax;
                $subjectRows[] = [
                    'subject_id' => $entry->subject_id,
                    'code' => $entry->subject->code,
                    'name' => $entry->subject->name,
                    'obtained' => $obtained,
                    'max' => (float) $entryMax,
                    'percent' => $percent,
                    'grade' => $this->ordinances->gradeForBands($bands, $percent),
                    'pass' => $pass,
                    'pass_percent' => (float) $entry->pass_percent,
                ];
            }

            $totalPercent = $grandMax > 0 ? round($grandObtained / $grandMax * 100, 1) : 0.0;
            $externalPercent = $extMaxTotal > 0 ? round($extObtainedTotal / $extMaxTotal * 100, 2) : null;
            $overallPasses = $this->ordinances->passes($criteria, $totalPercent, $externalPercent);

            $breakdown = [
                'internal' => [
                    'obtained' => round($intObtainedTotal, 2),
                    'max' => round($intMaxTotal, 2),
                    'percent' => $intMaxTotal > 0 ? round($intObtainedTotal / $intMaxTotal * 100, 2) : null,
                ],
                'external' => $extMaxTotal > 0 ? [
                    'obtained' => round($extObtainedTotal, 2),
                    'max' => round($extMaxTotal, 2),
                    'percent' => $externalPercent,
                ] : null,
                'criteria' => $criteria,
                'ordinance' => $ordinance?->code,
            ];

            $rows[] = [
                'student' => [
                    'id' => (int) $student->profile_id,
                    'application_number' => $student->application_number,
                    'name' => $student->name,
                    'email' => $student->email,
                ],
                'subjects' => $subjectRows,
                'total' => [
                    'obtained' => round($grandObtained, 2),
                    'max' => round($grandMax, 2),
                    'percent' => $totalPercent,
                    'grade' => $this->ordinances->gradeForBands($bands, $totalPercent),
                    'status' => ($allPass && $overallPasses) ? 'pass' : 'fail',
                    'external_percent' => $externalPercent,
                    'breakdown' => $breakdown,
                ],
            ];
        }

        return $rows;
    }

    /* =========================
     |  Invigilation
     |========================= */

    public function assignInvigilator(Request $request, int $examId, int $subjectEntryId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $entry = ExamSubject::where('exam_id', $exam->id)->findOrFail($subjectEntryId);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'duty' => ['nullable', 'in:invigilator,chief'],
            'duty_role_id' => ['nullable', 'integer'],
            'duty_role_code' => ['nullable', 'string', 'max:40'],
        ]);

        $this->assertStaffInInstitution($request, (int) $validated['user_id']);
        $legacyDuty = $validated['duty'] ?? 'invigilator';
        $role = $this->resolveDutyRole($institutionId, $validated['duty_role_id'] ?? null, $validated['duty_role_code'] ?? null, $legacyDuty);

        $invigilation = ExamInvigilation::updateOrCreate(
            [
                'exam_subject_id' => $entry->id,
                'user_id' => (int) $validated['user_id'],
            ],
            [
                'institution_id' => $institutionId,
                'duty' => $legacyDuty,
            ]
        );

        $assignment = null;
        if ($role) {
            $assignment = ExamDutyAssignment::updateOrCreate(
                [
                    'institution_id' => $institutionId,
                    'exam_subject_id' => $entry->id,
                    'user_id' => (int) $validated['user_id'],
                ],
                [
                    'duty_role_id' => $role->id,
                    'status' => 'assigned',
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'responded_at' => null,
                ]
            );
        }

        return response()->json([
            'message' => 'Invigilator assigned.',
            'invigilation' => $invigilation->load('user:id,name,email'),
            'assignment' => $assignment?->load(['user:id,name,email', 'dutyRole:id,code,name']),
        ], 201);
    }

    public function removeInvigilator(Request $request, int $examId, int $invigilationId)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;
        $legacy = ExamInvigilation::where('institution_id', $institutionId)
            ->whereHas('examSubject', fn ($q) => $q->whereHas('exam', fn ($e) => $e->where('id', $examId)))
            ->find($invigilationId);

        if ($legacy) {
            ExamDutyAssignment::where('institution_id', $institutionId)
                ->where('exam_subject_id', $legacy->exam_subject_id)
                ->where('user_id', $legacy->user_id)
                ->delete();
            $legacy->delete();
            return response()->json(['message' => 'Invigilator removed.']);
        }

        $assignment = ExamDutyAssignment::where('institution_id', $institutionId)
            ->whereHas('examSubject', fn ($q) => $q->where('exam_id', $examId))
            ->findOrFail($invigilationId);

        ExamInvigilation::where('institution_id', $institutionId)
            ->where('exam_subject_id', $assignment->exam_subject_id)
            ->where('user_id', $assignment->user_id)
            ->delete();
        $assignment->delete();

        return response()->json(['message' => 'Invigilator removed.']);
    }

    public function myInvigilations(Request $request)
    {
        $this->ensureExamView($request);

        $institutionId = $request->user()->institution_id;
        $userId = $request->user()->id;

        $assignments = ExamDutyAssignment::where('institution_id', $institutionId)
            ->where('user_id', $userId)
            ->with(['examSubject.exam:id,title,kind,section', 'examSubject.subject:id,code,name', 'dutyRole:id,code,name'])
            ->get();

        if ($assignments->isNotEmpty()) {
            $duties = $assignments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'exam_subject_id' => $a->exam_subject_id,
                    'duty' => $a->dutyRole?->code === 'CHIEF_SUPERINTENDENT' ? 'chief' : 'invigilator',
                    'duty_role' => $a->dutyRole ? ['id' => $a->dutyRole->id, 'code' => $a->dutyRole->code, 'name' => $a->dutyRole->name] : null,
                    'status' => $a->status,
                    'exam_subject' => $a->examSubject ? [
                        'id' => $a->examSubject->id,
                        'exam' => $a->examSubject->exam ? [
                            'id' => $a->examSubject->exam->id,
                            'title' => $a->examSubject->exam->title,
                            'kind' => $a->examSubject->exam->kind,
                            'section' => $a->examSubject->exam->section,
                        ] : null,
                        'subject' => $a->examSubject->subject ? [
                            'id' => $a->examSubject->subject->id,
                            'code' => $a->examSubject->subject->code,
                            'name' => $a->examSubject->subject->name,
                        ] : null,
                    ] : null,
                ];
            });

            return response()->json(['duties' => $duties]);
        }

        $duties = ExamInvigilation::where('institution_id', $institutionId)
            ->where('user_id', $userId)
            ->with(['examSubject.exam:id,title,kind,section', 'examSubject.subject:id,code,name'])
            ->get();

        return response()->json([
            'duties' => $duties,
        ]);
    }

    /* =========================
     |  Revaluation
     |========================= */

    public function storeRevaluation(Request $request)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'exam_id' => ['required', 'integer'],
            'student_profile_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $exam = $this->findExam($institutionId, (int) $validated['exam_id']);
        $student = StudentProfile::where('institution_id', $institutionId)
            ->where('programme_id', $exam->programme_id)
            ->findOrFail($validated['student_profile_id']);

        // Subject must be part of the exam.
        $inExam = ExamSubject::where('exam_id', $exam->id)
            ->where('subject_id', $validated['subject_id'])
            ->exists();
        abort_unless($inExam, 422, 'That subject is not part of this examination.');

        $existing = RevaluationRequest::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->where('student_profile_id', $student->id)
            ->where('subject_id', $validated['subject_id'])
            ->first();

        if ($existing && $existing->status !== 'rejected') {
            abort(422, 'A revaluation request already exists for this subject.');
        }

        $payload = [
            'reason' => $validated['reason'],
            'fee_amount' => $validated['fee_amount'] ?? null,
            'status' => 'requested',
            'requested_by' => $request->user()->id,
            'handled_by' => null,
        ];

        if ($existing) {
            $existing->update($payload);
            $revaluation = $existing->fresh();
        } else {
            $revaluation = RevaluationRequest::create(array_merge([
                'institution_id' => $institutionId,
                'exam_id' => $exam->id,
                'student_profile_id' => $student->id,
                'subject_id' => $validated['subject_id'],
            ], $payload));
        }

        $this->audit->record(
            $request,
            event: 'examination.revaluation_requested',
            module: 'institution',
            subject: $revaluation,
            newValues: $revaluation->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Revaluation request recorded.',
            'revaluation' => $revaluation,
        ], 201);
    }

    public function listRevaluations(Request $request)
    {
        $this->ensureExamView($request);

        $institutionId = $request->user()->institution_id;

        $query = RevaluationRequest::where('institution_id', $institutionId)
            ->with([
                'exam:id,title,kind,status',
                'subject:id,code,name',
                'studentProfile:id,application_number,user_id',
                'studentProfile.user:id,name,email',
                'requestedBy:id,name',
                'handledBy:id,name',
            ])
            ->orderByDesc('created_at');

        if ($examId = $request->query('exam_id')) {
            $query->where('exam_id', (int) $examId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'revaluations' => $query->get(),
        ]);
    }

    public function updateRevaluationStatus(Request $request, int $id)
    {
        $this->ensureExamManage($request);

        $institutionId = $request->user()->institution_id;

        $revaluation = RevaluationRequest::where('institution_id', $institutionId)->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:in_review,completed,rejected'],
        ]);

        $revaluation->update([
            'status' => $validated['status'],
            'handled_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Revaluation status updated.',
            'revaluation' => $revaluation->fresh(),
        ]);
    }

    /* =========================
     |  Hall ticket
     |========================= */

    public function hallTicket(Request $request, int $examId)
    {
        $this->ensureExamView($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $studentProfileId = (int) $request->query('student_profile_id');
        abort_if(!$studentProfileId, 422, 'student_profile_id is required.');

        $student = StudentProfile::where('institution_id', $institutionId)
            ->where('programme_id', $exam->programme_id)
            ->with(['user:id,name,email', 'programme:id,code,name', 'academicSession:id,code'])
            ->findOrFail($studentProfileId);

        $entries = ExamSubject::where('exam_id', $exam->id)
            ->with('subject:id,code,name')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $instThreshold = $entries->pluck('eligibility_threshold')->filter()->map(fn ($v) => (float) $v)->min();
        $defaultThreshold = $this->ordinances->attendanceThreshold($this->ordinances->resolveOrNull($institutionId));

        $ineligible = \App\Models\ExamEligibility::where('institution_id', $institutionId)
            ->whereIn('exam_subject_id', $entries->pluck('id'))
            ->where('student_profile_id', $studentProfileId)
            ->where('status', 'short')
            ->count();
        abort_if(
            $ineligible > 0,
            403,
            'Hall ticket withheld: attendance is below the required threshold (' . ($instThreshold ?? $defaultThreshold) . '%). Contact the examination office — eligibility can be overridden with a documented reason by an authorised user.'
        );

        $institution = DB::table('institutions')->where('id', $institutionId)->first();

        return response()->json([
            'institution' => [
                'name' => $institution->name ?? '',
                'code' => $institution->code ?? '',
            ],
            'exam' => $exam->load(['programme:id,code,name', 'academicSession:id,code,name']),
            'student' => $student,
            'subjects' => $entries->map(fn ($e) => [
                'code' => $e->subject->code,
                'name' => $e->subject->name,
                'date' => $e->exam_date->toDateString(),
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
                'venue' => $e->venue,
            ]),
            'hall_ticket_number' => strtoupper(($institution->code ?? 'EXM') . '/' . $exam->id . '/' . ($student->application_number ?? str_pad((string) $student->id, 6, '0', STR_PAD_LEFT))),
        ]);
    }

    /* =========================
     |  Helpers
     |========================= */

    private function findExam(int $institutionId, int $examId): Exam
    {
        return Exam::where('institution_id', $institutionId)
            ->with(['programme:id,code,name', 'academicSession:id,code,name'])
            ->findOrFail($examId);
    }

    private function rosterStudents(int $institutionId, Exam $exam)
    {
        return DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.institution_id', $institutionId)
            ->where('student_profiles.programme_id', $exam->programme_id)
            ->where('student_profiles.enrollment_status', 'current')
            ->orderBy('users.name')
            ->get([
                'student_profiles.id as profile_id',
                'student_profiles.application_number',
                'users.name',
                'users.email',
            ]);
    }

    private function examSubjectsPayload(Exam $exam): array
    {
        $entries = ExamSubject::where('exam_id', $exam->id)
            ->with(['components' => fn ($q) => $q->orderBy('position'), 'subject:id,code,name,credits,subject_type', 'session', 'room'])
            ->orderBy('exam_date')
            ->get();

        return $entries->map(fn ($entry) => $this->examSubjectPayload($entry))->all();
    }

    private function examSubjectPayload(ExamSubject $entry): array
    {
        return [
            'id' => $entry->id,
            'subject_id' => $entry->subject_id,
            'subject' => $entry->subject ? [
                'id' => $entry->subject->id,
                'code' => $entry->subject->code,
                'name' => $entry->subject->name,
                'credits' => $entry->subject->credits,
                'subject_type' => $entry->subject->subject_type,
            ] : null,
            'exam_date' => $entry->exam_date->toDateString(),
            'exam_session_id' => $entry->exam_session_id,
            'exam_room_id' => $entry->exam_room_id,
            'session' => $entry->relationLoaded('session') && $entry->session ? [
                'id' => $entry->session->id,
                'code' => $entry->session->code,
                'label' => $entry->session->label,
            ] : null,
            'room' => $entry->relationLoaded('room') && $entry->room ? [
                'id' => $entry->room->id,
                'code' => $entry->room->code,
                'name' => $entry->room->name,
                'capacity' => $entry->room->capacity,
            ] : null,
            'eligibility_threshold' => $entry->eligibility_threshold,
            'instructions' => $entry->instructions,
            'start_time' => $entry->start_time,
            'end_time' => $entry->end_time,
            'venue' => $entry->venue,
            'pass_percent' => (float) $entry->pass_percent,
            'total_max' => (float) $entry->components->sum('max_marks'),
            'components' => $entry->components->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'max_marks' => (float) $c->max_marks,
                'position' => $c->position,
            ])->all(),
        ];
    }

    private function invigilationsPayload(Exam $exam): array
    {
        $subjectIds = ExamSubject::where('exam_id', $exam->id)->pluck('id');
        $assignments = ExamDutyAssignment::where('institution_id', $exam->institution_id)
            ->whereIn('exam_subject_id', $subjectIds)
            ->with(['user:id,name,email', 'examSubject:id,subject_id', 'examSubject.subject:id,code', 'dutyRole:id,code,name,duty_weight'])
            ->get();

        if ($assignments->isNotEmpty()) {
            return $assignments->map(fn ($a) => [
                'id' => $a->id,
                'legacy_invigilation_id' => ExamInvigilation::where('exam_subject_id', $a->exam_subject_id)->where('user_id', $a->user_id)->value('id'),
                'exam_subject_id' => $a->exam_subject_id,
                'subject_code' => $a->examSubject?->subject?->code,
                'duty' => $a->dutyRole?->code === 'CHIEF_SUPERINTENDENT' ? 'chief' : 'invigilator',
                'duty_role_id' => $a->duty_role_id,
                'duty_role_code' => $a->dutyRole?->code,
                'duty_role_name' => $a->dutyRole?->name,
                'status' => $a->status,
                'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'email' => $a->user->email] : null,
            ])->all();
        }

        return ExamInvigilation::whereHas('examSubject', fn ($q) => $q->where('exam_id', $exam->id))
            ->with(['user:id,name,email', 'examSubject:id,subject_id', 'examSubject.subject:id,code'])
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'legacy_invigilation_id' => $i->id,
                'exam_subject_id' => $i->exam_subject_id,
                'subject_code' => $i->examSubject?->subject?->code,
                'duty' => $i->duty,
                'duty_role_code' => $i->duty === 'chief' ? 'CHIEF_SUPERINTENDENT' : 'INVIGILATOR',
                'duty_role_name' => $i->duty === 'chief' ? 'Chief Superintendent' : 'Invigilator',
                'status' => 'assigned',
                'user' => $i->user ? ['id' => $i->user->id, 'name' => $i->user->name, 'email' => $i->user->email] : null,
            ])
            ->all();
    }

    private function marksState(Exam $exam): array
    {
        $entries = ExamSubject::where('exam_id', $exam->id)
            ->with('components:id,exam_subject_id')
            ->get();
        $componentIds = $entries->flatMap(fn ($e) => $e->components->pluck('id'))->all();
        $markedStudents = Mark::whereIn('exam_component_id', $componentIds)
            ->distinct('student_profile_id')
            ->count('student_profile_id');

        return [
            'components' => count($componentIds),
            'students_with_marks' => $markedStudents,
            'total_students' => $this->rosterStudents($exam->institution_id, $exam)->count(),
        ];
    }

    private function resolveDutyRole(int $institutionId, ?int $roleId, ?string $roleCode, string $legacyDuty): ?ExamDutyRole
    {
        if ($roleId) {
            return ExamDutyRole::where('institution_id', $institutionId)->where('id', $roleId)->where('active', true)->first();
        }
        $code = $roleCode ?: ($legacyDuty === 'chief' ? 'CHIEF_SUPERINTENDENT' : 'INVIGILATOR');
        return ExamDutyRole::where('institution_id', $institutionId)->where('code', $code)->where('active', true)->first();
    }

    private function assertStaffInInstitution(Request $request, int $userId): void
    {
        $isStaff = DB::table('user_role_assignments')
            ->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
            ->where('user_role_assignments.institution_id', $request->user()->institution_id)
            ->where('user_role_assignments.user_id', $userId)
            ->where('user_role_assignments.status', 'active')
            ->whereIn('roles.code', ['faculty', 'hod', 'dean', 'registrar', 'examination_officer', 'institution_admin'])
            ->exists();
        abort_unless($isStaff, 422, 'User is not a staff member of this institution.');
    }
}
