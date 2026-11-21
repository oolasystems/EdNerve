<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexInstitutionRequest;
use App\Http\Requests\StoreInstitutionRequest;
use App\Http\Requests\UpdateInstitutionRequest;
use App\Http\Requests\UpdateInstitutionStatusRequest;
use App\Models\Institution;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstitutionController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    public function dashboard(Request $request)
    {
        // Total institution statistics
        $totalInstitutions = Institution::count();

        $activeInstitutions = Institution::where('status', 'active')->count();

        $inactiveInstitutions = Institution::where('status', 'inactive')->count();

        // Institution user statistics
        $totalInstitutionUsers = User::where(
            'account_type',
            'institution'
        )->count();

        $activeInstitutionUsers = User::where('account_type', 'institution')
            ->where('status', 'active')
            ->count();

        // Institution distribution by type
        $institutionsByType = Institution::query()
            ->selectRaw('institution_type as type, COUNT(*) as total')
            ->groupBy('institution_type')
            ->orderBy('institution_type')
            ->get();

        // Institution distribution by status
        $institutionsByStatus = Institution::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return response()->json(
            [
                'summary' => [
                    'total_institutions' => $totalInstitutions,
                    'active_institutions' => $activeInstitutions,
                    'inactive_institutions' => $inactiveInstitutions,
                    'total_institution_users' => $totalInstitutionUsers,
                    'active_institution_users' => $activeInstitutionUsers,
                ],

                'institution_statistics' => [
                    'by_type' => $institutionsByType,
                    'by_status' => $institutionsByStatus,
                ],

                // Real activity feed from the audit trail (latest 10 events).
                'recent_activities' => \App\Models\AuditLog::with('actor:id,name')
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->map(fn ($log) => [
                        'id' => $log->id,
                        'event' => $log->event,
                        'module' => $log->module,
                        'description' => $log->description,
                        'actor_name' => $log->actor?->name,
                        'created_at' => $log->created_at?->toIso8601String(),
                    ]),
            ],
            200
        );
    }

    /**
     * Return a paginated list of all institutions.
     * Platform Admin only.
     */
    public function index(IndexInstitutionRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $query = Institution::query()
            ->withCount([
                'users',
                'users as active_users_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'academicUnits',
            ]);

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['institution_type'])) {
            $query->where(
                'institution_type',
                $validated['institution_type']
            );
        }

        $sortBy = $validated['sort_by'] ?? 'name';
        $sortOrder = $validated['sort_order'] ?? 'asc';
        $perPage = $validated['per_page'] ?? 20;

        $institutions = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($institutions);
    }

    /**
     * Return details of a specific institution.
     * Platform Admin only.
     */
    /**
     * Show an institution.
     *
     * Platform Admin views any institution by id. Institution users view
     * only their own institution; the id parameter is ignored for them,
     * so the route may also be called with no id.
     */
    public function show(Request $request, ?int $id = null)
    {
        $user = $request->user();

        // Institution users can view only their own institution.
        if ($user->isInstitutionUser()) {
            $institution = $user->institution()
                ->where('status', 'active')
                ->firstOrFail();

            return response()->json([
                'institution' => $institution,
            ]);
        }

        // Platform Admin can view any institution.
        $this->ensurePlatformAdmin($request);
        abort_if($id === null, 400, 'An institution id is required for platform access.');

        $institution = Institution::withCount([
            'users',
            'users as active_users_count' => function ($query) {
                $query->where('status', 'active');
            },
            'users as inactive_users_count' => function ($query) {
                $query->where('status', 'inactive');
            },
            'academicUnits',
        ])->findOrFail($id);

        $institutionAdmin = User::where('institution_id', $institution->id)
            ->whereHas('activeRoleAssignments', function ($query) {
                $query->whereHas('role', function ($roleQuery) {
                    $roleQuery->where('code', 'institution_admin');
                });
            })
            ->orderBy('id')
            ->first([
                'id',
                'name',
                'email',
                'phone',
                'status',
            ]);

        return response()->json([
            'institution' => $institution,
            'institution_admin' => $institutionAdmin,
        ]);
    }

    /**
     * Create a institution and its first Institution Admin.
     * Platform Admin only.
     */
    public function store(StoreInstitutionRequest $request)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $institutionData = $validated['institution'];
        $adminData = $validated['initial_admin'];

        $institutionData['code'] = strtoupper(trim($institutionData['code']));
        $institutionData['slug'] = strtolower(trim($institutionData['slug']));

        $adminData['email'] = strtolower(trim($adminData['email']));

        $result = DB::transaction(function () use (
            $institutionData,
            $adminData
        ) {
            $institution = Institution::create([
                ...$institutionData,
                'status' => 'active',
            ]);

            $institutionAdmin = User::create([
                'account_type' => 'institution',
                'institution_id' => $institution->id,
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'phone' => $adminData['phone'] ?? null,
                'password' => Hash::make($adminData['password']),
                'status' => 'active',
            ]);

            $adminRole = \App\Models\Role::where(
                'code',
                'institution_admin'
            )
                ->where('status', 'active')
                ->firstOrFail();

            $institutionAdmin->roleAssignments()->create([
                'institution_id' => $institution->id,
                'role_id' => $adminRole->id,
                'academic_unit_id' => null,
                'is_primary' => true,
                'status' => 'active',
                'starts_at' => now()->toDateString(),
                'ends_at' => null,
            ]);

            return [
                'institution' => $institution,
                'institution_admin' => $institutionAdmin,
            ];
        });

        $this->audit->record(
            $request,
            event: 'institution.created',
            module: 'platform',
            subject: $result['institution'],
            newValues: $result['institution']->toArray(),
            description: 'Institution created by platform administrator.',
            institutionId: $result['institution']->id,
        );

        return response()->json([
            'message' => 'Institution created successfully.',
            'institution' => $result['institution'],
            'institution_admin' => [
                'id' => $result['institution_admin']->id,
                'name' => $result['institution_admin']->name,
                'email' => $result['institution_admin']->email,
                'phone' => $result['institution_admin']->phone,
                'account_type' => $result['institution_admin']->account_type,
                'institution_id' => $result['institution_admin']->institution_id,
                'role' => 'institution_admin',
            ],
        ], 201);
    }

    /**
     * Update institution details.
     * Platform Admin only.
     */
    public function update(UpdateInstitutionRequest $request, int $id)
    {
        $this->ensurePlatformAdmin($request);

        $institution = Institution::findOrFail($id);

        $before = $institution->toArray();

        $institution->update($request->validated());

        $this->audit->record(
            $request,
            event: 'institution.updated',
            module: 'platform',
            subject: $institution,
            oldValues: $before,
            newValues: $institution->fresh()?->toArray(),
            institutionId: $institution->id,
        );

        return response()->json([
            'message' => 'Institution updated successfully.',
            'institution' => $institution->fresh(),
        ]);
    }

    /**
     * Update the profile of the caller's own institution.
     * Institution Administrator only.
     *
     * Identity-critical fields (code, type, status, slug) stay
     * platform-managed; this endpoint deliberately accepts only the
     * editable contact/profile fields.
     */
    public function updateProfile(Request $request)
    {
        $this->ensureInstitutionAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $institution = $request->user()->institution()->firstOrFail();

        $before = $institution->toArray();
        $institution->update($validated);

        $this->audit->record(
            $request,
            event: 'institution_profile.updated',
            module: 'institution',
            subject: $institution,
            oldValues: $before,
            newValues: $institution->fresh()?->toArray(),
            institutionId: $institution->id,
        );

        return response()->json([
            'message' => 'Institution profile updated successfully.',
            'institution' => $institution->fresh(),
        ]);
    }

    /**
     * Activate or deactivate a institution.
     * Platform Admin only.
     */
    public function updateStatus(UpdateInstitutionStatusRequest $request, int $id)
    {
        $this->ensurePlatformAdmin($request);

        $validated = $request->validated();

        $institution = Institution::findOrFail($id);

        $before = $institution->status;

        $institution->update([
            'status' => $validated['status'],
        ]);

        $this->audit->record(
            $request,
            event: 'institution.status_changed',
            module: 'platform',
            subject: $institution,
            oldValues: ['status' => $before],
            newValues: ['status' => $validated['status']],
            institutionId: $institution->id,
        );

        return response()->json([
            'message' => 'Institution status updated successfully.',
            'institution' => $institution->fresh(),
        ]);
    }
}
