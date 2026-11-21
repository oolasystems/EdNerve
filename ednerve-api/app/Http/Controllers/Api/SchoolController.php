<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\School;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $schools = School::where(
            'institution_id',
            $request->user()->institution_id
        )
            ->with('faculty:id,code,name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'schools' => $schools,
        ]);
    }

    public function store(StoreSchoolRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $school = School::create([
            ...$validated,
            'institution_id' => $institutionId,
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'school.created',
            module: 'institution',
            subject: $school,
            newValues: $school->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'School created successfully.',
            'school' => $school,
        ], 201);
    }

    public function update(UpdateSchoolRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $school = School::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $school->toArray();
        $school->update($request->validated());

        $this->audit->record(
            $request,
            event: 'school.updated',
            module: 'institution',
            subject: $school,
            oldValues: $before,
            newValues: $school->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'School updated successfully.',
            'school' => $school->fresh(['faculty']),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $school = School::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $school->toArray();
        $school->update(['status' => 'inactive']);

        $this->audit->record(
            $request,
            event: 'school.deactivated',
            module: 'institution',
            subject: $school,
            oldValues: $before,
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'School deactivated successfully.',
        ]);
    }
}
