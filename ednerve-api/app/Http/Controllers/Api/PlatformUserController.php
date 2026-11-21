<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPlatformUserRequest;
use App\Http\Requests\StorePlatformUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Platform-wide user administration: list users across institutions
 * (and platform admins), inspect them, create platform admins and
 * activate/deactivate accounts safely.
 */
class PlatformUserController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List users platform-wide with search and filters.
     */
    public function index(IndexPlatformUserRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $query = User::query()
            ->with([
                'institution:id,code,name,slug',
                'activeRoleAssignments.role:id,code,name',
            ]);

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['account_type'])) {
            $query->where('account_type', $validated['account_type']);
        }

        if (!empty($validated['institution_id'])) {
            $query->where('institution_id', $validated['institution_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $sortBy = $validated['sort_by'] ?? 'name';
        $sortOrder = $validated['sort_order'] ?? 'asc';
        $perPage = $validated['per_page'] ?? 20;

        $users = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($users);
    }

    /**
     * Build the user's current effective access: union of permissions
     * granted by every active role assignment (in-window), with provenance.
     * Read-only lens — does not modify anything.
     */
    public function effectivePermissions(Request $request, int $id)
    {
        $this->ensurePlatformAdmin($request);

        $user = User::with([
            'institution:id,code,name,slug,institution_type,status',
            'activeRoleAssignments' => function ($query) {
                $query->with([
                    'role' => fn ($q) => $q->where('status', 'active')
                        ->select(['id', 'code', 'name', 'status', 'is_system']),
                    'role.permissions' => fn ($q) => $q
                        ->select(['permissions.id', 'code', 'name', 'module', 'description'])
                        ->where('permissions.status', 'active'),
                    'organizationUnit:id,code,name',
                ]);
            },
        ])->findOrFail($id);

        $assignments = [];
        $permissionMap = [];

        foreach ($user->activeRoleAssignments as $assignment) {
            $role = $assignment->role;

            if (!$role) {
                continue;
            }

            $unit = $assignment->organizationUnit;
            $scope = $unit?->name;

            $assignments[] = [
                'id' => $assignment->id,
                'role' => [
                    'code' => $role->code,
                    'name' => $role->name,
                    'is_system' => $role->is_system,
                ],
                'organization_unit' => $unit ? [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                ] : null,
                'is_primary' => $assignment->is_primary,
                'starts_at' => $assignment->starts_at?->toIso8601String(),
                'ends_at' => $assignment->ends_at?->toIso8601String(),
            ];

            foreach ($role->permissions as $permission) {
                $code = $permission->code;

                if (!isset($permissionMap[$code])) {
                    $permissionMap[$code] = [
                        'id' => $permission->id,
                        'code' => $permission->code,
                        'name' => $permission->name,
                        'module' => $permission->module,
                        'description' => $permission->description,
                        'via' => [],
                    ];
                }

                $permissionMap[$code]['via'][] = [
                    'role_code' => $role->code,
                    'role_name' => $role->name,
                    'scope' => $scope,
                    'is_primary' => $assignment->is_primary,
                    'expires' => $assignment->ends_at?->toIso8601String(),
                ];
            }
        }

        usort($permissionMap, fn ($a, $b) => strcmp($a['code'], $b['code']));

        return response()->json([
            'user' => $user->only(['id', 'account_type', 'name', 'email', 'status', 'institution_id', 'created_at']),
            'institution' => $user->institution?->only(['id', 'code', 'name', 'slug', 'institution_type', 'status']),
            'assignments' => $assignments,
            'permissions' => array_values($permissionMap),
            'assignment_count' => count($assignments),
            'permission_count' => count($permissionMap),
        ]);
    }

    /**
     * Detail view: account, institution and effective role assignments.
     */
    public function show(Request $request, int $id)
    {
        $this->ensurePlatformAdmin($request);

        $user = User::with([
            'institution',
            'activeRoleAssignments.role',
            'activeRoleAssignments.organizationUnit:id,code,name',
        ])->findOrFail($id);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Create a platform administrator account.
     */
    public function store(StorePlatformUserRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $user = User::create([
            'account_type' => 'platform',
            'institution_id' => null,
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'platform_user.created',
            module: 'platform',
            subject: $user,
            newValues: $user->toArray(),
            description: 'Platform administrator account created.',
            institutionId: null,
        );

        return response()->json([
            'message' => 'Platform administrator created successfully.',
            'user' => $user,
        ], 201);
    }

    /**
     * Activate or deactivate any user — with self and last-admin protections.
     */
    public function updateStatus(UpdateUserStatusRequest $request, int $id)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $user = User::findOrFail($id);
        $actor = $request->user();

        // Never allow acting on your own account.
        abort_if(
            $user->id === $actor->id,
            422,
            'You cannot change your own account status.'
        );

        $before = $user->toArray();

        // Deactivating a platform admin requires another active one to remain.
        if (
            $validated['status'] === 'inactive' &&
            $user->account_type === 'platform' &&
            $user->status === 'active'
        ) {
            $otherActivePlatformAdmins = User::query()
                ->where('account_type', 'platform')
                ->where('status', 'active')
                ->whereKeyNot($user->id)
                ->count();

            abort_if(
                $otherActivePlatformAdmins < 1,
                422,
                'At least one active platform administrator is required.'
            );
        }

        $user->update([
            'status' => $validated['status'],
        ]);

        // Deactivation revokes all API tokens immediately.
        if ($validated['status'] === 'inactive') {
            $user->tokens()->delete();
        }

        $event = $validated['status'] === 'active' ? 'user.activated' : 'user.deactivated';

        $this->audit->record(
            $request,
            event: $event,
            module: 'platform',
            subject: $user,
            oldValues: ['status' => $before['status']],
            newValues: ['status' => $validated['status']],
            institutionId: $user->institution_id,
        );

        return response()->json([
            'message' => $validated['status'] === 'active'
                ? 'User activated successfully.'
                : 'User deactivated successfully.',
            'user' => $user->fresh(),
        ]);
    }
}
