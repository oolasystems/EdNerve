<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\QuestionPaperTemplate;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionPaperTemplateController extends Controller
{
    use RequiresAccountType;

    public const KINDS = ['exam', 'unit_test', 'mid', 'sessional'];

    public function __construct(
        private readonly AuditService $audit
    ) {}

    private function ensureTemplateAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user?->isInstitutionAdmin() || $user?->hasRole('examination_officer'),
            403,
            'Paper templates are restricted to administrators and examination officers.'
        );
    }

    public function index(Request $request)
    {
        $this->ensureTemplateAccess($request);

        $institutionId = $request->user()->institution_id;

        $templates = QuestionPaperTemplate::where('institution_id', $institutionId)
            ->orderBy('assessment_kind')
            ->orderBy('name')
            ->get();

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $this->ensureTemplateAccess($request);

        $institutionId = $request->user()->institution_id;
        $validated = $this->validatePayload($request);

        $taken = QuestionPaperTemplate::where('institution_id', $institutionId)
            ->where('name', $validated['name'])
            ->exists();
        abort_if($taken, 422, 'A template with this name already exists.');

        $template = QuestionPaperTemplate::create([
            'institution_id' => $institutionId,
            'name' => $validated['name'],
            'assessment_kind' => $validated['assessment_kind'] ?? self::KIND_EXAM,
            'total_marks' => $validated['total_marks'],
            'pattern' => $validated['pattern'] ?? null,
            'instructions_md' => $validated['instructions_md'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'question_paper_template.created',
            module: 'institution',
            subject: $template,
            newValues: ['name' => $template->name, 'assessment_kind' => $template->assessment_kind],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Paper template created.',
            'template' => $template,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureTemplateAccess($request);

        $institutionId = $request->user()->institution_id;
        $template = QuestionPaperTemplate::where('institution_id', $institutionId)->findOrFail($id);

        $validated = $this->validatePayload($request, updating: true);

        $before = $template->toArray();
        $template->fill(array_filter([
            'name' => $validated['name'] ?? null,
            'assessment_kind' => $validated['assessment_kind'] ?? null,
            'total_marks' => $validated['total_marks'] ?? null,
            'pattern' => array_key_exists('pattern', $validated) ? $validated['pattern'] : null,
            'instructions_md' => array_key_exists('instructions_md', $validated) ? $validated['instructions_md'] : null,
            'status' => $validated['status'] ?? null,
        ], fn ($v) => $v !== null));
        $template->save();

        $this->audit->record(
            $request,
            event: 'question_paper_template.updated',
            module: 'institution',
            subject: $template,
            oldValues: $before,
            newValues: $template->fresh()->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Template updated.',
            'template' => $template->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureTemplateAccess($request);

        $institutionId = $request->user()->institution_id;
        $template = QuestionPaperTemplate::where('institution_id', $institutionId)->findOrFail($id);

        $before = $template->toArray();
        $template->update(['status' => 'inactive']);

        $this->audit->record(
            $request,
            event: 'question_paper_template.deactivated',
            module: 'institution',
            subject: $template,
            oldValues: $before,
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Template deactivated.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $updating = false): array
    {
        $rules = [
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:120'],
            'assessment_kind' => ['sometimes', 'string', Rule::in(self::KINDS)],
            'total_marks' => [$updating ? 'sometimes' : 'required', 'numeric', 'min:0.5', 'max:10000'],
            'pattern' => ['nullable', 'array', 'max:20'],
            'pattern.*.title' => ['required_with:pattern', 'string', 'max:60'],
            'pattern.*.position' => ['required_with:pattern', 'integer', 'min:1', 'max:20'],
            'pattern.*.instruction' => ['nullable', 'string', 'max:150'],
            'pattern.*.items' => ['required_with:pattern', 'integer', 'min:1', 'max:200'],
            'pattern.*.marks' => ['required_with:pattern', 'numeric', 'min:0.5', 'max:1000'],
            'instructions_md' => ['nullable', 'string', 'max:20000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];

        return $request->validate($rules);
    }
}
