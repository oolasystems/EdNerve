<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectComponent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectComponentController extends Controller
{
    use RequiresAccountType;

    public const KINDS = ['theory', 'practical', 'tutorial'];

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function index(Request $request, int $subjectId)
    {
        $institutionId = $request->user()->institution_id;

        Subject::where('institution_id', $institutionId)->findOrFail($subjectId);

        $components = SubjectComponent::where('institution_id', $institutionId)
            ->where('subject_id', $subjectId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json(['components' => $components]);
    }

    /**
     * Upsert a component split (one per kind per subject).
     */
    public function upsert(Request $request, int $subjectId)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(self::KINDS)],
            'credits' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'hours_per_week' => ['sometimes', 'integer', 'min:0', 'max:40'],
            'max_marks' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'position' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $subject = Subject::where('institution_id', $institutionId)->findOrFail($subjectId);

        $component = SubjectComponent::updateOrCreate(
            [
                'institution_id' => $institutionId,
                'subject_id' => $subject->id,
                'kind' => $validated['kind'],
            ],
            [
                'credits' => $validated['credits'] ?? 0,
                'hours_per_week' => $validated['hours_per_week'] ?? 0,
                'max_marks' => $validated['max_marks'] ?? null,
                'position' => $validated['position'] ?? 1,
                'status' => $validated['status'] ?? 'active',
            ]
        );

        $this->audit->record(
            $request,
            event: 'subject.component_upserted',
            module: 'institution',
            subject: $subject,
            newValues: $component->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Component saved.',
            'component' => $component,
        ], 201);
    }

    public function destroy(Request $request, int $subjectId, string $kind)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        abort_unless(in_array($kind, self::KINDS, true), 404);

        $subject = Subject::where('institution_id', $institutionId)->findOrFail($subjectId);

        $component = SubjectComponent::where('institution_id', $institutionId)
            ->where('subject_id', $subject->id)
            ->where('kind', $kind)
            ->first();

        abort_if($component === null, 404, 'Component not found.');

        $before = $component->toArray();
        $component->delete();

        $this->audit->record(
            $request,
            event: 'subject.component_removed',
            module: 'institution',
            subject: $subject,
            oldValues: $before,
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Component removed.']);
    }
}
