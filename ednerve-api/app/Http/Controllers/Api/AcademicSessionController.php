<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicSessionRequest;
use App\Http\Requests\UpdateAcademicSessionRequest;
use App\Models\AcademicSession;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List academic sessions for the caller's institution.
     * Active session is returned first.
     */
    public function index(Request $request)
    {
        $sessions = AcademicSession::where(
            'institution_id',
            $request->user()->institution_id
        )
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    public function store(StoreAcademicSessionRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $session = \DB::transaction(function () use ($validated, $institutionId) {
            if (!empty($validated['is_active'])) {
                AcademicSession::where('institution_id', $institutionId)
                    ->update(['is_active' => false]);
            }

            return AcademicSession::create([
                ...$validated,
                'institution_id' => $institutionId,
                'is_active' => $validated['is_active'] ?? false,
                'status' => $validated['status'] ?? 'active',
            ]);
        });

        $this->audit->record(
            $request,
            event: 'academic_session.created',
            module: 'institution',
            subject: $session,
            newValues: $session->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Academic session created successfully.',
            'session' => $session,
        ], 201);
    }

    public function update(UpdateAcademicSessionRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $session = AcademicSession::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $session->toArray();

        \DB::transaction(function () use ($session, $validated, $institutionId) {
            if (array_key_exists('is_active', $validated) && $validated['is_active']) {
                AcademicSession::where('institution_id', $institutionId)
                    ->where('id', '!=', $session->id)
                    ->update(['is_active' => false]);
            }

            $session->update($validated);
        });

        $this->audit->record(
            $request,
            event: 'academic_session.updated',
            module: 'institution',
            subject: $session,
            oldValues: $before,
            newValues: $session->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Academic session updated successfully.',
            'session' => $session->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $session = AcademicSession::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $session->toArray();
        $session->update(['status' => 'inactive', 'is_active' => false]);

        $this->audit->record(
            $request,
            event: 'academic_session.deactivated',
            module: 'institution',
            subject: $session,
            oldValues: $before,
            newValues: ['status' => 'inactive', 'is_active' => false],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Academic session deactivated successfully.',
        ]);
    }

    /**
     * Make this the active academic session for the institution.
     */
    public function activate(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $session = AcademicSession::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $session->toArray();

        \DB::transaction(function () use ($session, $institutionId) {
            AcademicSession::where('institution_id', $institutionId)
                ->update(['is_active' => false]);

            $session->update([
                'is_active' => true,
                'status' => 'active',
            ]);
        });

        $this->audit->record(
            $request,
            event: 'academic_session.activated',
            module: 'institution',
            subject: $session,
            oldValues: $before,
            newValues: ['is_active' => true, 'status' => 'active'],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => "\"{$session->name}\" is now the active academic session.",
            'session' => $session->fresh(),
        ]);
    }
}
