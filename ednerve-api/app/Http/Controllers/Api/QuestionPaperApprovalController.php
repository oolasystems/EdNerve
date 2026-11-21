<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperItem;
use App\Models\QuestionPaperApproval;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Question paper approval workflow (Phase B).
 *
 * draft -> prepared -> checked -> approved -> ratified (final)
 * SoD: the four actors must all be distinct people. Each transition is
 * recorded in question_paper_approvals and mirrored into the audit trail.
 * ratify() additionally requires mfa.privileged (route middleware).
 */
class QuestionPaperApprovalController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    private function ensureWorkflowAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->hasRole('examination_officer'),
            403,
            'Paper approvals are restricted to administrators and examination officers.'
        );
    }

    /** author submits the paper: draft -> prepared */
    public function submit(Request $request, int $id)
    {
        $this->ensureWorkflowAccess($request);

        $paper = $this->findPaper($request->user()->institution_id, $id);
        abort_unless($paper->status === QuestionPaper::STATUS_DRAFT, 422, 'Only draft papers can be submitted.');
        abort_if($paper->items()->count() === 0, 422, 'Attach at least one question before submitting.');

        $paper->update([
            'status' => QuestionPaper::STATUS_PREPARED,
            'prepared_by' => $request->user()->id,
            'prepared_at' => now(),
        ]);

        $this->logStage($request, $paper, QuestionPaperApproval::STAGE_PREPARED, 'approved', null);
        $this->audit->record($request, event: 'question_bank.paper_submitted', module: 'institution', subject: $paper, institutionId: $paper->institution_id);

        return response()->json(['message' => 'Paper submitted for checking.', 'paper' => $this->paperStamp($paper)]);
    }

    /** checker (independent): prepared -> checked */
    public function check(Request $request, int $id)
    {
        $this->ensureWorkflowAccess($request);

        $paper = $this->findPaper($request->user()->institution_id, $id);
        abort_unless($paper->status === QuestionPaper::STATUS_PREPARED, 422, 'Only submitted papers can be checked.');
        abort_if(
            $request->user()->id === $paper->prepared_by,
            403,
            'Separation of duties: the preparer cannot check their own paper.'
        );

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);

        $paper->update([
            'status' => QuestionPaper::STATUS_CHECKED,
            'checked_by' => $request->user()->id,
            'checked_at' => now(),
        ]);

        $this->logStage($request, $paper, QuestionPaperApproval::STAGE_CHECKED, 'approved', $validated['remarks'] ?? null);
        $this->audit->record($request, event: 'question_bank.paper_checked', module: 'institution', subject: $paper, institutionId: $paper->institution_id);

        return response()->json(['message' => 'Paper checked.', 'paper' => $this->paperStamp($paper)]);
    }

    /** approver (officer): checked -> approved */
    public function approve(Request $request, int $id)
    {
        $this->ensureWorkflowAccess($request);

        $paper = $this->findPaper($request->user()->institution_id, $id);
        abort_unless($paper->status === QuestionPaper::STATUS_CHECKED, 422, 'Only checked papers can be approved.');
        abort_if(
            in_array($request->user()->id, array_filter([$paper->prepared_by, $paper->checked_by]), true),
            403,
            'Separation of duties: the approver must be different from the preparer and the checker.'
        );

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);

        $paper->update([
            'status' => QuestionPaper::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->logStage($request, $paper, QuestionPaperApproval::STAGE_APPROVED, 'approved', $validated['remarks'] ?? null);
        $this->audit->record($request, event: 'question_bank.paper_approved', module: 'institution', subject: $paper, institutionId: $paper->institution_id);

        return response()->json(['message' => 'Paper approved.', 'paper' => $this->paperStamp($paper)]);
    }

    /** ratifier (final sign-off): approved -> ratified. Requires mfa.privileged. */
    public function ratify(Request $request, int $id)
    {
        $this->ensureWorkflowAccess($request);

        $institutionId = $request->user()->institution_id;
        $paper = $this->findPaper($institutionId, $id);
        abort_unless($paper->status === QuestionPaper::STATUS_APPROVED, 422, 'Only approved papers can be ratified.');
        abort_if(
            in_array($request->user()->id, array_filter([$paper->prepared_by, $paper->checked_by, $paper->approved_by]), true),
            403,
            'Separation of duties: the ratifier must differ from the preparer, checker and approver.'
        );

        // Marks consistency: paper total must equal the sum of effective item marks,
        // or be initialised from them when unset.
        $itemMarks = $this->effectiveTotal($paper);
        if ((float) $paper->total_marks > 0 && abs((float) $paper->total_marks - $itemMarks) > 0.01) {
            abort(422, sprintf(
                'Marks mismatch: paper declares %.2f but its questions add up to %.2f. Update total_marks or the questions before ratifying.',
                (float) $paper->total_marks,
                $itemMarks
            ));
        }

        DB::transaction(function () use ($paper, $request, $itemMarks) {
            $paper->update([
                'status' => QuestionPaper::STATUS_RATIFIED,
                'total_marks' => $itemMarks,
                'ratified_by' => $request->user()->id,
                'ratified_at' => now(),
            ]);
        });

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);
        $this->logStage($request, $paper, QuestionPaperApproval::STAGE_RATIFIED, 'approved', $validated['remarks'] ?? null);
        $this->audit->record($request, event: 'question_bank.paper_ratified', module: 'institution', subject: $paper, institutionId: $paper->institution_id);

        return response()->json(['message' => 'Paper ratified and finalized.', 'paper' => $this->paperStamp($paper)]);
    }

    /** send back for fixes: prepared|checked|approved -> draft, with mandatory reason */
    public function reject(Request $request, int $id)
    {
        $this->ensureWorkflowAccess($request);

        $paper = $this->findPaper($request->user()->institution_id, $id);
        abort_unless(
            in_array($paper->status, [QuestionPaper::STATUS_PREPARED, QuestionPaper::STATUS_CHECKED, QuestionPaper::STATUS_APPROVED], true),
            422,
            'Only submitted, checked or approved papers can be sent back.'
        );

        $validated = $request->validate(['remarks' => ['required', 'string', 'min:5', 'max:500']]);

        $stage = $paper->status;

        // Clean staged fields explicitly to keep behaviour obvious
        $paper->update(array_merge([
            'status' => QuestionPaper::STATUS_DRAFT,
        ], match ($stage) {
            QuestionPaper::STATUS_PREPARED => ['prepared_by' => null, 'prepared_at' => null],
            QuestionPaper::STATUS_CHECKED => ['checked_by' => null, 'checked_at' => null],
            default => ['approved_by' => null, 'approved_at' => null],
        }));

        $this->logStage($request, $paper, $stage, 'rejected', $validated['remarks']);
        $this->audit->record($request, event: 'question_bank.paper_rejected', module: 'institution', subject: $paper, description: $validated['remarks'], institutionId: $paper->institution_id);

        return response()->json(['message' => 'Paper sent back to draft.', 'paper' => $this->paperStamp($paper)]);
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------

    private function findPaper(int $institutionId, int $id): QuestionPaper
    {
        return QuestionPaper::where('institution_id', $institutionId)->findOrFail($id);
    }

    private function effectiveTotal(QuestionPaper $paper): float
    {
        return round((float) QuestionPaperItem::where('paper_id', $paper->id)
            ->with('question:id,marks')
            ->get()
            ->sum(fn ($item) => $item->marks_override ?? $item->question?->marks ?? 0), 2);
    }

    private function logStage(Request $request, QuestionPaper $paper, string $stage, string $decision, ?string $remarks): void
    {
        QuestionPaperApproval::create([
            'institution_id' => $paper->institution_id,
            'paper_id' => $paper->id,
            'stage' => $stage,
            'decision' => $decision,
            'actor_id' => $request->user()->id,
            'remarks' => $remarks,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paperStamp(QuestionPaper $paper): array
    {
        $fresh = $paper->fresh();

        return [
            'id' => $fresh->id,
            'status' => $fresh->status,
            'total_marks' => (float) $fresh->total_marks,
            'prepared_by' => $fresh->prepared_by,
            'prepared_at' => $fresh->prepared_at,
            'checked_by' => $fresh->checked_by,
            'checked_at' => $fresh->checked_at,
            'approved_by' => $fresh->approved_by,
            'approved_at' => $fresh->approved_at,
            'ratified_by' => $fresh->ratified_by,
            'ratified_at' => $fresh->ratified_at,
        ];
    }
}
