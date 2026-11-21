<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\ElectiveGroup;
use App\Models\Programme;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ElectiveGroupController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $query = ElectiveGroup::where('institution_id', $institutionId)
            ->with(['programme:id,code,name,level', 'subjects:id,code,name,credits'])
            ->orderBy('programme_id')
            ->orderBy('semester')
            ->orderBy('code');

        if ($programmeId = $request->query('programme_id')) {
            $query->where('programme_id', (int) $programmeId);
        }

        if ($semester = $request->query('semester')) {
            $query->where('semester', (int) $semester);
        }

        return response()->json(['groups' => $query->get()]);
    }

    public function store(Request $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'programme_id' => ['required', 'integer', 'min:1'],
            'semester' => ['required', 'integer', 'min:1', 'max:12'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'required_choices' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $programme = Programme::where('institution_id', $institutionId)->findOrFail((int) $validated['programme_id']);

        $exists = ElectiveGroup::where('institution_id', $institutionId)
            ->where('programme_id', $programme->id)
            ->where('semester', (int) $validated['semester'])
            ->where('code', $validated['code'])
            ->exists();

        abort_if($exists, 422, 'An elective group with this code already exists for the programme and semester.');

        $group = ElectiveGroup::create([
            'institution_id' => $institutionId,
            'programme_id' => $programme->id,
            'semester' => (int) $validated['semester'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'capacity' => $validated['capacity'] ?? null,
            'required_choices' => $validated['required_choices'] ?? 1,
            'status' => 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'elective_group.created',
            module: 'institution',
            subject: $group,
            newValues: $group->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Elective group created.',
            'group' => $group->load(['programme:id,code,name,level', 'subjects:id,code,name,credits']),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'required_choices' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $group = ElectiveGroup::where('institution_id', $institutionId)->findOrFail($id);

        $before = $group->toArray();
        $group->update($validated);

        $this->audit->record(
            $request,
            event: 'elective_group.updated',
            module: 'institution',
            subject: $group,
            oldValues: $before,
            newValues: $group->fresh()->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Elective group updated.',
            'group' => $group->fresh()->load(['programme:id,code,name,level', 'subjects:id,code,name,credits']),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $group = ElectiveGroup::where('institution_id', $institutionId)->findOrFail($id);

        $before = $group->toArray();
        $group->update(['status' => 'inactive']);

        $this->audit->record(
            $request,
            event: 'elective_group.deactivated',
            module: 'institution',
            subject: $group,
            oldValues: $before,
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Elective group deactivated.']);
    }

    public function attachSubject(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $group = ElectiveGroup::where('institution_id', $institutionId)->findOrFail($id);

        $subject = Subject::where('institution_id', $institutionId)->findOrFail((int) $validated['subject_id']);

        if ($group->subjects()->where('subject_id', $subject->id)->exists()) {
            return response()->json(['message' => 'Subject already in the elective group.'], 200);
        }

        $group->subjects()->attach($subject->id, [
            'institution_id' => $institutionId,
        ]);

        $this->audit->record(
            $request,
            event: 'elective_group.subject_attached',
            module: 'institution',
            subject: $group,
            newValues: ['subject_id' => $subject->id, 'subject_code' => $subject->code],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject attached to elective group.',
            'group' => $group->fresh()->load(['programme:id,code,name,level', 'subjects:id,code,name,credits']),
        ], 201);
    }

    public function detachSubject(Request $request, int $id, int $subjectId)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $group = ElectiveGroup::where('institution_id', $institutionId)->findOrFail($id);

        $subject = Subject::where('institution_id', $institutionId)->findOrFail($subjectId);

        $group->subjects()->detach($subject->id);

        $this->audit->record(
            $request,
            event: 'elective_group.subject_detached',
            module: 'institution',
            subject: $group,
            oldValues: ['subject_id' => $subject->id, 'subject_code' => $subject->code],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject detached from elective group.',
            'group' => $group->fresh()->load(['programme:id,code,name,level', 'subjects:id,code,name,credits']),
        ]);
    }
}
