<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentScheme;
use App\Models\Programme;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Per-programme or per-subject assessment schemes (AMU-style internal
 * splits, e.g. Sessional 10 + Sessional 10 + Mid-sem 10 + End-sem 70).
 */
class AssessmentSchemeController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    private function ensureView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isInstitutionAdmin() || $user->isFaculty()
                || $user->hasRole('registrar') || $user->hasRole('examination_officer')),
            403,
            'You are not allowed to view assessment schemes.'
        );
    }

    private function ensureManage(Request $request): void
    {
        abort_unless($request->user()?->isInstitutionAdmin(), 403, 'Institution administrator required to change schemes.');
    }

    private function validateComponents(array $components): array
    {
        $out = [];
        $totalWeight = 0.0;
        foreach ($components as $index => $component) {
            $weight = isset($component['weightage']) && $component['weightage'] !== null ? (float) $component['weightage'] : null;
            $totalWeight += $weight ?? 0.0;
            $out[] = [
                'position' => (int) ($component['position'] ?? ($index + 1)),
                'name' => trim($component['name']),
                'kind' => $component['kind'],
                'max_marks' => (float) $component['max_marks'],
                'weightage' => $weight,
                'is_external' => (bool) ($component['is_external'] ?? false),
                'min_percent' => isset($component['min_percent']) && $component['min_percent'] !== null ? (float) $component['min_percent'] : null,
            ];
        }

        if ($totalWeight > 0 && abs($totalWeight - 100) > 0.01 && count(array_filter($components, fn ($c) => isset($c['weightage']) && $c['weightage'] !== null)) > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'components' => ["Component weightages sum to {$totalWeight}, expected 100."],
            ]);
        }

        return $out;
    }

    public function index(Request $request)
    {
        $this->ensureView($request);

        $institutionId = $request->user()->institution_id;

        $rows = AssessmentScheme::where('institution_id', $institutionId)
            ->with(['components'])
            ->orderBy('scope')
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('assessment_schemes', 'code')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', 'in:subject,programme'],
            'subject_id' => ['nullable', 'integer'],
            'programme_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.name' => ['required', 'string', 'max:60'],
            'components.*.kind' => ['required', 'in:continuous,external,practical,viva,project'],
            'components.*.max_marks' => ['required', 'numeric', 'min:0.01'],
            'components.*.weightage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.is_external' => ['nullable', 'boolean'],
            'components.*.min_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.position' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validated['scope'] === 'subject') {
            abort_if(empty($validated['subject_id']), 422, 'subject_id is required for a subject scheme.');
            abort_if(!empty($validated['programme_id']), 422, 'A subject scheme cannot also carry programme_id.');
            Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);
        } else {
            abort_if(empty($validated['programme_id']), 422, 'programme_id is required for a programme scheme.');
            abort_if(!empty($validated['subject_id']), 422, 'A programme scheme cannot also carry subject_id.');
            Programme::where('institution_id', $institutionId)->findOrFail($validated['programme_id']);
        }

        $components = $this->validateComponents($validated['components']);

        $scheme = DB::transaction(function () use ($institutionId, $validated, $components) {
            $scheme = AssessmentScheme::create([
                'institution_id' => $institutionId,
                'code' => strtoupper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'scope' => $validated['scope'],
                'subject_id' => $validated['scope'] === 'subject' ? $validated['subject_id'] : null,
                'programme_id' => $validated['scope'] === 'programme' ? $validated['programme_id'] : null,
                'description' => $validated['description'] ?? null,
                'status' => 'active',
            ]);
            foreach ($components as $component) {
                $scheme->components()->create([
                    'institution_id' => $institutionId,
                    ...$component,
                ]);
            }

            return $scheme;
        });

        $this->audit->record($request, event: 'examination.scheme_created', module: 'institution',
            subject: $scheme->fresh('components'), newValues: ['code' => $scheme->code], institutionId: $institutionId);

        return response()->json(['scheme' => $scheme->fresh('components')], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $scheme = AssessmentScheme::where('institution_id', $institutionId)->with('components')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,archived'],
            'components' => ['sometimes', 'array', 'min:1'],
            'components.*.name' => ['required_with:components', 'string', 'max:60'],
            'components.*.kind' => ['required_with:components', 'in:continuous,external,practical,viva,project'],
            'components.*.max_marks' => ['required_with:components', 'numeric', 'min:0.01'],
            'components.*.weightage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.is_external' => ['nullable', 'boolean'],
            'components.*.min_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.position' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($scheme, $validated, $institutionId) {
            $scheme->update(collect($validated)->only(['name', 'description', 'status'])->toArray());

            if (isset($validated['components'])) {
                $components = $this->validateComponents($validated['components']);
                $scheme->components()->delete();
                foreach ($components as $component) {
                    $scheme->components()->create([
                        'institution_id' => $institutionId,
                        ...$component,
                    ]);
                }
            }
        });

        $this->audit->record($request, event: 'examination.scheme_updated', module: 'institution',
            subject: $scheme->fresh('components'), institutionId: $institutionId);

        return response()->json(['scheme' => $scheme->fresh('components')]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $scheme = AssessmentScheme::where('institution_id', $institutionId)->findOrFail($id);
        $scheme->delete();

        $this->audit->record($request, event: 'examination.scheme_deleted', module: 'institution',
            newValues: ['code' => $scheme->code], institutionId: $institutionId);

        return response()->json(['message' => 'Assessment scheme deleted.']);
    }
}
