<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\UserRoleAssignment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform-level view of role assignments across every institution:
 * search/filter, inspect, and activate/deactivate.
 * (Creating/institution-scoped editing stays on the institution routes.)
 */
class PlatformAssignmentController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'expiry' => ['nullable', Rule::in(['all', 'expiring', 'expired', 'permanent'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = UserRoleAssignment::query()
            ->with([
                'user:id,name,email,institution_id',
                'user.institution:id,code,name',
                'role:id,code,name',
                'organizationUnit:id,code,name',
            ])
            ->orderByDesc('id');

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['institution_id'])) {
            $query->where('institution_id', $validated['institution_id']);
        }

        if (!empty($validated['role_id'])) {
            $query->where('role_id', $validated['role_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $expiry = $validated['expiry'] ?? null;

        if ($expiry === 'expiring') {
            $query->whereNotNull('ends_at')
                ->whereDate('ends_at', '>=', now())
                ->whereDate('ends_at', '<=', now()->addDays(30));
        } elseif ($expiry === 'expired') {
            $query->whereNotNull('ends_at')
                ->whereDate('ends_at', '<', now());
        } elseif ($expiry === 'permanent') {
            $query->whereNull('ends_at');
        }

        $perPage = $validated['per_page'] ?? 20;

        return response()->json(
            $query->paginate($perPage)->withQueryString()
        );
    }

    public function updateStatus(Request $request, int $id)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $assignment = UserRoleAssignment::findOrFail($id);

        $before = $assignment->toArray();

        $assignment->update([
            'status' => $validated['status'],
        ]);

        $this->audit->record(
            $request,
            event: $validated['status'] === 'active' ? 'role_assignment.activated' : 'role_assignment.deactivated',
            module: 'platform',
            subject: $assignment,
            oldValues: ['status' => $before['status']],
            newValues: ['status' => $validated['status']],
            institutionId: $assignment->institution_id,
        );

        return response()->json([
            'message' => 'Role assignment status updated successfully.',
            'assignment' => $assignment->fresh([
                'user:id,name,email,institution_id',
                'user.institution:id,code,name',
                'role:id,code,name',
                'organizationUnit:id,code,name',
            ]),
        ]);
    }
}
