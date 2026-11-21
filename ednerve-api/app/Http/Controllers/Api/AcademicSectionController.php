<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Programme;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicSectionController extends Controller
{
    use RequiresAccountType;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;
        $query = AcademicSection::where('institution_id', $institutionId)
            ->with(['academicSession:id,code,name', 'programme:id,code,name,level']);

        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', (int) $request->query('academic_session_id'));
        }
        if ($request->filled('programme_id')) {
            $query->where('programme_id', (int) $request->query('programme_id'));
        }
        if ($request->filled('semester')) {
            $query->where('semester', (int) $request->query('semester'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $sections = $query->orderBy('programme_id')->orderBy('semester')->orderBy('code')->get();
        return response()->json(['sections' => $sections]);
    }

    public function store(Request $request)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'academic_session_id' => ['nullable', 'integer'],
            'programme_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $this->assertRefs($institutionId, $validated);

        $exists = AcademicSection::where('institution_id', $institutionId)
            ->where('academic_session_id', $validated['academic_session_id'] ?? null)
            ->where('programme_id', $validated['programme_id'] ?? null)
            ->where('semester', $validated['semester'] ?? null)
            ->where('code', $validated['code'])
            ->exists();
        abort_if($exists, 422, 'A section/batch with this code already exists in the selected scope.');

        $section = AcademicSection::create([
            'institution_id' => $institutionId,
            'academic_session_id' => $validated['academic_session_id'] ?? null,
            'programme_id' => $validated['programme_id'] ?? null,
            'semester' => $validated['semester'] ?? null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'capacity' => $validated['capacity'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);
        $section->load(['academicSession:id,code,name', 'programme:id,code,name,level']);

        $this->audit->record($request, 'academic_section.created', 'academic', $section, [], $section->toArray(), null, $institutionId);

        return response()->json(['message' => 'Section/batch created.', 'section' => $section], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;
        $section = AcademicSection::where('institution_id', $institutionId)->findOrFail($id);

        $validated = $request->validate([
            'academic_session_id' => ['nullable', 'integer'],
            'programme_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'code' => ['sometimes', 'required', 'string', 'max:30'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $this->assertRefs($institutionId, $validated);
        $before = $section->toArray();
        $section->fill($validated);
        $section->save();
        $section->load(['academicSession:id,code,name', 'programme:id,code,name,level']);

        $this->audit->record($request, 'academic_section.updated', 'academic', $section, $before, $section->toArray(), null, $institutionId);

        return response()->json(['message' => 'Section/batch updated.', 'section' => $section]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;
        $section = AcademicSection::where('institution_id', $institutionId)->findOrFail($id);
        $before = $section->toArray();
        $section->delete();

        $this->audit->record($request, 'academic_section.deleted', 'academic', null, $before, ['id' => $id], null, $institutionId);

        return response()->json(['message' => 'Section/batch deleted.']);
    }

    private function assertRefs(int $institutionId, array $validated): void
    {
        if (!empty($validated['academic_session_id'])) {
            abort_unless(
                AcademicSession::where('institution_id', $institutionId)->where('id', $validated['academic_session_id'])->exists(),
                422,
                'Invalid academic session.'
            );
        }
        if (!empty($validated['programme_id'])) {
            abort_unless(
                Programme::where('institution_id', $institutionId)->where('id', $validated['programme_id'])->exists(),
                422,
                'Invalid programme.'
            );
        }
    }
}
