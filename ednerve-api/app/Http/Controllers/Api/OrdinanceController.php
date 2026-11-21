<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExaminationOrdinance;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Institution examination ordinances: grading bands + pass criteria +
 * attendance threshold, kept as data (the result engine reads these).
 */
class OrdinanceController extends Controller
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
            'You are not allowed to view ordinances.'
        );
    }

    private function ensureManage(Request $request): void
    {
        abort_unless($request->user()?->isInstitutionAdmin(), 403, 'Institution administrator required to change ordinances.');
    }

    private function validateBands(array $bands): array
    {
        $out = [];
        foreach ($bands as $band) {
            $out[] = [
                'min' => (float) $band['min'],
                'grade' => strtoupper(trim((string) $band['grade'])),
                'points' => (float) ($band['points'] ?? 0),
            ];
        }

        usort($out, fn ($a, $b) => $b['min'] <=> $a['min']);

        return $out;
    }

    public function index(Request $request)
    {
        $this->ensureView($request);

        $rows = ExaminationOrdinance::where('institution_id', $request->user()->institution_id)
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('examination_ordinances', 'code')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'grading_bands' => ['required', 'array', 'min:2'],
            'grading_bands.*.min' => ['required', 'numeric', 'min:0', 'max:100'],
            'grading_bands.*.grade' => ['required', 'string', 'max:3'],
            'grading_bands.*.points' => ['required', 'numeric', 'min:0'],
            'pass_criteria' => ['required', 'array'],
            'pass_criteria.overall_min_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'pass_criteria.external_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pass_criteria.condonation_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pass_criteria.max_backlogs' => ['nullable', 'integer', 'min:0'],
            'attendance_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $bands = $this->validateBands($validated['grading_bands']);
        $explicitDefault = (bool) ($validated['is_default'] ?? false);
        $hasDefault = ExaminationOrdinance::where('institution_id', $institutionId)->where('is_default', true)->exists();

        $ordinance = DB::transaction(function () use ($institutionId, $validated, $bands, $explicitDefault, $hasDefault) {
            if ($explicitDefault && $hasDefault) {
                ExaminationOrdinance::where('institution_id', $institutionId)->where('is_default', true)->update(['is_default' => false]);
            }
            $makeDefault = $explicitDefault || !$hasDefault;

            return ExaminationOrdinance::create([
                'institution_id' => $institutionId,
                'code' => strtoupper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?? null,
                'grading_bands' => $bands,
                'pass_criteria' => array_merge(['overall_min_pct' => 0, 'external_min_pct' => 0, 'condonation_pct' => 0, 'max_backlogs' => null], $validated['pass_criteria']),
                'attendance_threshold' => $validated['attendance_threshold'] ?? 75,
                'is_default' => $makeDefault,
                'status' => 'active',
            ]);
        });

        $this->audit->record($request, event: 'examination.ordinance_created', module: 'institution',
            subject: $ordinance, newValues: ['code' => $ordinance->code], institutionId: $institutionId);

        return response()->json(['ordinance' => $ordinance], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $ordinance = ExaminationOrdinance::where('institution_id', $institutionId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'grading_bands' => ['sometimes', 'array', 'min:2'],
            'grading_bands.*.min' => ['required_with:grading_bands', 'numeric', 'min:0', 'max:100'],
            'grading_bands.*.grade' => ['required_with:grading_bands', 'string', 'max:3'],
            'grading_bands.*.points' => ['required_with:grading_bands', 'numeric', 'min:0'],
            'pass_criteria' => ['sometimes', 'array'],
            'pass_criteria.overall_min_pct' => ['required_with:pass_criteria', 'numeric', 'min:0', 'max:100'],
            'pass_criteria.external_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pass_criteria.condonation_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pass_criteria.max_backlogs' => ['nullable', 'integer', 'min:0'],
            'attendance_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'in:active,archived'],
        ]);

        if (isset($validated['grading_bands'])) {
            $validated['grading_bands'] = $this->validateBands($validated['grading_bands']);
        }

        $ordinance->update($validated);

        $this->audit->record($request, event: 'examination.ordinance_updated', module: 'institution',
            subject: $ordinance->fresh(), institutionId: $institutionId);

        return response()->json(['ordinance' => $ordinance->fresh()]);
    }

    public function setDefault(Request $request, int $id)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;

        $ordinance = DB::transaction(function () use ($institutionId, $id) {
            ExaminationOrdinance::where('institution_id', $institutionId)->where('is_default', true)->update(['is_default' => false]);
            $ordinance = ExaminationOrdinance::where('institution_id', $institutionId)->findOrFail($id);
            $ordinance->update(['is_default' => true, 'status' => 'active']);

            return $ordinance;
        });

        $this->audit->record($request, event: 'examination.ordinance_set_default', module: 'institution',
            subject: $ordinance, institutionId: $institutionId);

        return response()->json(['message' => 'Default ordinance updated.', 'ordinance' => $ordinance->fresh()]);
    }

    public function archive(Request $request, int $id)
    {
        $this->ensureManage($request);

        $institutionId = $request->user()->institution_id;
        $ordinance = ExaminationOrdinance::where('institution_id', $institutionId)->findOrFail($id);

        abort_if($ordinance->is_default, 409, 'The default ordinance cannot be archived. Set another ordinance as default first.');

        $ordinance->update(['status' => 'archived']);

        $this->audit->record($request, event: 'examination.ordinance_archived', module: 'institution',
            subject: $ordinance, institutionId: $institutionId);

        return response()->json(['message' => 'Ordinance archived.']);
    }
}
