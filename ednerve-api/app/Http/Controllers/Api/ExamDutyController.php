<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyAvailability;
use App\Models\ExamDutyRole;
use App\Models\ExamInvigilation;
use App\Models\ExamSubject;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExamDutyController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    private function ensureView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isInstitutionAdmin()
                || $user->isFaculty()
                || $user->hasRole('registrar')
                || $user->hasRole('examination_officer'),
            403,
            'You are not allowed to view examination duties.'
        );
    }

    private function ensureManage(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isInstitutionAdmin() || $user->hasRole('examination_officer'),
            403,
            'You are not allowed to manage examination duties.'
        );
    }

    private function findExam(int $institutionId, int $examId): Exam
    {
        return Exam::where('institution_id', $institutionId)->findOrFail($examId);
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

        abort_unless($isStaff, 422, 'The selected user is not an eligible staff member in this institution.');
    }

    /* Roles */
    public function roles(Request $request)
    {
        $this->ensureView($request);
        $roles = ExamDutyRole::where('institution_id', $request->user()->institution_id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
        return response()->json(['roles' => $roles]);
    }

    public function storeRole(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('exam_duty_roles', 'code')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'min_qualification' => ['nullable', 'string', 'max:255'],
            'duty_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $role = ExamDutyRole::create([
            'institution_id' => $institutionId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'min_qualification' => $validated['min_qualification'] ?? null,
            'duty_weight' => $validated['duty_weight'] ?? 1,
            'active' => $validated['active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $this->audit->record($request, 'exam.duty_role_created', 'examinations', $role, [], $role->toArray());
        return response()->json(['message' => 'Duty role created.', 'role' => $role], 201);
    }

    public function updateRole(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $role = ExamDutyRole::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('exam_duty_roles', 'code')->where('institution_id', $institutionId)->ignore($role->id)],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'min_qualification' => ['nullable', 'string', 'max:255'],
            'duty_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $before = $role->toArray();
        $role->fill($validated);
        $role->save();
        $this->audit->record($request, 'exam.duty_role_updated', 'examinations', $role, $before, $role->toArray());
        return response()->json(['message' => 'Duty role updated.', 'role' => $role]);
    }

    /* Availability */
    public function listAvailability(Request $request, int $examId)
    {
        $this->ensureView($request);
        $exam = $this->findExam($request->user()->institution_id, $examId);
        $rows = ExamDutyAvailability::where('institution_id', $exam->institution_id)
            ->where('exam_id', $exam->id)
            ->with(['user:id,name,email', 'examSession:id,code,label'])
            ->orderBy('user_id')->orderBy('duty_date')
            ->get();
        return response()->json(['availability' => $rows]);
    }

    public function storeAvailability(Request $request, int $examId)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'exam_session_id' => ['nullable', 'integer'],
            'duty_date' => ['nullable', 'date'],
            'available' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->assertStaffInInstitution($request, (int) $validated['user_id']);
        if (!empty($validated['exam_session_id'])) {
            abort_unless(
                DB::table('exam_sessions')->where('institution_id', $institutionId)->where('id', $validated['exam_session_id'])->exists(),
                422,
                'Invalid exam session slot.'
            );
        }

        $row = ExamDutyAvailability::updateOrCreate(
            [
                'institution_id' => $institutionId,
                'user_id' => (int) $validated['user_id'],
                'exam_id' => $exam->id,
                'exam_session_id' => $validated['exam_session_id'] ?? null,
                'duty_date' => $validated['duty_date'] ?? null,
            ],
            [
                'available' => (bool) $validated['available'],
                'reason' => $validated['reason'] ?? null,
            ]
        );
        $row->load(['user:id,name,email', 'examSession:id,code,label']);
        $this->audit->record($request, 'exam.duty_availability_set', 'examinations', $row, [], $row->toArray());
        return response()->json(['message' => 'Availability saved.', 'availability' => $row], 201);
    }

    /* Roster / assignments */
    public function roster(Request $request, int $examId)
    {
        $this->ensureView($request);
        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);
        $exam->load(['programme:id,code,name', 'academicSession:id,code,name']);

        $subjects = ExamSubject::where('exam_id', $exam->id)
            ->with(['subject:id,code,name', 'session:id,code,label', 'room:id,code,name,capacity'])
            ->orderBy('exam_date')->orderBy('start_time')->get();

        $subjectIds = $subjects->pluck('id');
        $assignments = ExamDutyAssignment::where('institution_id', $institutionId)
            ->whereIn('exam_subject_id', $subjectIds)
            ->with(['user:id,name,email', 'dutyRole:id,code,name,duty_weight', 'assignedBy:id,name'])
            ->get()
            ->groupBy('exam_subject_id');

        $availability = ExamDutyAvailability::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->get()
            ->groupBy('user_id');

        $roles = ExamDutyRole::where('institution_id', $institutionId)->where('active', true)->orderBy('sort_order')->get();
        $workload = $this->workloadSummary($institutionId, $exam->id);

        $slots = $subjects->map(function ($s) use ($assignments) {
            $rows = ($assignments->get($s->id) ?? collect())->map(fn ($a) => [
                'id' => $a->id,
                'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'email' => $a->user->email] : null,
                'duty_role' => $a->dutyRole ? ['id' => $a->dutyRole->id, 'code' => $a->dutyRole->code, 'name' => $a->dutyRole->name, 'duty_weight' => $a->dutyRole->duty_weight] : null,
                'status' => $a->status,
                'assigned_at' => $a->assigned_at,
                'responded_at' => $a->responded_at,
                'remarks' => $a->remarks,
                'assigned_by' => $a->assignedBy ? ['id' => $a->assignedBy->id, 'name' => $a->assignedBy->name] : null,
            ])->values();
            return [
                'exam_subject_id' => $s->id,
                'exam_date' => optional($s->exam_date)->format('Y-m-d'),
                'start_time' => $s->start_time ? substr((string)$s->start_time, 0, 5) : null,
                'end_time' => $s->end_time ? substr((string)$s->end_time, 0, 5) : null,
                'venue' => $s->venue,
                'subject' => $s->subject ? ['id' => $s->subject->id, 'code' => $s->subject->code, 'name' => $s->subject->name] : null,
                'session' => $s->session ? ['id' => $s->session->id, 'code' => $s->session->code, 'label' => $s->session->label] : null,
                'room' => $s->room ? ['id' => $s->room->id, 'code' => $s->room->code, 'name' => $s->room->name, 'capacity' => $s->room->capacity] : null,
                'assignments' => $rows,
            ];
        });

        $unavailable = $availability->map(function ($rows, $userId) {
            $user = $rows->first()->user;
            return [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : ['id' => (int)$userId],
                'entries' => $rows->map(fn ($r) => [
                    'id' => $r->id,
                    'available' => $r->available,
                    'duty_date' => optional($r->duty_date)->format('Y-m-d'),
                    'exam_session_id' => $r->exam_session_id,
                    'session' => $r->examSession ? ['id' => $r->examSession->id, 'code' => $r->examSession->code, 'label' => $r->examSession->label] : null,
                    'reason' => $r->reason,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'kind' => $exam->kind,
                'status' => $exam->status,
                'semester' => $exam->semester,
                'section' => $exam->section,
                'programme' => $exam->programme,
                'academic_session' => $exam->academicSession,
            ],
            'roles' => $roles,
            'slots' => $slots,
            'availability' => $unavailable,
            'workload' => $workload,
        ]);
    }

    public function storeAssignment(Request $request, int $examId)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);
        $validated = $request->validate([
            'exam_subject_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'duty_role_id' => ['required', 'integer'],
            'status' => ['nullable', Rule::in(['assigned', 'accepted', 'declined', 'replaced'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);
        $entry = ExamSubject::where('exam_id', $exam->id)->where('id', $validated['exam_subject_id'])->firstOrFail();
        $role = ExamDutyRole::where('institution_id', $institutionId)->where('id', $validated['duty_role_id'])->where('active', true)->firstOrFail();
        $this->assertStaffInInstitution($request, (int) $validated['user_id']);

        $assignment = ExamDutyAssignment::updateOrCreate(
            [
                'institution_id' => $institutionId,
                'exam_subject_id' => $entry->id,
                'user_id' => (int) $validated['user_id'],
            ],
            [
                'duty_role_id' => $role->id,
                'status' => $validated['status'] ?? 'assigned',
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'responded_at' => in_array(($validated['status'] ?? 'assigned'), ['accepted', 'declined'], true) ? now() : null,
                'remarks' => $validated['remarks'] ?? null,
            ]
        );

        $this->syncLegacyInvigilation($institutionId, $entry->id, (int) $validated['user_id'], $role, $assignment->status);
        $assignment->load(['user:id,name,email', 'dutyRole:id,code,name,duty_weight']);
        $this->audit->record($request, 'exam.duty_assigned', 'examinations', $assignment, [], $assignment->toArray());

        return response()->json(['message' => 'Duty assigned.', 'assignment' => $assignment], 201);
    }

    public function updateAssignment(Request $request, int $examId, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $this->findExam($institutionId, $examId);
        $assignment = ExamDutyAssignment::where('institution_id', $institutionId)
            ->whereHas('examSubject', fn ($q) => $q->where('exam_id', $examId))
            ->findOrFail($id);
        $validated = $request->validate([
            'duty_role_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['assigned', 'accepted', 'declined', 'replaced'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);
        $before = $assignment->toArray();
        if (!empty($validated['duty_role_id'])) {
            $role = ExamDutyRole::where('institution_id', $institutionId)->where('id', $validated['duty_role_id'])->where('active', true)->firstOrFail();
            $assignment->duty_role_id = $role->id;
        }
        if (!empty($validated['status'])) {
            $assignment->status = $validated['status'];
            if (in_array($validated['status'], ['accepted', 'declined', 'replaced'], true)) {
                $assignment->responded_at = now();
            }
        }
        if (array_key_exists('remarks', $validated)) {
            $assignment->remarks = $validated['remarks'];
        }
        $assignment->save();
        $assignment->load('dutyRole:id,code,name,duty_weight');
        $this->syncLegacyInvigilation($institutionId, $assignment->exam_subject_id, $assignment->user_id, $assignment->dutyRole, $assignment->status);
        $this->audit->record($request, 'exam.duty_assignment_updated', 'examinations', $assignment, $before, $assignment->toArray());
        return response()->json(['message' => 'Duty assignment updated.', 'assignment' => $assignment->load(['user:id,name,email', 'dutyRole:id,code,name,duty_weight'])]);
    }

    public function destroyAssignment(Request $request, int $examId, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $this->findExam($institutionId, $examId);
        $assignment = ExamDutyAssignment::where('institution_id', $institutionId)
            ->whereHas('examSubject', fn ($q) => $q->where('exam_id', $examId))
            ->findOrFail($id);
        ExamInvigilation::where('institution_id', $institutionId)
            ->where('exam_subject_id', $assignment->exam_subject_id)
            ->where('user_id', $assignment->user_id)
            ->delete();
        $before = $assignment->toArray();
        $assignment->delete();
        $this->audit->record($request, 'exam.duty_assignment_removed', 'examinations', null, $before, ['id' => $id, 'deleted' => true]);
        return response()->json(['message' => 'Duty assignment removed.']);
    }

    public function autoAssign(Request $request, int $examId)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);
        $validated = $request->validate([
            'duty_role_id' => ['required', 'integer'],
            'required_per_slot' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_per_staff' => ['nullable', 'integer', 'min:1', 'max:50'],
            'staff_user_ids' => ['nullable', 'array'],
            'staff_user_ids.*' => ['integer'],
            'replace_declined' => ['nullable', 'boolean'],
        ]);
        $role = ExamDutyRole::where('institution_id', $institutionId)->where('id', $validated['duty_role_id'])->where('active', true)->firstOrFail();
        $required = (int) ($validated['required_per_slot'] ?? 1);
        $maxPerStaff = isset($validated['max_per_staff']) ? (int) $validated['max_per_staff'] : null;
        $replaceDeclined = (bool) ($validated['replace_declined'] ?? false);

        $subjects = ExamSubject::where('exam_id', $exam->id)->orderBy('exam_date')->orderBy('start_time')->get();
        $subjectIds = $subjects->pluck('id')->all();

        $staffIds = collect($validated['staff_user_ids'] ?? []);
        if ($staffIds->isEmpty()) {
            $staffIds = DB::table('user_role_assignments')
                ->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
                ->where('user_role_assignments.institution_id', $institutionId)
                ->where('user_role_assignments.status', 'active')
                ->whereIn('roles.code', ['faculty', 'hod', 'dean', 'registrar', 'examination_officer', 'institution_admin'])
                ->pluck('user_role_assignments.user_id')->unique()->values();
        }

        $workload = ExamDutyAssignment::where('exam_duty_assignments.institution_id', $institutionId)
            ->whereIn('exam_duty_assignments.exam_subject_id', $subjectIds)
            ->whereNotIn('exam_duty_assignments.status', ['declined', 'replaced'])
            ->join('exam_duty_roles', function ($join) use ($institutionId) {
                $join->on('exam_duty_assignments.duty_role_id', '=', 'exam_duty_roles.id')
                    ->where('exam_duty_roles.institution_id', '=', $institutionId);
            })
            ->selectRaw('exam_duty_assignments.user_id, SUM(exam_duty_roles.duty_weight) as weight_sum, COUNT(*) as duty_count')
            ->groupBy('exam_duty_assignments.user_id')
            ->get()
            ->keyBy('user_id');

        $unavailable = ExamDutyAvailability::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->where('available', false)
            ->get();

        $isUnavailable = function (int $userId, $subject) use ($unavailable): bool {
            foreach ($unavailable as $u) {
                if ((int) $u->user_id !== $userId) continue;
                if ($u->duty_date && optional($subject->exam_date)->format('Y-m-d') !== optional($u->duty_date)->format('Y-m-d')) continue;
                if ($u->exam_session_id && (int) $u->exam_session_id !== (int) $subject->exam_session_id) continue;
                return true;
            }
            return false;
        };

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($subjects, $staffIds, $role, $required, $maxPerStaff, $replaceDeclined, $institutionId, $exam, $request, &$created, &$skipped, &$workload, $isUnavailable) {
            foreach ($subjects as $subject) {
                $existing = ExamDutyAssignment::where('institution_id', $institutionId)
                    ->where('exam_subject_id', $subject->id)
                    ->get();
                $activeForRole = $existing->filter(fn ($a) => (int) $a->duty_role_id === (int) $role->id && !in_array($a->status, ['declined', 'replaced'], true))->count();
                $need = max(0, $required - $activeForRole);
                if ($need <= 0) {
                    continue;
                }
                $assignedUserIds = $existing->whereNotIn('status', ['declined', 'replaced'])->pluck('user_id')->map(fn ($v) => (int) $v)->all();
                $candidates = $staffIds->map(fn ($uid) => (int) $uid)->filter(function ($uid) use ($assignedUserIds, $isUnavailable, $subject, $workload, $maxPerStaff) {
                    if (in_array($uid, $assignedUserIds, true)) return false;
                    if ($isUnavailable($uid, $subject)) return false;
                    $count = (int) (($workload[$uid]->duty_count ?? 0));
                    if ($maxPerStaff !== null && $count >= $maxPerStaff) return false;
                    return true;
                })->sortBy(function ($uid) use ($workload) {
                    $w = $workload[$uid] ?? null;
                    return [(int) ($w->weight_sum ?? 0), (int) ($w->duty_count ?? 0), (int) $uid];
                })->values();

                foreach ($candidates->take($need) as $uid) {
                    $declined = $replaceDeclined ? $existing->first(fn ($a) => (int)$a->user_id === $uid && $a->status === 'declined') : null;
                    $assignment = ExamDutyAssignment::updateOrCreate(
                        [
                            'institution_id' => $institutionId,
                            'exam_subject_id' => $subject->id,
                            'user_id' => $uid,
                        ],
                        [
                            'duty_role_id' => $role->id,
                            'status' => 'assigned',
                            'assigned_by' => $request->user()->id,
                            'assigned_at' => now(),
                            'responded_at' => null,
                            'remarks' => $declined ? 'Reassigned after declined duty.' : null,
                        ]
                    );
                    $this->syncLegacyInvigilation($institutionId, $subject->id, $uid, $role, 'assigned');
                    $current = $workload[$uid] ?? (object)['weight_sum' => 0, 'duty_count' => 0];
                    $workload[$uid] = (object)[
                        'user_id' => $uid,
                        'weight_sum' => (int) $current->weight_sum + (int) $role->duty_weight,
                        'duty_count' => (int) $current->duty_count + 1,
                    ];
                    $created++;
                }
                if ($candidates->count() < $need) {
                    $skipped += ($need - $candidates->count());
                }
            }
            $this->audit->record($request, 'exam.duty_roster_auto_assigned', 'examinations', $exam, [], [
                'duty_role_id' => $role->id,
                'required_per_slot' => $required,
                'created' => $created,
                'skipped_unfilled' => $skipped,
            ]);
        });

        return response()->json([
            'message' => 'Auto-assignment completed.',
            'created' => $created,
            'skipped_unfilled' => $skipped,
        ]);
    }

    public function printPayload(Request $request, int $examId)
    {
        $this->ensureView($request);
        $roster = $this->roster($request, $examId);
        $data = $roster->getData(true);
        $data['generated_at'] = now()->toIso8601String();
        return response()->json($data);
    }

    private function workloadSummary(int $institutionId, int $examId): array
    {
        $subjectIds = ExamSubject::where('exam_id', $examId)->pluck('id');
        $rows = ExamDutyAssignment::where('institution_id', $institutionId)
            ->whereIn('exam_subject_id', $subjectIds)
            ->whereNotIn('status', ['declined', 'replaced'])
            ->with(['user:id,name,email', 'dutyRole:id,code,name,duty_weight'])
            ->get();
        $byUser = [];
        foreach ($rows as $row) {
            $uid = $row->user_id;
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user' => $row->user ? ['id' => $row->user->id, 'name' => $row->user->name, 'email' => $row->user->email] : ['id' => $uid],
                    'duty_count' => 0,
                    'weighted_load' => 0,
                    'accepted' => 0,
                    'assigned' => 0,
                ];
            }
            $byUser[$uid]['duty_count']++;
            $byUser[$uid]['weighted_load'] += (int) ($row->dutyRole->duty_weight ?? 1);
            if ($row->status === 'accepted') $byUser[$uid]['accepted']++;
            if ($row->status === 'assigned') $byUser[$uid]['assigned']++;
        }
        usort($byUser, fn ($a, $b) => [$b['weighted_load'], $b['duty_count']] <=> [$a['weighted_load'], $a['duty_count']]);
        return array_values($byUser);
    }

    private function syncLegacyInvigilation(int $institutionId, int $examSubjectId, int $userId, ?ExamDutyRole $role, string $status): void
    {
        if (in_array($status, ['declined', 'replaced'], true)) {
            ExamInvigilation::where('institution_id', $institutionId)
                ->where('exam_subject_id', $examSubjectId)
                ->where('user_id', $userId)
                ->delete();
            return;
        }
        $legacyDuty = ($role && $role->code === 'CHIEF_SUPERINTENDENT') ? 'chief' : 'invigilator';
        ExamInvigilation::updateOrCreate(
            [
                'exam_subject_id' => $examSubjectId,
                'user_id' => $userId,
            ],
            [
                'institution_id' => $institutionId,
                'duty' => $legacyDuty,
            ]
        );
    }
}
