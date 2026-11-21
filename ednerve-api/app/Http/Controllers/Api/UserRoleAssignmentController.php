<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleAssignmentRequest;
use App\Http\Requests\UpdateRoleAssignmentRequest;
use App\Models\AcademicUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRoleAssignmentController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $assignments = UserRoleAssignment::where(
            'institution_id',
            $request->user()->institution_id
        )
        ->with(['user', 'role', 'organizationUnit'])
        ->orderBy('user_id')
        ->paginate(50);

        return response()->json($assignments);
    }

    public function store(StoreRoleAssignmentRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validated();

        $user = User::where('institution_id', $institutionId)
            ->findOrFail($validated['user_id']);

        $role = Role::findOrFail($validated['role_id']);

        if (!empty($validated['academic_unit_id'])) {
            AcademicUnit::where('institution_id', $institutionId)
                ->findOrFail($validated['academic_unit_id']);
        }

        $isPrimary = $validated['is_primary'] ?? false;

        $assignment = DB::transaction(function () use (
            $validated,
            $institutionId,
            $user,
            $isPrimary
        ) {
            if ($isPrimary) {
                UserRoleAssignment::where('institution_id', $institutionId)
                    ->where('user_id', $user->id)
                    ->update(['is_primary' => false]);
            }

            return UserRoleAssignment::create([
                'institution_id' => $institutionId,
                'user_id' => $user->id,
                'role_id' => $validated['role_id'],
                'academic_unit_id' =>
                    $validated['academic_unit_id'] ?? null,
                'is_primary' => $isPrimary,
                'status' => $validated['status'] ?? 'active',
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ]);
        });

        $this->audit->record(
            $request,
            event: 'role_assignment.created',
            module: 'institution',
            subject: $assignment,
            newValues: $assignment->load([
                'user', 'role', 'organizationUnit'
            ])->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Role assigned successfully.',
            'assignment' => $assignment->load([
                'user', 'role', 'organizationUnit'
            ]),
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $assignment = UserRoleAssignment::where(
            'institution_id',
            $request->user()->institution_id
        )
        ->with(['user', 'role', 'organizationUnit'])
        ->findOrFail($id);

        return response()->json([
            'assignment' => $assignment,
        ]);
    }

    public function update(UpdateRoleAssignmentRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $assignment = UserRoleAssignment::where(
            'institution_id',
            $institutionId
        )->findOrFail($id);

        $before = $assignment->toArray();

        $validated = $request->validated();

        if (!empty($validated['academic_unit_id'])) {
            AcademicUnit::where('institution_id', $institutionId)
                ->findOrFail($validated['academic_unit_id']);
        }

        DB::transaction(function () use (
            $assignment,
            $validated,
            $institutionId
        ) {
            if ($validated['is_primary'] ?? false) {
                UserRoleAssignment::where('institution_id', $institutionId)
                    ->where('user_id', $assignment->user_id)
                    ->where('id', '!=', $assignment->id)
                    ->update(['is_primary' => false]);
            }

            $assignment->update($validated);
        });

        $this->audit->record(
            $request,
            event: 'role_assignment.updated',
            module: 'institution',
            subject: $assignment,
            oldValues: $before,
            newValues: $assignment->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Role assignment updated successfully.',
            'assignment' => $assignment->fresh([
                'user', 'role', 'organizationUnit'
            ]),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $assignment = UserRoleAssignment::where(
            'institution_id',
            $request->user()->institution_id
        )->findOrFail($id);

        $before = $assignment->toArray();

        $assignment->update([
            'status' => 'inactive',
            'is_primary' => false,
        ]);

        $this->audit->record(
            $request,
            event: 'role_assignment.deactivated',
            module: 'institution',
            subject: $assignment,
            oldValues: $before,
            newValues: ['status' => 'inactive', 'is_primary' => false],
            institutionId: $assignment->institution_id,
        );

        return response()->json([
            'message' => 'Role assignment deactivated successfully.',
        ]);
    }
}
