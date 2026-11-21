<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperItem;
use App\Models\QuestionPaperSection;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionBankController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /* =========================
     |  Gate — institution admin or examination officer only
     |========================= */

    private function ensureBankAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->hasRole('examination_officer'),
            403,
            'Question bank access is restricted to administrators and examination officers.'
        );
    }

    /* =========================
     |  Questions
     |========================= */

    public function index(Request $request)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;

        $query = QuestionBankQuestion::where('institution_id', $institutionId)
            ->with('subject:id,code,name')
            ->orderByDesc('created_at');

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', (int) $subjectId);
        }
        if ($unit = $request->query('unit')) {
            $query->where('unit', 'like', '%' . $unit . '%');
        }
        if ($difficulty = $request->query('difficulty')) {
            $query->where('difficulty', $difficulty);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('q')) {
            // Search tags/unit only — question_text/model_answer are encrypted.
            $query->where(function ($q) use ($search) {
                $q->where('tags', 'like', '%' . $search . '%')
                    ->orWhere('unit', 'like', '%' . $search . '%');
            });
        }

        return response()->json([
            'questions' => $query->get()->map(fn ($q) => $this->questionPayload($q)),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;

        $question = QuestionBankQuestion::where('institution_id', $institutionId)
            ->with('subject:id,code,name')
            ->findOrFail($id);

        return response()->json([
            'question' => $this->questionPayload($question),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'unit' => ['nullable', 'string', 'max:80'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'type' => ['required', 'in:mcq,short,long,numerical'],
            'marks' => ['required', 'numeric', 'min:0.5'],
            'question_text' => ['required', 'string', 'min:3'],
            'model_answer' => ['required', 'string', 'min:1'],
            'tags' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'in:draft,active,archived'],
        ]);

        Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);

        $question = QuestionBankQuestion::create([
            'institution_id' => $institutionId,
            'subject_id' => $validated['subject_id'],
            'unit' => $validated['unit'] ?? null,
            'difficulty' => $validated['difficulty'],
            'type' => $validated['type'],
            'marks' => $validated['marks'],
            'question_text' => $validated['question_text'],
            'model_answer' => $validated['model_answer'],
            'tags' => $validated['tags'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        $this->audit->record(
            $request,
            event: 'question_bank.question_created',
            module: 'institution',
            subject: $question,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Question added to the bank.',
            'question' => $this->questionPayload($question->fresh(['subject:id,code,name'])),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;

        $question = QuestionBankQuestion::where('institution_id', $institutionId)->findOrFail($id);

        $validated = $request->validate([
            'subject_id' => ['sometimes', 'integer'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:80'],
            'difficulty' => ['sometimes', 'in:easy,medium,hard'],
            'type' => ['sometimes', 'in:mcq,short,long,numerical'],
            'marks' => ['sometimes', 'numeric', 'min:0.5'],
            'question_text' => ['sometimes', 'string', 'min:3'],
            'model_answer' => ['sometimes', 'string', 'min:1'],
            'tags' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'in:draft,active,archived'],
        ]);

        if (isset($validated['subject_id'])) {
            Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);
        }

        $question->update($validated);

        $this->audit->record(
            $request,
            event: 'question_bank.question_updated',
            module: 'institution',
            subject: $question->fresh(),
            newValues: collect($validated)->except(['question_text', 'model_answer'])->all(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Question updated.',
            'question' => $this->questionPayload($question->fresh(['subject:id,code,name'])),
        ]);
    }

    /**
     * Never hard-deletes: active integrity is preserved for printed papers.
     * Questions referenced by any paper are archived; unused drafts may be archived too.
     */
    public function destroy(Request $request, int $id)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;

        $question = QuestionBankQuestion::where('institution_id', $institutionId)->findOrFail($id);
        $question->update(['status' => 'archived']);

        $this->audit->record(
            $request,
            event: 'question_bank.question_archived',
            module: 'institution',
            subject: $question,
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Question archived.']);
    }

    /* =========================
     |  Papers
     |========================= */

    public function papers(Request $request)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;

        $query = QuestionPaper::where('institution_id', $institutionId)
            ->with(['subject:id,code,name'])
            ->withCount('items')
            ->orderByDesc('created_at');

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', (int) $subjectId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'papers' => $query->get(),
        ]);
    }

    public function storePaper(Request $request)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;
        $user = $request->user();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:40'],
            'instructions' => ['nullable', 'string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:60'],
            'sections.*.instruction' => ['nullable', 'string', 'max:150'],
            'template_id' => ['nullable', 'integer', 'min:1'],
            'numbering_scheme' => ['sometimes', 'string', 'max:30'],
        ]);

        Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);

        $templateId = $validated['template_id'] ?? null;
        if ($templateId !== null) {
            \App\Models\QuestionPaperTemplate::where('institution_id', $institutionId)->findOrFail((int) $templateId);
        }

        $codeTaken = QuestionPaper::where('institution_id', $institutionId)
            ->where('code', $validated['code'])
            ->exists();
        abort_if($codeTaken, 422, 'A paper with that code already exists.');

        $paper = DB::transaction(function () use ($institutionId, $user, $validated, $templateId) {
            $paper = QuestionPaper::create([
                'institution_id' => $institutionId,
                'subject_id' => $validated['subject_id'],
                'code' => strtoupper(trim($validated['code'])),
                'instructions' => $validated['instructions'] ?? null,
                'status' => 'draft',
                'source' => 'manual',
                'total_marks' => 0,
                'template_id' => $templateId,
                'numbering_scheme' => $validated['numbering_scheme'] ?? '1',
                'created_by' => $user->id,
            ]);

            foreach ($validated['sections'] as $index => $section) {
                QuestionPaperSection::create([
                    'institution_id' => $institutionId,
                    'paper_id' => $paper->id,
                    'title' => trim($section['title']),
                    'instruction' => $section['instruction'] ?? null,
                    'position' => $index + 1,
                ]);
            }

            return $paper;
        });

        $this->audit->record(
            $request,
            event: 'question_bank.paper_created',
            module: 'institution',
            subject: $paper,
            newValues: ['code' => $paper->code],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Paper created.',
            'paper' => $this->paperPayload($paper->fresh()),
        ], 201);
    }

    public function showPaper(Request $request, int $id)
    {
        $this->ensureBankAccess($request);

        $paper = $this->findPaper($request->user()->institution_id, $id);

        return response()->json($this->paperPayload($paper));
    }

    public function updatePaper(Request $request, int $id)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;

        $paper = $this->findPaper($institutionId, $id);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:40'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:draft,final,retired'],
        ]);

        if (isset($validated['code'])) {
            $taken = QuestionPaper::where('institution_id', $institutionId)
                ->where('code', strtoupper(trim($validated['code'])))
                ->where('id', '!=', $paper->id)
                ->exists();
            abort_if($taken, 422, 'A paper with that code already exists.');
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $paper->update($validated);

        $this->audit->record(
            $request,
            event: 'question_bank.paper_updated',
            module: 'institution',
            subject: $paper->fresh(),
            newValues: $validated,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Paper updated.',
            'paper' => $this->paperPayload($paper->fresh()),
        ]);
    }

    public function destroyPaper(Request $request, int $id)
    {
        $this->ensureBankAccess($request);

        $paper = $this->findPaper($request->user()->institution_id, $id);
        abort_unless($paper->status === 'draft', 409, 'Only draft papers can be deleted.');

        $paper->delete();

        return response()->json(['message' => 'Paper deleted.']);
    }

    public function addPaperSection(Request $request, int $paperId)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;
        $paper = $this->findPaper($institutionId, $paperId);
        abort_unless($paper->status === 'draft', 409, 'Only draft papers can change sections.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'instruction' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        $section = QuestionPaperSection::create([
            'institution_id' => $institutionId,
            'paper_id' => $paper->id,
            'title' => trim($validated['title']),
            'instruction' => $validated['instruction'] ?? null,
            'position' => $validated['position'] ?? (($paper->sections()->max('position') ?? 0) + 1),
        ]);

        return response()->json([
            'message' => 'Section added.',
            'section' => $section,
        ], 201);
    }

    public function removePaperSection(Request $request, int $paperId, int $sectionId)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;
        $paper = $this->findPaper($institutionId, $paperId);
        abort_unless($paper->status === 'draft', 409, 'Only draft papers can change sections.');

        QuestionPaperSection::where('institution_id', $institutionId)
            ->where('paper_id', $paper->id)
            ->where('id', $sectionId)
            ->firstOrFail()
            ->delete(); // items cascade

        $this->recomputeTotal($paper->fresh());

        return response()->json(['message' => 'Section removed.']);
    }

    public function addPaperItem(Request $request, int $paperId)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;
        $paper = $this->findPaper($institutionId, $paperId);
        abort_unless($paper->status === 'draft', 409, 'Only draft papers can change questions.');

        $validated = $request->validate([
            'section_id' => ['required', 'integer'],
            'question_id' => ['required', 'integer'],
            'marks_override' => ['nullable', 'numeric', 'min:0.5'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        QuestionPaperSection::where('institution_id', $institutionId)
            ->where('paper_id', $paper->id)
            ->findOrFail($validated['section_id']);

        $question = QuestionBankQuestion::where('institution_id', $institutionId)
            ->findOrFail($validated['question_id']);

        $duplicate = QuestionPaperItem::where('paper_id', $paper->id)
            ->where('question_id', $question->id)
            ->exists();
        abort_if($duplicate, 422, 'That question is already in this paper.');

        $item = QuestionPaperItem::create([
            'institution_id' => $institutionId,
            'paper_id' => $paper->id,
            'section_id' => $validated['section_id'],
            'question_id' => $question->id,
            'position' => $validated['position']
                ?? ((QuestionPaperItem::where('section_id', $validated['section_id'])->max('position') ?? 0) + 1),
            'marks_override' => $validated['marks_override'] ?? null,
        ]);

        $this->recomputeTotal($paper->fresh());

        return response()->json([
            'message' => 'Question added to paper.',
            'item_id' => $item->id,
            'total_marks' => (float) $paper->fresh()->total_marks,
        ], 201);
    }

    public function removePaperItem(Request $request, int $paperId, int $itemId)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;
        $paper = $this->findPaper($institutionId, $paperId);
        abort_unless($paper->status === 'draft', 409, 'Only draft papers can change questions.');

        QuestionPaperItem::where('institution_id', $institutionId)
            ->where('paper_id', $paper->id)
            ->where('id', $itemId)
            ->firstOrFail()
            ->delete();

        $this->recomputeTotal($paper->fresh());

        return response()->json(['message' => 'Question removed from paper.']);
    }

    /**
     * Auto-compose a paper from a simple blueprint.
     * Each section requests N questions at a target difficulty mix.
     * Blueprint is not persisted — the generated paper is the artifact.
     */
    public function generatePaper(Request $request)
    {
        $this->ensureBankAccess($request);

        $institutionId = $request->user()->institution_id;
        $user = $request->user();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'code' => ['nullable', 'string', 'max:40'],
            'instructions' => ['nullable', 'string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:60'],
            'sections.*.count' => ['required', 'integer', 'min:1', 'max:50'],
            'sections.*.marks' => ['required', 'numeric', 'min:0.5'],
            'sections.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'sections.*.position' => ['nullable', 'integer', 'min:1'],
        ]);

        Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);

        $code = isset($validated['code']) && $validated['code']
            ? strtoupper(trim($validated['code']))
            : strtoupper(DB::table('subjects')->where('id', $validated['subject_id'])->value('code') . '-Q-' . str_pad((string) (QuestionPaper::where('institution_id', $institutionId)->count() + 1), 3, '0', STR_PAD_LEFT));

        $codeTaken = QuestionPaper::where('institution_id', $institutionId)->where('code', $code)->exists();
        abort_if($codeTaken, 422, 'A paper with that code already exists.');

        $paper = DB::transaction(function () use ($institutionId, $user, $validated, $code) {
            $paper = QuestionPaper::create([
                'institution_id' => $institutionId,
                'subject_id' => $validated['subject_id'],
                'code' => $code,
                'instructions' => $validated['instructions'] ?? null,
                'status' => 'draft',
                'source' => 'blueprint',
                'total_marks' => 0,
                'created_by' => $user->id,
            ]);

            foreach ($validated['sections'] as $index => $section) {
                $paperSection = QuestionPaperSection::create([
                    'institution_id' => $institutionId,
                    'paper_id' => $paper->id,
                    'title' => trim($section['title']),
                    'instruction' => "Answer all questions. Each carries {$section['marks']} marks.",
                    'position' => $section['position'] ?? ($index + 1),
                ]);

                $candidates = QuestionBankQuestion::where('institution_id', $institutionId)
                    ->where('subject_id', $validated['subject_id'])
                    ->where('status', 'active');

                if (!empty($section['difficulty'])) {
                    $candidates->where('difficulty', $section['difficulty']);
                }

                $ids = $candidates->pluck('id')->shuffle()->take((int) $section['count']);

                abort_if(
                    $ids->count() < (int) $section['count'],
                    422,
                    'Not enough active questions in the bank for section "' . $section['title'] . '". Need '
                        . $section['count'] . ', have ' . $ids->count() . '.'
                );

                foreach ($ids as $position => $questionId) {
                    QuestionPaperItem::create([
                        'institution_id' => $institutionId,
                        'paper_id' => $paper->id,
                        'section_id' => $paperSection->id,
                        'question_id' => $questionId,
                        'position' => $position + 1,
                        'marks_override' => (float) $section['marks'],
                    ]);
                }
            }

            return $paper;
        });

        $this->recomputeTotal($paper->fresh());

        $this->audit->record(
            $request,
            event: 'question_bank.paper_generated',
            module: 'institution',
            subject: $paper,
            newValues: ['code' => $paper->code, 'sections' => count($validated['sections'])],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Paper generated from blueprint.',
            'paper' => $this->paperPayload($paper->fresh()),
        ], 201);
    }

    /**
     * Print-ready payload. Accepts ?with_answers=1 to embed model answers
     * (for the setter/checker copy only). Still institution-admin/officer only.
     */
    public function printPaper(Request $request, int $id)
    {
        $this->ensureBankAccess($request);

        $paper = $this->findPaper($request->user()->institution_id, $id);

        $withAnswers = filter_var($request->query('with_answers'), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'paper' => $this->paperPayload($paper, $withAnswers),
            'with_answers' => $withAnswers,
        ]);
    }

    /* =========================
     |  Helpers
     |========================= */

    private function findPaper(int $institutionId, int $id): QuestionPaper
    {
        return QuestionPaper::where('institution_id', $institutionId)
            ->with(['subject:id,code,name'])
            ->findOrFail($id);
    }

    private function recomputeTotal(QuestionPaper $paper): void
    {
        $total = QuestionPaperItem::where('paper_id', $paper->id)
            ->with('question:id,marks')
            ->get()
            ->sum(fn ($item) => $item->marks_override ?? $item->question?->marks ?? 0);

        $paper->update(['total_marks' => round((float) $total, 2)]);
    }

    private function questionPayload($question): array
    {
        return [
            'id' => $question->id,
            'subject_id' => $question->subject_id,
            'subject' => $question->subject ? ['id' => $question->subject->id, 'code' => $question->subject->code, 'name' => $question->subject->name] : null,
            'unit' => $question->unit,
            'difficulty' => $question->difficulty,
            'type' => $question->type,
            'marks' => (float) $question->marks,
            'question_text' => $question->question_text,
            'model_answer' => $question->model_answer,
            'tags' => $question->tags,
            'status' => $question->status,
            'created_at' => $question->created_at,
        ];
    }

    private function paperPayload(QuestionPaper $paper, bool $withAnswers = true): array
    {
        $sections = QuestionPaperSection::where('paper_id', $paper->id)
            ->with(['items' => function ($q) {
                $q->with('question:id,marks,type,difficulty,unit,status,question_text,model_answer')->orderBy('position');
            }])
            ->orderBy('position')
            ->get();

        $sectionPayload = $sections->map(function ($section) use ($withAnswers) {
            $items = $section->items->map(function ($item) use ($withAnswers) {
                $question = $item->question;

                return [
                    'id' => $item->id,
                    'position' => $item->position,
                    'choice_group' => $item->choice_group,
                    'marks' => $item->marks_override !== null ? (float) $item->marks_override : (float) ($question?->marks ?? 0),
                    'question_id' => $item->question_id,
                    'type' => $question?->type,
                    'difficulty' => $question?->difficulty,
                    'unit' => $question?->unit,
                    'question_text' => $question?->question_text,
                    'model_answer' => $withAnswers ? $question?->model_answer : null,
                ];
            });

            return [
                'id' => $section->id,
                'title' => $section->title,
                'instruction' => $section->instruction,
                'position' => $section->position,
                'items' => $items,
            ];
        });

        return [
            'id' => $paper->id,
            'code' => $paper->code,
            'status' => $paper->status,
            'source' => $paper->source,
            'instructions' => $paper->instructions,
            'total_marks' => (float) $paper->total_marks,
            'template_id' => $paper->template_id,
            'numbering_scheme' => $paper->numbering_scheme,
            'prepared_by' => $paper->prepared_by,
            'prepared_at' => $paper->prepared_at,
            'checked_by' => $paper->checked_by,
            'checked_at' => $paper->checked_at,
            'approved_by' => $paper->approved_by,
            'approved_at' => $paper->approved_at,
            'ratified_by' => $paper->ratified_by,
            'ratified_at' => $paper->ratified_at,
            'approvals' => $paper->approvals()->get(['stage', 'decision', 'actor_id', 'remarks', 'created_at']),
            'subject' => $paper->subject ? ['id' => $paper->subject->id, 'code' => $paper->subject->code, 'name' => $paper->subject->name] : null,
            'sections' => $sectionPayload,
        ];
    }
}
