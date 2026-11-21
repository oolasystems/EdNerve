<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * Return users belonging to the authenticated institution.
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $users = User::where('institution_id', $institutionId)
            ->with([
                'activeRoleAssignments.role',
                'activeRoleAssignments.organizationUnit',
            ])
            ->orderBy('name')
            ->paginate(20);

        return response()->json($users);
    }

    /**
     * Create a new institution user.
     */
    public function store(StoreUserRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $validated = $request->validated();

        $institutionId = $request->user()->institution_id;

        $user = User::create([
            'account_type' => 'institution',
            'institution_id' => $institutionId,
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);

        $safe = $user->toArray(); // password/remember_token hidden by model
        unset($safe['password'], $safe['remember_token']);

        $this->audit->record(
            $request,
            event: 'user.created',
            module: 'institution',
            subject: $user,
            newValues: $safe,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user,
        ], 201);
    }

    /**
     * Return a specific user belonging to the authenticated institution.
     */
    public function show(Request $request, int $id)
    {
        $institutionId = $request->user()->institution_id;

        $user = User::where('institution_id', $institutionId)
            ->with([
                'institution',
                'activeRoleAssignments.role',
                'activeRoleAssignments.organizationUnit',
            ])
            ->findOrFail($id);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update an institution user.
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        // Never retrieve a user outside the authenticated institution.
        $user = User::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $user->toArray();
        unset($before['password'], $before['remember_token']);

        $validated = $request->validated();

        // Normalize email.
        if (isset($validated['email'])) {
            $validated['email'] = strtolower(trim($validated['email']));
        }

        // Hash password when supplied.
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make(
                $validated['password']
            );
        }

        $user->update($validated);

        $after = $user->fresh()?->toArray() ?? [];
        unset($after['password'], $after['remember_token']);

        $this->audit->record(
            $request,
            event: 'user.updated',
            module: 'institution',
            subject: $user,
            oldValues: $before,
            newValues: $after,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Deactivate an institution user.
     */
    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $user = User::where('institution_id', $institutionId)
            ->findOrFail($id);

        // Prevent an administrator from deactivating their own account.
        abort_if(
            $user->id === $request->user()->id,
            422,
            'You cannot deactivate your own account.'
        );

        $user->update([
            'status' => 'inactive',
        ]);

        // Revoke all API tokens belonging to the deactivated user.
        $user->tokens()->delete();

        $this->audit->record(
            $request,
            event: 'user.deactivated',
            module: 'institution',
            subject: $user,
            oldValues: ['status' => 'active'],
            newValues: ['status' => 'inactive'],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'User deactivated successfully.',
        ]);
    }
}
