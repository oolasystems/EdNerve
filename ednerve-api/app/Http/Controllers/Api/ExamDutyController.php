<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyAvailability;
use App\Models\ExamDutyRole;
use App\Models\ExamDutyRoster;
use App\Models\ExamSubject;
use App\Services\AuditService;
use App\Services\ExamDutyLegacySyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExamDutyController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ExamDutyLegacySyncService $legacySync
    ) {}

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

    private function latestRoster(
        int $institutionId,
        int $examId,
        bool $lock = false
    ): ExamDutyRoster {
        $query = ExamDutyRoster::where('institution_id', $institutionId)
            ->where('exam_id', $examId)
            ->orderByDesc('version');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function submitRoster(Request $request, int $examId)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $roster = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam
        ) {
            // Lock the latest roster version.
            $roster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            // Only draft rosters can be submitted.
            $this->ensureDraftRoster($roster);

            $before = $roster->toArray();

            $roster->status = 'submitted';
            $roster->submitted_by = $request->user()->id;
            $roster->submitted_at = now();

            $roster->save();

            $this->audit->record(
                $request,
                'exam.duty_roster_submitted',
                'examinations',
                $roster,
                $before,
                $roster->toArray()
            );

            return $roster;
        });

        return response()->json([
            'message' => 'Duty roster submitted for approval.',
            'roster' => $roster,
        ]);
    }

    public function approveRoster(Request $request, int $examId)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $roster = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam
        ) {
            // Lock the latest roster version.
            $roster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            // Only submitted rosters can be approved.
            abort_unless(
                $roster->status === 'submitted',
                409,
                'Only a submitted duty roster can be approved.'
            );

            // Enforce separation of duties.
            abort_unless(
                (int) $roster->submitted_by !== (int) $request->user()->id,
                403,
                'The user who submitted this roster cannot approve it.'
            );

            $before = $roster->toArray();

            $roster->status = 'approved';
            $roster->approved_by = $request->user()->id;
            $roster->approved_at = now();

            $roster->save();

            $this->audit->record(
                $request,
                'exam.duty_roster_approved',
                'examinations',
                $roster,
                $before,
                $roster->toArray()
            );

            return $roster;
        });

        return response()->json([
            'message' => 'Duty roster approved successfully.',
            'roster' => $roster,
        ]);
    }

    public function rejectRoster(Request $request, int $examId)
    {
        $this->ensureManage($request);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $roster = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam,
            $validated
        ) {
            // Lock the latest roster version.
            $roster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            // Only submitted rosters can be rejected.
            abort_unless(
                $roster->status === 'submitted',
                409,
                'Only a submitted duty roster can be rejected.'
            );

            // Enforce separation of duties.
            abort_unless(
                (int) $roster->submitted_by !== (int) $request->user()->id,
                403,
                'The user who submitted this roster cannot reject it.'
            );

            $before = $roster->toArray();

            $roster->status = 'rejected';
            $roster->rejected_by = $request->user()->id;
            $roster->rejected_at = now();
            $roster->rejection_reason = $validated['rejection_reason'];

            $roster->save();

            $this->audit->record(
                $request,
                'exam.duty_roster_rejected',
                'examinations',
                $roster,
                $before,
                $roster->toArray()
            );

            return $roster;
        });

        return response()->json([
            'message' => 'Duty roster rejected successfully.',
            'roster' => $roster,
        ]);
    }

    public function publishRoster(Request $request, int $examId)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $roster = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam
        ) {
            // Lock the latest roster version before changing its status.
            $roster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            // Only an approved roster can be published.
            abort_unless(
                $roster->status === 'approved',
                409,
                'Only an approved duty roster can be published.'
            );

            $before = $roster->toArray();

            $roster->status = 'published';
            $roster->published_by = $request->user()->id;
            $roster->published_at = now();
            $roster->save();

            // Rebuild the legacy projection in the same transaction as publication.
            $this->legacySync->syncPublishedRoster($roster);

            $this->audit->record(
                $request,
                'exam.duty_roster_published',
                'examinations',
                $roster,
                $before,
                $roster->toArray()
            );

            return $roster;
        });

        return response()->json([
            'message' => 'Duty roster published successfully.',
            'roster' => $roster,
        ]);
    }

    public function createRosterVersion(Request $request, int $examId)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $roster = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam
        ) {
            // Lock the latest version to serialize version creation.
            $previousRoster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            abort_unless(
                in_array($previousRoster->status, ['rejected', 'published'], true),
                409,
                'A new roster version can only be created from a rejected or published version.'
            );

            $previousVersion = (int) $previousRoster->version;
            $newVersion = $previousVersion + 1;

            // Create the next version as a fresh draft.
            $newRoster = ExamDutyRoster::create([
                'institution_id' => $institutionId,
                'exam_id' => $exam->id,
                'version' => $newVersion,
                'status' => 'draft',
            ]);

            // Copy only assignments that are still active in the previous version.
            $previousAssignments = ExamDutyAssignment::where(
                'institution_id',
                $institutionId
            )
                ->where('roster_id', $previousRoster->id)
                ->whereIn('status', ['assigned', 'accepted'])
                ->with('dutyRole')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $copiedCount = 0;

            foreach ($previousAssignments as $oldAssignment) {
                $newAssignment = ExamDutyAssignment::create([
                    'institution_id' => $institutionId,
                    'exam_subject_id' => $oldAssignment->exam_subject_id,
                    'roster_id' => $newRoster->id,
                    'user_id' => $oldAssignment->user_id,
                    'duty_role_id' => $oldAssignment->duty_role_id,
                    'status' => 'assigned',
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'responded_at' => null,
                    'remarks' => null,
                    'replaced_by_assignment_id' => null,
                ]);

                // Keep the legacy invigilation table synchronized.

                $copiedCount++;
            }

            // Record version creation in the audit trail.
            $this->audit->record(
                $request,
                'exam.duty_roster_version_created',
                'examinations',
                $newRoster,
                [
                    'previous_roster_id' => $previousRoster->id,
                    'previous_version' => $previousVersion,
                    'previous_status' => $previousRoster->status,
                ],
                [
                    'new_roster_id' => $newRoster->id,
                    'new_version' => $newVersion,
                    'new_status' => 'draft',
                    'copied_assignments' => $copiedCount,
                ],
                "Created duty roster version {$newVersion} from version {$previousVersion}."
            );

            return $newRoster->loadCount('assignments');
        });

        return response()->json([
            'message' => 'New duty roster version created successfully.',
            'roster' => $roster,
            'copied_assignments' => $roster->assignments_count,
        ], 201);
    }

    public function rosterHistory(Request $request, int $examId)
    {
        $this->ensureView($request);

        $institutionId = $request->user()->institution_id;

        // Tenant-scoped examination lookup.
        $exam = $this->findExam($institutionId, $examId);

        $versions = ExamDutyRoster::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->with([
                'submittedBy:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'publishedBy:id,name',
            ])
            ->withCount('assignments')
            ->orderByDesc('version')
            ->get()
            ->map(function ($roster) {
                return [
                    'id' => $roster->id,
                    'version' => $roster->version,
                    'status' => $roster->status,

                    'submitted_by' => $roster->submittedBy
                        ? [
                            'id' => $roster->submittedBy->id,
                            'name' => $roster->submittedBy->name,
                        ]
                        : null,
                    'submitted_at' => $roster->submitted_at,

                    'approved_by' => $roster->approvedBy
                        ? [
                            'id' => $roster->approvedBy->id,
                            'name' => $roster->approvedBy->name,
                        ]
                        : null,
                    'approved_at' => $roster->approved_at,

                    'rejected_by' => $roster->rejectedBy
                        ? [
                            'id' => $roster->rejectedBy->id,
                            'name' => $roster->rejectedBy->name,
                        ]
                        : null,
                    'rejected_at' => $roster->rejected_at,
                    'rejection_reason' => $roster->rejection_reason,

                    'published_by' => $roster->publishedBy
                        ? [
                            'id' => $roster->publishedBy->id,
                            'name' => $roster->publishedBy->name,
                        ]
                        : null,
                    'published_at' => $roster->published_at,

                    'assignment_count' => $roster->assignments_count,
                    'created_at' => $roster->created_at,
                    'updated_at' => $roster->updated_at,
                ];
            });

        return response()->json([
            'exam_id' => $exam->id,
            'versions' => $versions,
        ]);
    }

    private function ensureDraftRoster(ExamDutyRoster $roster): void
    {
        abort_unless(
            $roster->status === 'draft',
            409,
            'This roster version is not editable. Only a draft roster can be modified.'
        );
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

        $exam->load([
            'programme:id,code,name',
            'academicSession:id,code,name',
        ]);

        // Retrieve the latest roster version for this examination.
        $roster = $this->latestRoster($institutionId, $exam->id);

        $subjects = ExamSubject::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->with([
                'subject:id,code,name',
                'session:id,code,label',
                'room:id,code,name,capacity',
            ])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $subjectIds = $subjects->pluck('id');

        // Retrieve assignments belonging only to the latest roster version.
        $assignments = ExamDutyAssignment::where('institution_id', $institutionId)
            ->where('roster_id', $roster->id)
            ->whereIn('exam_subject_id', $subjectIds)
            ->with([
                'user:id,name,email',
                'dutyRole:id,code,name,duty_weight',
                'assignedBy:id,name',
            ])
            ->get()
            ->groupBy('exam_subject_id');

        $availability = ExamDutyAvailability::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->with(['user:id,name,email', 'examSession:id,code,label'])
            ->get()
            ->groupBy('user_id');

        $roles = ExamDutyRole::where('institution_id', $institutionId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        // Preserve the existing F5.2 cross-examination workload calculation.
        $workload = $this->workloadSummary($institutionId, $exam);

        $slots = $subjects->map(function ($s) use ($assignments) {
            $rows = ($assignments->get($s->id) ?? collect())->map(fn($a) => [
                'id' => $a->id,
                'user' => $a->user ? [
                    'id' => $a->user->id,
                    'name' => $a->user->name,
                    'email' => $a->user->email,
                ] : null,
                'duty_role' => $a->dutyRole ? [
                    'id' => $a->dutyRole->id,
                    'code' => $a->dutyRole->code,
                    'name' => $a->dutyRole->name,
                    'duty_weight' => $a->dutyRole->duty_weight,
                ] : null,
                'status' => $a->status,
                'assigned_at' => $a->assigned_at,
                'responded_at' => $a->responded_at,
                'remarks' => $a->remarks,
                'assigned_by' => $a->assignedBy ? [
                    'id' => $a->assignedBy->id,
                    'name' => $a->assignedBy->name,
                ] : null,
            ])->values();

            return [
                'exam_subject_id' => $s->id,
                'exam_date' => optional($s->exam_date)->format('Y-m-d'),
                'start_time' => $s->start_time ? substr((string) $s->start_time, 0, 5) : null,
                'end_time' => $s->end_time ? substr((string) $s->end_time, 0, 5) : null,
                'venue' => $s->venue,
                'subject' => $s->subject ? [
                    'id' => $s->subject->id,
                    'code' => $s->subject->code,
                    'name' => $s->subject->name,
                ] : null,
                'session' => $s->session ? [
                    'id' => $s->session->id,
                    'code' => $s->session->code,
                    'label' => $s->session->label,
                ] : null,
                'room' => $s->room ? [
                    'id' => $s->room->id,
                    'code' => $s->room->code,
                    'name' => $s->room->name,
                    'capacity' => $s->room->capacity,
                ] : null,
                'assignments' => $rows,
            ];
        });

        $unavailable = $availability->map(function ($rows, $userId) {
            $user = $rows->first()->user;

            return [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : ['id' => (int) $userId],
                'entries' => $rows->map(fn($r) => [
                    'id' => $r->id,
                    'available' => $r->available,
                    'duty_date' => optional($r->duty_date)->format('Y-m-d'),
                    'exam_session_id' => $r->exam_session_id,
                    'session' => $r->examSession ? [
                        'id' => $r->examSession->id,
                        'code' => $r->examSession->code,
                        'label' => $r->examSession->label,
                    ] : null,
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
            'roster' => [
                'id' => $roster->id,
                'version' => $roster->version,
                'status' => $roster->status,
                'submitted_at' => $roster->submitted_at,
                'approved_at' => $roster->approved_at,
                'published_at' => $roster->published_at,
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
            'status' => ['prohibited'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = ExamSubject::where('exam_id', $exam->id)
            ->where('id', $validated['exam_subject_id'])
            ->firstOrFail();

        $role = ExamDutyRole::where('institution_id', $institutionId)
            ->where('id', $validated['duty_role_id'])
            ->where('active', true)
            ->firstOrFail();

        $this->assertStaffInInstitution(
            $request,
            (int) $validated['user_id']
        );

        $assignment = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam,
            $entry,
            $role,
            $validated
        ) {
            // Lock the latest roster before checking its status.
            $roster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            // Recheck draft status while holding the lock.
            $this->ensureDraftRoster($roster);

            $existingActiveAssignment = ExamDutyAssignment::where(
                'institution_id',
                $institutionId
            )
                ->where('roster_id', $roster->id)
                ->where('exam_subject_id', $entry->id)
                ->where('user_id', (int) $validated['user_id'])
                ->whereIn('status', ['assigned', 'accepted'])
                ->lockForUpdate()
                ->exists();

            abort_if(
                $existingActiveAssignment,
                409,
                'This staff member already has an active duty assignment for this examination subject.'
            );

            $assignment = ExamDutyAssignment::create([
                'institution_id' => $institutionId,
                'exam_subject_id' => $entry->id,
                'roster_id' => $roster->id,
                'user_id' => (int) $validated['user_id'],
                'duty_role_id' => $role->id,
                'status' => 'assigned',
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'responded_at' => null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $assignment->load([
                'user:id,name,email',
                'dutyRole:id,code,name,duty_weight',
            ]);

            $this->audit->record(
                $request,
                'exam.duty_assigned',
                'examinations',
                $assignment,
                [],
                $assignment->toArray()
            );

            return $assignment;
        });

        return response()->json([
            'message' => 'Duty assigned.',
            'assignment' => $assignment,
        ], 201);
    }

    public function updateAssignment(Request $request, int $examId, int $id)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $validated = $request->validate([
            'duty_role_id' => ['nullable', 'integer'],
            'status' => ['prohibited'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $assignment = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam,
            $examId,
            $id,
            $validated
        ) {
            // Lock and validate the latest roster version.
            $roster = $this->latestRoster($institutionId, $exam->id, true);
            $this->ensureDraftRoster($roster);

            // Only assignments belonging to the latest roster can be modified.
            $assignment = ExamDutyAssignment::where('institution_id', $institutionId)
                ->where('roster_id', $roster->id)
                ->whereHas('examSubject', fn($q) => $q->where('exam_id', $examId))
                ->lockForUpdate()
                ->findOrFail($id);

            $before = $assignment->toArray();

            if (!empty($validated['duty_role_id'])) {
                $role = ExamDutyRole::where('institution_id', $institutionId)
                    ->where('id', $validated['duty_role_id'])
                    ->where('active', true)
                    ->firstOrFail();

                $assignment->duty_role_id = $role->id;
            }

            if (array_key_exists('remarks', $validated)) {
                $assignment->remarks = $validated['remarks'];
            }

            $assignment->save();

            $assignment->load([
                'user:id,name,email',
                'dutyRole:id,code,name,duty_weight',
            ]);

            $this->audit->record(
                $request,
                'exam.duty_assignment_updated',
                'examinations',
                $assignment,
                $before,
                $assignment->toArray()
            );

            return $assignment;
        });

        return response()->json([
            'message' => 'Duty assignment updated.',
            'assignment' => $assignment,
        ]);
    }

    public function respondToAssignment(Request $request, int $examId, int $id)
    {
        $validated = $request->validate([
            'response' => ['required', Rule::in(['accepted', 'declined'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $institutionId = $request->user()->institution_id;
        $userId = $request->user()->id;

        // Verify that the examination belongs to the authenticated user's institution.
        $exam = $this->findExam($institutionId, $examId);

        $assignment = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam,
            $examId,
            $id,
            $userId,
            $validated
        ) {
            // Lock the latest roster to prevent concurrent lifecycle changes.
            $latestRoster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            // Staff can respond only to assignments in the latest published roster.
            abort_unless(
                $latestRoster->status === 'published',
                409,
                'Staff responses are allowed only for the currently published duty roster.'
            );

            // Restrict the assignment to this staff member, institution and latest roster.
            $assignment = ExamDutyAssignment::where('institution_id', $institutionId)
                ->where('roster_id', $latestRoster->id)
                ->where('user_id', $userId)
                ->whereHas('examSubject', fn($q) => $q->where('exam_id', $examId))
                ->lockForUpdate()
                ->findOrFail($id);

            // Only an assignment awaiting a response can be accepted or declined.
            abort_unless(
                $assignment->status === 'assigned',
                409,
                'This duty assignment has already been responded to or is no longer active.'
            );

            $before = $assignment->toArray();

            $assignment->status = $validated['response'];
            $assignment->responded_at = now();

            if (array_key_exists('remarks', $validated)) {
                $assignment->remarks = $validated['remarks'];
            }

            $assignment->save();

            $assignment->load([
                'user:id,name,email',
                'dutyRole:id,code,name,duty_weight',
            ]);

            // Keep the legacy invigilation table synchronized.
            // Staff responses affect the current published roster, so update
            // only this assignment's legacy projection.
            $this->legacySync->syncPublishedAssignment(
                $latestRoster,
                $assignment
            );

            $this->audit->record(
                $request,
                $assignment->status === 'accepted'
                    ? 'exam.duty_accepted'
                    : 'exam.duty_declined',
                'examinations',
                $assignment,
                $before,
                $assignment->toArray()
            );

            return $assignment;
        });

        return response()->json([
            'message' => $assignment->status === 'accepted'
                ? 'Duty assignment accepted successfully.'
                : 'Duty assignment declined successfully.',
            'assignment' => $assignment,
        ]);
    }

    public function destroyAssignment(Request $request, int $examId, int $id)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        DB::transaction(function () use (
            $request,
            $institutionId,
            $exam,
            $examId,
            $id
        ) {
            // Lock and validate the latest roster version.
            $roster = $this->latestRoster($institutionId, $exam->id, true);
            $this->ensureDraftRoster($roster);

            // Only assignments belonging to the latest roster can be removed.
            $assignment = ExamDutyAssignment::where('institution_id', $institutionId)
                ->where('roster_id', $roster->id)
                ->whereHas('examSubject', fn($q) => $q->where('exam_id', $examId))
                ->lockForUpdate()
                ->findOrFail($id);

            $before = $assignment->toArray();

            $assignment->delete();

            $this->audit->record(
                $request,
                'exam.duty_assignment_removed',
                'examinations',
                null,
                $before,
                ['id' => $id, 'deleted' => true]
            );
        });

        return response()->json([
            'message' => 'Duty assignment removed.',
        ]);
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

        $role = ExamDutyRole::where('institution_id', $institutionId)
            ->where('id', $validated['duty_role_id'])
            ->where('active', true)
            ->firstOrFail();

        $required = (int) ($validated['required_per_slot'] ?? 1);
        $maxPerStaff = isset($validated['max_per_staff'])
            ? (int) $validated['max_per_staff']
            : null;
        $replaceDeclined = (bool) ($validated['replace_declined'] ?? false);

        $subjects = ExamSubject::where('exam_id', $exam->id)
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $subjectIds = $subjects->pluck('id')->all();

        $staffIds = collect($validated['staff_user_ids'] ?? []);

        if ($staffIds->isEmpty()) {
            $staffIds = DB::table('user_role_assignments')
                ->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
                ->where('user_role_assignments.institution_id', $institutionId)
                ->where('user_role_assignments.status', 'active')
                ->whereIn('roles.code', [
                    'faculty',
                    'hod',
                    'dean',
                    'registrar',
                    'examination_officer',
                    'institution_admin',
                ])
                ->pluck('user_role_assignments.user_id')
                ->unique()
                ->values();
        }

        $workloadSubjectIds = $this->workloadSubjectIds($institutionId, $exam);
        $workloadRosterIds = $this->workloadRosterIds($institutionId, $exam);

        $workload = ExamDutyAssignment::where(
            'exam_duty_assignments.institution_id',
            $institutionId
        )
            ->whereIn('exam_duty_assignments.exam_subject_id', $workloadSubjectIds)
            ->whereIn('exam_duty_assignments.roster_id', $workloadRosterIds)
            ->whereNotIn('exam_duty_assignments.status', ['declined', 'replaced'])
            ->join('exam_duty_roles', function ($join) use ($institutionId) {
                $join->on(
                    'exam_duty_assignments.duty_role_id',
                    '=',
                    'exam_duty_roles.id'
                )->where(
                    'exam_duty_roles.institution_id',
                    '=',
                    $institutionId
                );
            })
            ->selectRaw(
                'exam_duty_assignments.user_id,
         SUM(exam_duty_roles.duty_weight) as weight_sum,
         COUNT(*) as duty_count'
            )
            ->groupBy('exam_duty_assignments.user_id')
            ->get()
            ->keyBy('user_id');

        $unavailable = ExamDutyAvailability::where('institution_id', $institutionId)
            ->where('exam_id', $exam->id)
            ->where('available', false)
            ->get();

        $isUnavailable = function (int $userId, $subject) use ($unavailable): bool {
            foreach ($unavailable as $entry) {
                if ((int) $entry->user_id !== $userId) {
                    continue;
                }

                if (
                    $entry->duty_date
                    && optional($subject->exam_date)->format('Y-m-d')
                    !== optional($entry->duty_date)->format('Y-m-d')
                ) {
                    continue;
                }

                if (
                    $entry->exam_session_id
                    && (int) $entry->exam_session_id
                    !== (int) $subject->exam_session_id
                ) {
                    continue;
                }

                return true;
            }

            return false;
        };

        $created = 0;
        $skipped = 0;
        $replacementLinks = [];

        DB::transaction(function () use (
            $subjects,
            $staffIds,
            $role,
            $required,
            $maxPerStaff,
            $replaceDeclined,
            $institutionId,
            $exam,
            $request,
            &$created,
            &$skipped,
            &$workload,
            &$replacementLinks,
            $isUnavailable
        ) {
            // Lock and validate the latest roster version before modifying assignments.
            $roster = $this->latestRoster($institutionId, $exam->id, true);
            $this->ensureDraftRoster($roster);
            foreach ($subjects as $subject) {
                // Lock the existing assignment history for this subject.
                $existing = ExamDutyAssignment::where('institution_id', $institutionId)
                    ->where('roster_id', $roster->id)
                    ->where('exam_subject_id', $subject->id)
                    ->lockForUpdate()
                    ->get();

                $activeForRole = $existing->filter(
                    fn($assignment) =>
                    (int) $assignment->duty_role_id === (int) $role->id
                        && !in_array(
                            $assignment->status,
                            ['declined', 'replaced'],
                            true
                        )
                )->count();

                $need = max(0, $required - $activeForRole);

                if ($need <= 0) {
                    continue;
                }

                // A staff member with an active assignment cannot receive
                // another duty for the same subject.
                $assignedUserIds = $existing
                    ->whereNotIn('status', ['declined', 'replaced'])
                    ->pluck('user_id')
                    ->map(fn($id) => (int) $id)
                    ->all();

                // Do not automatically assign the same duty again to a
                // staff member who previously declined it.
                $declinedUserIds = $existing
                    ->filter(
                        fn($assignment) =>
                        (int) $assignment->duty_role_id === (int) $role->id
                            && $assignment->status === 'declined'
                    )
                    ->pluck('user_id')
                    ->map(fn($id) => (int) $id)
                    ->all();

                $candidates = $staffIds
                    ->map(fn($id) => (int) $id)
                    ->unique()
                    ->filter(function ($userId) use (
                        $assignedUserIds,
                        $declinedUserIds,
                        $isUnavailable,
                        $subject,
                        $workload,
                        $maxPerStaff
                    ) {
                        if (in_array($userId, $assignedUserIds, true)) {
                            return false;
                        }

                        if (in_array($userId, $declinedUserIds, true)) {
                            return false;
                        }

                        if ($isUnavailable($userId, $subject)) {
                            return false;
                        }

                        $count = (int) ($workload[$userId]->duty_count ?? 0);

                        if ($maxPerStaff !== null && $count >= $maxPerStaff) {
                            return false;
                        }

                        return true;
                    })
                    ->sortBy(function ($userId) use ($workload) {
                        $current = $workload[$userId] ?? null;

                        return [
                            (int) ($current->weight_sum ?? 0),
                            (int) ($current->duty_count ?? 0),
                            (int) $userId,
                        ];
                    })
                    ->values();

                foreach ($candidates->take($need) as $userId) {
                    // Always create a new row. Never overwrite assignment history.
                    $assignment = ExamDutyAssignment::create([
                        'institution_id' => $institutionId,
                        'exam_subject_id' => $subject->id,
                        'roster_id' => $roster->id, // Add this line
                        'user_id' => $userId,
                        'duty_role_id' => $role->id,
                        'status' => 'assigned',
                        'assigned_by' => $request->user()->id,
                        'assigned_at' => now(),
                        'responded_at' => null,
                        'remarks' => null,
                    ]);

                    // If replacement linking was requested, link this new
                    // assignment to the earliest unlinked declined record
                    // for the same subject and duty role.
                    if ($replaceDeclined) {
                        $declined = $existing->first(
                            fn($old) =>
                            (int) $old->duty_role_id === (int) $role->id
                                && $old->status === 'declined'
                                && $old->replaced_by_assignment_id === null
                        );

                        if ($declined) {
                            $declined->status = 'replaced';
                            $declined->replaced_by_assignment_id = $assignment->id;
                            $declined->save();

                            $replacementLinks[] = [
                                'original_assignment_id' => $declined->id,
                                'replacement_assignment_id' => $assignment->id,
                            ];
                        }
                    }

                    $existing->push($assignment);

                    $current = $workload[$userId] ?? (object) [
                        'weight_sum' => 0,
                        'duty_count' => 0,
                    ];

                    $workload[$userId] = (object) [
                        'user_id' => $userId,
                        'weight_sum' => (int) $current->weight_sum
                            + (int) $role->duty_weight,
                        'duty_count' => (int) $current->duty_count + 1,
                    ];

                    $created++;
                }

                $assignedCount = min($candidates->count(), $need);

                if ($assignedCount < $need) {
                    $skipped += $need - $assignedCount;
                }
            }

            $this->audit->record(
                $request,
                'exam.duty_roster_auto_assigned',
                'examinations',
                $exam,
                [],
                [
                    'duty_role_id' => $role->id,
                    'required_per_slot' => $required,
                    'created' => $created,
                    'skipped_unfilled' => $skipped,
                    'replacement_links' => $replacementLinks,
                ]
            );
        });

        return response()->json([
            'message' => 'Auto-assignment completed.',
            'created' => $created,
            'skipped_unfilled' => $skipped,
            'replacement_links' => $replacementLinks,
        ]);
    }

    private function workloadSubjectIds(int $institutionId, Exam $exam)
    {
        $eligibleExams = Exam::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', ['scheduled', 'published']);

        if ($exam->academic_session_id === null) {
            // Do not group unrelated examinations with a null session.
            $eligibleExams->where('id', $exam->id);
        } else {
            $eligibleExams->where(
                'academic_session_id',
                $exam->academic_session_id
            );
        }

        return ExamSubject::query()
            ->where('institution_id', $institutionId)
            ->whereIn('exam_id', $eligibleExams->select('id'))
            ->select('id');
    }

    private function workloadRosterIds(int $institutionId, Exam $exam)
    {
        $eligibleExams = Exam::query()
            ->where('institution_id', $institutionId)
            ->whereIn('status', ['scheduled', 'published']);

        if ($exam->academic_session_id === null) {
            $eligibleExams->where('id', $exam->id);
        } else {
            $eligibleExams->where(
                'academic_session_id',
                $exam->academic_session_id
            );
        }

        return ExamDutyRoster::query()
            ->select('exam_duty_rosters.id')
            ->where('exam_duty_rosters.institution_id', $institutionId)
            ->whereIn('exam_duty_rosters.exam_id', $eligibleExams->select('id'))
            ->whereRaw('
            exam_duty_rosters.version = (
                SELECT MAX(previous_roster.version)
                FROM exam_duty_rosters AS previous_roster
                WHERE previous_roster.institution_id =
                    exam_duty_rosters.institution_id
                  AND previous_roster.exam_id =
                    exam_duty_rosters.exam_id
            )
        ');
    }

    public function printPayload(Request $request, int $examId)
    {
        $this->ensureView($request);
        $roster = $this->roster($request, $examId);
        $data = $roster->getData(true);
        $data['generated_at'] = now()->toIso8601String();
        return response()->json($data);
    }

    private function workloadSummary(int $institutionId, Exam $exam): array
    {
        $subjectIds = $this->workloadSubjectIds($institutionId, $exam);
        $rosterIds = $this->workloadRosterIds($institutionId, $exam);

        $rows = ExamDutyAssignment::where('institution_id', $institutionId)
            ->whereIn('exam_subject_id', $subjectIds)
            ->whereIn('roster_id', $rosterIds) // Add this condition
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
        usort($byUser, fn($a, $b) => [$b['weighted_load'], $b['duty_count']] <=> [$a['weighted_load'], $a['duty_count']]);
        return array_values($byUser);
    }
}
