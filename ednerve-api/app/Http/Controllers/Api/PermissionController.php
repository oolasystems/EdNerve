<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPermissionRequest;
use App\Http\Requests\StorePermissionRequest;
use App\Models\Permission;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Permission catalogue: the codes roles can grant.
 *
 * Design boundary: the catalogue itself is not authorizing anything —
 * enforcement gates on account_type / isInstitutionAdmin() today (D7).
 * Restricted to Platform Admin accounts.
 */
class PermissionController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * Catalogue with optional filters. Defaults to active permissions
     * for backwards compatibility with existing consumers.
     */
    public function index(IndexPermissionRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $query = Permission::query();

        // Backwards-compatible default: active catalogue.
        $status = $validated['status'] ?? 'active';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        if (($validated['system'] ?? null) === 'system') {
            $query->where('is_system', true);
        } elseif (($validated['system'] ?? null) === 'custom') {
            $query->where('is_system', false);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $permissions = $query
            ->orderBy('module')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'module', 'description', 'status', 'is_system']);

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create a custom permission. System permissions can never be
     * created via the API (they belong to the seed contract).
     */
    public function store(StorePermissionRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $permission = Permission::create([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'module' => strtolower($validated['module']),
            'description' => $validated['description'] ?? null,
            'status' => 'active',
            'is_system' => false,
        ]);

        $this->audit->record(
            $request,
            event: 'permission.created',
            module: 'platform',
            subject: $permission,
            newValues: $permission->toArray(),
            description: 'Custom permission created.',
        );

        return response()->json([
            'message' => 'Permission created successfully.',
            'permission' => $permission,
        ], 201);
    }

    /**
     * Update a permission's name/description/status.
     * The code and the system flag are immutable.
     */
    public function update(Request $request, Permission $permission)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ]);

        $before = $permission->toArray();

        $permission->update($validated);

        // Status toggles are auditable as their own event for clarity.
        $event = array_key_exists('status', $validated) && $validated['status'] !== ($before['status'] ?? null)
            ? 'permission.status_changed'
            : 'permission.updated';

        $this->audit->record(
            $request,
            event: $event,
            module: 'platform',
            subject: $permission,
            oldValues: $before,
            newValues: $permission->fresh()?->toArray(),
        );

        return response()->json([
            'message' => 'Permission updated successfully.',
            'permission' => $permission->fresh(),
        ]);
    }
}
