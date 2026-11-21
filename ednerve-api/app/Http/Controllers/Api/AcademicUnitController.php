<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicUnitRequest;
use App\Http\Requests\UpdateAcademicUnitRequest;
use App\Models\AcademicUnit;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AcademicUnitController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * Faculties → schools → academic units, resolved for the
     * caller's institution. Schools without a faculty (e.g. JNU)
     * and academic units directly under a faculty (e.g. AMU/SPPU)
     * are both supported.
     */
    public function index(Request $request)
    {
        $units = AcademicUnit::where(
            'institution_id',
            $request->user()->institution_id
        )
            ->with(['school.faculty:id,code,name'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'academic_units' => $units,
        ]);
    }

    public function store(StoreAcademicUnitRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $unit = AcademicUnit::create([
            ...$validated,
            'institution_id' => $institutionId,
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'academic_unit.created',
            module: 'institution',
            subject: $unit,
            newValues: $unit->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Academic unit created successfully.',
            'academic_unit' => $unit,
        ], 201);
    }

    public function update(UpdateAcademicUnitRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $unit = AcademicUnit::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $unit->toArray();
        $unit->update($request->validated());

        $this->audit->record(
            $request,
            event: 'academic_unit.updated',
            module: 'institution',
            subject: $unit,
            oldValues: $before,
            newValues: $unit->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Academic unit updated successfully.',
            'academic_unit' => $unit->fresh(['school.faculty']),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $unit = AcademicUnit::where('institution_id', $institutionId)
            ->findOrFail($id);

        abort_if(
            $unit->userRoleAssignments()->exists(),
            422,
            'Reassign user-role assignments before deactivating this unit.'
        );

        $before = $unit->toArray();
        $unit->update(['status' => 'inactive']);

        $this->audit->record(
            $request,
            event: 'academic_unit.deactivated',
            module: 'institution',
            subject: $unit,
            oldValues: $before,
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Academic unit deactivated successfully.',
        ]);
    }
}
