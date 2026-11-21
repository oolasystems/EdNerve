<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Platform-level role management: create/update/delete custom roles and
 * sync the permission mappings for any role. System roles are protected
 * from deletion.
 */
class PlatformRoleController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List all roles (including inactive) with their permission counts.
     */
    public function index(Request $request)
    {
        $this->ensurePlatformAdmin($request);

        $roles = Role::query()
            ->withCount(['permissions'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'roles' => $roles,
        ]);
    }

    /**
     * Role detail with its mapped permissions.
     */
    public function show(Request $request, Role $role)
    {
        $this->ensurePlatformAdmin($request);

        $role->loadCount(['permissions']);
        $role->load([
            'permissions' => fn ($q) => $q->select('permissions.*')
                ->orderBy('code'),
        ]);

        return response()->json([
            'role' => $role,
        ]);
    }

    /**
     * Create a custom (non-system) role, optionally with permissions.
     */
    public function store(StoreRoleRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $role = Role::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
            'is_system' => false,
        ]);

        if (!empty($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $this->audit->record(
            $request,
            event: 'role.created',
            module: 'platform',
            subject: $role,
            newValues: [...$role->toArray(), 'permission_ids' => $validated['permission_ids'] ?? []],
            description: 'Custom role created.',
        );

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role->fresh()->loadCount(['permissions']),
        ], 201);
    }

    /**
     * Update role name/description/status. The code is immutable.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->ensurePlatformAdmin($request);

        $before = $role->toArray();

        $role->update($request->validated());

        $this->audit->record(
            $request,
            event: 'role.updated',
            module: 'platform',
            subject: $role,
            oldValues: $before,
            newValues: $role->fresh()?->toArray(),
        );

        return response()->json([
            'message' => 'Role updated successfully.',
            'role' => $role->fresh()->loadCount(['permissions']),
        ]);
    }

    /**
     * Delete a custom role without assignments.
     */
    public function destroy(Request $request, Role $role)
    {
        $this->ensurePlatformAdmin($request);

        abort_if(
            $role->is_system,
            422,
            'System roles cannot be deleted.'
        );

        $assignmentCount = $role->userRoleAssignments()->count();

        abort_if(
            $assignmentCount > 0,
            422,
            'The role is assigned to ' . $assignmentCount . ' user(s). Reassign or deactivate those assignments first.'
        );

        $deleted = $role->toArray();

        $role->delete();

        $this->audit->record(
            $request,
            event: 'role.deleted',
            module: 'platform',
            oldValues: $deleted,
            description: 'Custom role deleted.',
        );

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * Copy (replace) the permission mapping of a source role into this role.
     */
    public function copyPermissions(Request $request, Role $role, Role $sourceRole)
    {
        $this->ensurePlatformAdmin($request);

        $before = $role->permissions()->pluck('permissions.code')->all();

        $permissionIds = $sourceRole->permissions()->pluck('permissions.id')->all();

        $role->permissions()->sync($permissionIds);

        $after = $role->permissions()->pluck('permissions.code')->all();

        $this->audit->record(
            $request,
            event: 'role.permissions_synced',
            module: 'platform',
            subject: $role,
            oldValues: ['permissions' => $before],
            newValues: ['permissions' => $after],
            description: "Permissions copied from role {$sourceRole->code}.",
        );

        return response()->json([
            'message' => "Permissions copied from '{$sourceRole->code}'.",
            'role' => $role->fresh()->load([
                'permissions' => fn ($q) => $q->select('permissions.*')->orderBy('code'),
            ]),
        ]);
    }

    /**
     * Replace the role's permission mapping.
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role)
    {
        $this->ensurePlatformAdmin($request);

        $before = $role->permissions()->pluck('permissions.code')->all();

        $permissionIds = $request->validated()['permission_ids'];

        $role->permissions()->sync($permissionIds);

        $after = $role->permissions()->pluck('permissions.code')->all();

        $this->audit->record(
            $request,
            event: 'role.permissions_synced',
            module: 'platform',
            subject: $role,
            oldValues: ['permissions' => $before],
            newValues: ['permissions' => $after],
            description: 'Role permission mapping replaced.',
        );

        return response()->json([
            'message' => 'Role permissions updated successfully.',
            'role' => $role->fresh()->load([
                'permissions' => fn ($q) => $q->select('permissions.*')
                ->orderBy('code'),
            ]),
        ]);
    }
}
