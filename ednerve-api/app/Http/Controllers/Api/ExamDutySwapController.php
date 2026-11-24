<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyRoster;
use App\Models\ExamDutySwapRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExamDutySwapController extends Controller
{
    public function __construct(
        private readonly AuditService $audit
    ) {}

    private function ensureOfficer(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->isInstitutionAdmin()
                || $user->hasRole('examination_officer'),
            403,
            'Only an institution administrator or examination officer can perform this action.'
        );
    }

    private function findExam(int $institutionId, int $examId): Exam
    {
        return Exam::where('institution_id', $institutionId)
            ->findOrFail($examId);
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

    private function findSwap(
        int $institutionId,
        int $examId,
        int $swapId,
        bool $lock = false
    ): ExamDutySwapRequest {
        $query = ExamDutySwapRequest::where('institution_id', $institutionId)
            ->whereHas('roster', function ($q) use ($examId) {
                $q->where('exam_id', $examId);
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($swapId);
    }

    private function assertNoPendingSwap(
        int $institutionId,
        int $firstAssignmentId,
        int $secondAssignmentId
    ): void {
        $conflict = ExamDutySwapRequest::where(
            'institution_id',
            $institutionId
        )
            ->whereIn('status', ExamDutySwapRequest::PENDING_STATUSES)
            ->where(function ($q) use (
                $firstAssignmentId,
                $secondAssignmentId
            ) {
                $q->whereIn('first_assignment_id', [
                    $firstAssignmentId,
                    $secondAssignmentId,
                ])->orWhereIn('second_assignment_id', [
                    $firstAssignmentId,
                    $secondAssignmentId,
                ]);
            })
            ->exists();

        abort_if(
            $conflict,
            409,
            'One or both assignments already participate in a pending swap request.'
        );
    }

    private function assignmentForRoster(
        int $institutionId,
        int $rosterId,
        int $assignmentId,
        bool $lock = false
    ): ExamDutyAssignment {
        $query = ExamDutyAssignment::where('institution_id', $institutionId)
            ->where('roster_id', $rosterId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($assignmentId);
    }

    private function assertEligibleStaff(int $institutionId, int $userId): void
    {
        $eligible = DB::table('user_role_assignments')
            ->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
            ->where('user_role_assignments.institution_id', $institutionId)
            ->where('user_role_assignments.user_id', $userId)
            ->where('user_role_assignments.status', 'active')
            ->whereIn('roles.code', [
                'faculty',
                'hod',
                'dean',
                'registrar',
                'examination_officer',
                'institution_admin',
            ])
            ->exists();

        abort_unless(
            $eligible,
            409,
            'A participating staff member is no longer eligible in this institution.'
        );
    }

    /**
     * Staff A initiates a swap and explicitly confirms consent.
     */
    public function initiate(Request $request, int $examId)
    {
        $validated = $request->validate([
            'first_assignment_id' => [
                'required',
                'integer',
                'different:second_assignment_id',
            ],
            'second_assignment_id' => [
                'required',
                'integer',
                'different:first_assignment_id',
            ],
            'confirm_consent' => [
                'required',
                'accepted',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $institutionId = (int) $request->user()->institution_id;
        $userId = (int) $request->user()->id;

        $exam = $this->findExam($institutionId, $examId);

        $swap = DB::transaction(function () use (
            $request,
            $institutionId,
            $userId,
            $exam,
            $validated
        ) {
            $roster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            abort_unless(
                $roster->status === 'published',
                409,
                'Duty swaps can only be initiated against the current published roster.'
            );

            $first = $this->assignmentForRoster(
                $institutionId,
                $roster->id,
                (int) $validated['first_assignment_id'],
                true
            );

            $second = $this->assignmentForRoster(
                $institutionId,
                $roster->id,
                (int) $validated['second_assignment_id'],
                true
            );

            abort_unless(
                (int) $first->user_id === $userId,
                403,
                'You can initiate a swap only for your own duty assignment.'
            );

            abort_unless(
                (int) $second->user_id !== $userId,
                422,
                'You cannot swap an assignment with yourself.'
            );

            abort_unless(
                $first->status === 'accepted'
                    && $second->status === 'accepted',
                409,
                'Both staff members must have accepted their current assignments before a swap can be requested.'
            );

            $this->assertNoPendingSwap(
                $institutionId,
                $first->id,
                $second->id
            );

            $swap = ExamDutySwapRequest::create([
                'institution_id' => $institutionId,
                'roster_id' => $roster->id,
                'first_assignment_id' => $first->id,
                'second_assignment_id' => $second->id,
                'initiated_by' => $userId,
                'target_user_id' => $second->user_id,
                'initiator_accepted_at' => now(),
                'status' => ExamDutySwapRequest::STATUS_PENDING_TARGET,
            ]);

            $this->audit->record(
                $request,
                'exam.duty_swap_initiated',
                'examinations',
                $swap,
                [],
                [
                    'swap' => $swap->toArray(),
                    'remarks' => $validated['remarks'] ?? null,
                ]
            );

            return $swap;
        }, 3);

        return response()->json([
            'message' => 'Swap request submitted for the other staff member’s consent.',
            'swap' => $this->present($swap->fresh()),
        ], 201);
    }

    /**
     * Staff B accepts or declines the proposed swap.
     */
    public function respond(
        Request $request,
        int $examId,
        int $swapId
    ) {
        $validated = $request->validate([
            'response' => [
                'required',
                Rule::in(['accepted', 'declined']),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $institutionId = (int) $request->user()->institution_id;
        $userId = (int) $request->user()->id;

        $exam = $this->findExam($institutionId, $examId);

        $result = DB::transaction(function () use (
            $request,
            $institutionId,
            $userId,
            $exam,
            $swapId,
            $validated
        ) {
            $roster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            $swap = $this->findSwap(
                $institutionId,
                $exam->id,
                $swapId,
                true
            );

            abort_unless(
                (int) $swap->roster_id === (int) $roster->id
                    && $roster->status === 'published',
                409,
                'This swap no longer belongs to the current published roster.'
            );

            abort_unless(
                (int) $swap->target_user_id === $userId,
                403,
                'Only the invited staff member can respond to this request.'
            );

            abort_unless(
                $swap->status === ExamDutySwapRequest::STATUS_PENDING_TARGET,
                409,
                'This swap request is no longer awaiting your response.'
            );

            $before = $swap->toArray();

            $swap->target_responded_at = now();
            $swap->target_response_reason = $validated['reason'] ?? null;

            if ($validated['response'] === 'accepted') {
                $swap->target_accepted_at = now();
                $swap->status = ExamDutySwapRequest::STATUS_PENDING_OFFICER;
                $event = 'exam.duty_swap_accepted';
                $message = 'Both staff members have consented. The request is now awaiting Examination Officer approval.';
            } else {
                // A staff decline is not an Examination Officer decision.
                $swap->status = ExamDutySwapRequest::STATUS_REJECTED;
                $swap->decided_by = null;
                $swap->decided_at = null;
                $swap->decision_reason = null;
                $event = 'exam.duty_swap_declined';
                $message = 'The invited staff member declined the swap.';
            }

            $swap->save();

            $this->audit->record(
                $request,
                $event,
                'examinations',
                $swap,
                $before,
                $swap->toArray()
            );

            return [
                'swap' => $swap,
                'message' => $message,
            ];
        }, 3);

        return response()->json([
            'message' => $result['message'],
            'swap' => $this->present($result['swap']->fresh()),
        ]);
    }

    /**
     * List swap requests for an examination.
     */
    public function index(Request $request, int $examId)
    {
        $institutionId = (int) $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $query = ExamDutySwapRequest::where('institution_id', $institutionId)
            ->whereHas('roster', fn($q) => $q->where('exam_id', $exam->id))
            ->with([
                'initiator:id,name,email',
                'targetUser:id,name,email',
                'firstAssignment.user:id,name',
                'secondAssignment.user:id,name',
                'roster:id,exam_id,version,status',
            ])
            ->orderByDesc('id');

        $user = $request->user();

        if (!($user->isInstitutionAdmin()
            || $user->hasRole('examination_officer')
            || $user->hasRole('registrar'))) {
            $query->where(function ($q) use ($user) {
                $q->where('initiated_by', $user->id)
                    ->orWhere('target_user_id', $user->id);
            });
        }

        return response()->json([
            'swaps' => $query->get()->map(
                fn($swap) => $this->present($swap)
            )->values(),
        ]);
    }

    /**
     * View one swap request.
     */
    public function show(
        Request $request,
        int $examId,
        int $swapId
    ) {
        $institutionId = (int) $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $swap = $this->findSwap(
            $institutionId,
            $exam->id,
            $swapId
        )->load([
            'initiator:id,name,email',
            'targetUser:id,name,email',
            'firstAssignment.user:id,name,email',
            'firstAssignment.dutyRole:id,code,name',
            'secondAssignment.user:id,name,email',
            'secondAssignment.dutyRole:id,code,name',
            'roster:id,exam_id,version,status',
            'decidedBy:id,name',
            'withdrawnBy:id,name',
        ]);

        $user = $request->user();

        abort_unless(
            $user->isInstitutionAdmin()
                || $user->hasRole('examination_officer')
                || $user->hasRole('registrar')
                || (int) $swap->initiated_by === (int) $user->id
                || (int) $swap->target_user_id === (int) $user->id,
            403,
            'You are not allowed to view this swap request.'
        );

        return response()->json([
            'swap' => $this->present($swap),
        ]);
    }

    /**
     * Examination Officer approves the swap.
     * Approval creates a new draft roster version.
     */

    public function approve(
        Request $request,
        int $examId,
        int $swapId
    ) {
        $this->ensureOfficer($request);

        $institutionId = (int) $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $result = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam,
            $swapId
        ) {
            // Lock the latest roster to prevent concurrent lifecycle changes.
            $previousRoster = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            // Lock the swap request.
            $swap = $this->findSwap(
                $institutionId,
                $exam->id,
                $swapId,
                true
            );

            // Both staff members must have completed the consent process.
            abort_unless(
                $swap->status === ExamDutySwapRequest::STATUS_PENDING_OFFICER,
                409,
                'Only a swap request with both staff consents can be approved.'
            );

            // Explicitly verify both consent timestamps.
            abort_unless(
                $swap->initiator_accepted_at !== null
                    && $swap->target_accepted_at !== null,
                409,
                'Both staff members must explicitly consent before officer approval.'
            );

            // The request must belong to the latest published roster.
            abort_unless(
                (int) $swap->roster_id === (int) $previousRoster->id
                    && $previousRoster->status === 'published',
                409,
                'The roster has changed. This swap request can no longer be approved.'
            );

            $officerId = (int) $request->user()->id;

            // Neither participant may approve their own swap.
            abort_unless(
                !in_array($officerId, [
                    (int) $swap->initiated_by,
                    (int) $swap->target_user_id,
                ], true),
                403,
                'A participant in the swap cannot approve the request.'
            );

            // Lock and retrieve both original assignments.
            $first = $this->assignmentForRoster(
                $institutionId,
                $previousRoster->id,
                (int) $swap->first_assignment_id,
                true
            );

            $second = $this->assignmentForRoster(
                $institutionId,
                $previousRoster->id,
                (int) $swap->second_assignment_id,
                true
            );

            // Both original assignments must remain accepted.
            abort_unless(
                $first->status === 'accepted'
                    && $second->status === 'accepted',
                409,
                'Both original assignments must remain accepted.'
            );

            // Verify that the original assignment owners have not changed.
            abort_unless(
                (int) $first->user_id === (int) $swap->initiated_by
                    && (int) $second->user_id === (int) $swap->target_user_id,
                409,
                'The assignment owners have changed. This swap cannot be approved.'
            );

            // Revalidate both staff members.
            $this->assertEligibleStaff(
                $institutionId,
                (int) $first->user_id
            );

            $this->assertEligibleStaff(
                $institutionId,
                (int) $second->user_id
            );

            // Ensure neither assignment is involved in another pending swap.
            $this->assertNoOtherPendingSwap(
                $institutionId,
                $swap
            );

            // Create the next roster version as a draft.
            $newRoster = ExamDutyRoster::create([
                'institution_id' => $institutionId,
                'exam_id' => $exam->id,
                'version' => ((int) $previousRoster->version) + 1,
                'status' => 'draft',
            ]);

            // Copy active assignments from the previous published roster.
            $assignments = ExamDutyAssignment::where(
                'institution_id',
                $institutionId
            )
                ->where('roster_id', $previousRoster->id)
                ->whereIn('status', ['assigned', 'accepted'])
                ->with('dutyRole')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $newFirst = null;
            $newSecond = null;

            foreach ($assignments as $old) {
                $isFirst = (int) $old->id === (int) $first->id;
                $isSecond = (int) $old->id === (int) $second->id;

                // Default versioning policy:
                // Existing assignments require fresh staff acceptance.
                $newUserId = $old->user_id;
                $newStatus = 'assigned';
                $respondedAt = null;
                $remarks = null;

                if ($isFirst) {
                    // First duty moves to the target staff member.
                    $newUserId = $second->user_id;
                    $newStatus = 'accepted';
                    $respondedAt = $swap->target_accepted_at;
                    $remarks = 'Accepted through approved duty swap.';
                } elseif ($isSecond) {
                    // Second duty moves to the initiating staff member.
                    $newUserId = $first->user_id;
                    $newStatus = 'accepted';
                    $respondedAt = $swap->initiator_accepted_at;
                    $remarks = 'Accepted through approved duty swap.';
                }

                $newAssignment = ExamDutyAssignment::create([
                    'institution_id' => $institutionId,
                    'roster_id' => $newRoster->id,
                    'exam_subject_id' => $old->exam_subject_id,
                    'user_id' => $newUserId,
                    'duty_role_id' => $old->duty_role_id,
                    'status' => $newStatus,
                    'assigned_by' => $officerId,
                    'assigned_at' => now(),
                    'responded_at' => $respondedAt,
                    'remarks' => $remarks,
                    'replaced_by_assignment_id' => null,
                ]);

                // Do not synchronize exam_invigilations here.
                // This roster is still a draft. The legacy projection should
                // be refreshed when this roster is eventually published.

                if ($isFirst) {
                    $newFirst = $newAssignment;
                } elseif ($isSecond) {
                    $newSecond = $newAssignment;
                }
            }

            // Ensure both swapped assignments were copied successfully.
            abort_unless(
                $newFirst && $newSecond,
                500,
                'The new roster could not be created with both swapped assignments.'
            );

            // Mark the swap request as approved.
            $before = $swap->toArray();

            $swap->status = ExamDutySwapRequest::STATUS_APPROVED;
            $swap->decided_by = $officerId;
            $swap->decided_at = now();
            $swap->decision_reason = 'Approved. New roster version created.';
            $swap->save();

            // Audit the new roster version.
            $this->audit->record(
                $request,
                'exam.duty_roster_version_created',
                'examinations',
                $newRoster,
                [
                    'previous_roster_id' => $previousRoster->id,
                    'previous_version' => $previousRoster->version,
                ],
                [
                    'new_roster_id' => $newRoster->id,
                    'new_version' => $newRoster->version,
                    'swap_request_id' => $swap->id,
                    'swapped_assignment_ids' => [
                        $newFirst->id,
                        $newSecond->id,
                    ],
                ]
            );

            // Audit the swap approval.
            $this->audit->record(
                $request,
                'exam.duty_swap_approved',
                'examinations',
                $swap,
                $before,
                $swap->toArray()
            );

            return [
                'swap' => $swap,
                'roster' => $newRoster->loadCount('assignments'),
            ];
        }, 3);

        return response()->json([
            'message' => 'Swap approved. A new draft roster version has been created.',
            'swap' => $this->present($result['swap']->fresh()),
            'roster' => $result['roster'],
            'copied_assignments' => $result['roster']->assignments_count,
        ]);
    }

    /**
     * Examination Officer rejects a mutually accepted swap.
     */
    public function reject(
        Request $request,
        int $examId,
        int $swapId
    ) {
        $this->ensureOfficer($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $institutionId = (int) $request->user()->institution_id;
        $exam = $this->findExam($institutionId, $examId);

        $swap = DB::transaction(function () use (
            $request,
            $institutionId,
            $exam,
            $swapId,
            $validated
        ) {
            $latest = $this->latestRoster(
                $institutionId,
                $exam->id,
                true
            );

            $swap = $this->findSwap(
                $institutionId,
                $exam->id,
                $swapId,
                true
            );

            abort_unless(
                $swap->status === ExamDutySwapRequest::STATUS_PENDING_OFFICER,
                409,
                'Only a mutually accepted swap can be rejected by the officer.'
            );

            abort_unless(
                (int) $swap->roster_id === (int) $latest->id
                    && $latest->status === 'published',
                409,
                'This swap does not belong to the current published roster.'
            );

            $officerId = (int) $request->user()->id;

            abort_unless(
                !in_array($officerId, [
                    (int) $swap->initiated_by,
                    (int) $swap->target_user_id,
                ], true),
                403,
                'A participant in the swap cannot reject the request as officer.'
            );

            $before = $swap->toArray();

            $swap->status = ExamDutySwapRequest::STATUS_REJECTED;
            $swap->decided_by = $officerId;
            $swap->decided_at = now();
            $swap->decision_reason = $validated['reason'];
            $swap->save();

            $this->audit->record(
                $request,
                'exam.duty_swap_rejected',
                'examinations',
                $swap,
                $before,
                $swap->toArray()
            );

            return $swap;
        }, 3);

        return response()->json([
            'message' => 'Swap request rejected by the Examination Officer.',
            'swap' => $this->present($swap->fresh()),
        ]);
    }

    /**
     * Either participant can withdraw before officer decision.
     */
    public function withdraw(
        Request $request,
        int $examId,
        int $swapId
    ) {
        $institutionId = (int) $request->user()->institution_id;
        $userId = (int) $request->user()->id;

        $exam = $this->findExam($institutionId, $examId);

        $result = DB::transaction(function () use (
            $request,
            $institutionId,
            $userId,
            $exam,
            $swapId
        ) {
            $latest = $this->latestRoster($institutionId, $exam->id, true);

            $swap = $this->findSwap(
                $institutionId,
                $exam->id,
                $swapId,
                true
            );

            abort_unless(
                (int) $swap->roster_id === (int) $latest->id
                    && $latest->status === 'published',
                409,
                'This swap no longer belongs to the current published roster.'
            );

            abort_unless(
                in_array($userId, [
                    (int) $swap->initiated_by,
                    (int) $swap->target_user_id,
                ], true),
                403,
                'Only a participant can withdraw this swap request.'
            );

            abort_unless(
                in_array($swap->status, ExamDutySwapRequest::PENDING_STATUSES, true),
                409,
                'Only a pending swap request can be withdrawn.'
            );

            $before = $swap->toArray();

            $swap->status = ExamDutySwapRequest::STATUS_WITHDRAWN;
            $swap->withdrawn_by = $userId;
            $swap->withdrawn_at = now();
            $swap->save();

            $this->audit->record(
                $request,
                'exam.duty_swap_withdrawn',
                'examinations',
                $swap,
                $before,
                $swap->toArray()
            );

            return $swap;
        }, 3);

        return response()->json([
            'message' => 'Swap request withdrawn.',
            'swap' => $this->present($result->fresh()),
        ]);
    }

    private function assertNoOtherPendingSwap(
        int $institutionId,
        ExamDutySwapRequest $current
    ): void {
        $conflict = ExamDutySwapRequest::where(
            'institution_id',
            $institutionId
        )
            ->where('id', '!=', $current->id)
            ->whereIn('status', ExamDutySwapRequest::PENDING_STATUSES)
            ->where(function ($q) use ($current) {
                $ids = [
                    $current->first_assignment_id,
                    $current->second_assignment_id,
                ];

                $q->whereIn('first_assignment_id', $ids)
                    ->orWhereIn('second_assignment_id', $ids);
            })
            ->exists();

        abort_if(
            $conflict,
            409,
            'One of these assignments is already involved in another pending swap.'
        );
    }

    private function present(ExamDutySwapRequest $swap): array
    {
        $swap->loadMissing([
            'initiator:id,name,email',
            'targetUser:id,name,email',
            'firstAssignment.user:id,name,email',
            'firstAssignment.dutyRole:id,code,name',
            'secondAssignment.user:id,name,email',
            'secondAssignment.dutyRole:id,code,name',
            'roster:id,exam_id,version,status',
            'decidedBy:id,name',
            'withdrawnBy:id,name',
        ]);

        return [
            'id' => $swap->id,
            'status' => $swap->status,
            'roster' => $swap->roster,
            'initiator' => $swap->initiator,
            'target_user' => $swap->targetUser,
            'first_assignment' => $swap->firstAssignment,
            'second_assignment' => $swap->secondAssignment,
            'initiator_accepted_at' => $swap->initiator_accepted_at,
            'target_accepted_at' => $swap->target_accepted_at,
            'target_responded_at' => $swap->target_responded_at,
            'target_response_reason' => $swap->target_response_reason,
            'decided_by' => $swap->decidedBy,
            'decided_at' => $swap->decided_at,
            'decision_reason' => $swap->decision_reason,
            'withdrawn_by' => $swap->withdrawnBy,
            'withdrawn_at' => $swap->withdrawn_at,
            'created_at' => $swap->created_at,
            'updated_at' => $swap->updated_at,
        ];
    }
}
