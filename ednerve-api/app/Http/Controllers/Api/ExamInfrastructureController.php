<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamCentre;
use App\Models\ExamKind;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exam infrastructure: sessions (FN/AN/EV), centres, rooms, exam kinds.
 */
class ExamInfrastructureController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    private function ensureView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isInstitutionAdmin() || $user->isFaculty()
                || $user->hasRole('registrar') || $user->hasRole('examination_officer')),
            403,
            'You are not allowed to view exam infrastructure.'
        );
    }

    private function ensureManage(Request $request): void
    {
        abort_unless($request->user()?->isInstitutionAdmin(), 403, 'Institution administrator required to change exam infrastructure.');
    }

    private function audit(Request $request, string $event, $subject = null, ?int $institutionId = null, array $newValues = []): void
    {
        $this->audit->record($request, event: $event, module: 'institution',
            subject: $subject, newValues: $newValues, institutionId: $institutionId);
    }

    /* ---- sessions ---- */

    public function listSessions(Request $request)
    {
        $this->ensureView($request);
        $rows = ExamSession::where('institution_id', $request->user()->institution_id)
            ->orderBy('sort_order')->orderBy('code')->get();
        return response()->json(['data' => $rows]);
    }

    public function storeSession(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('exam_sessions', 'code')->where('institution_id', $institutionId)],
            'label' => ['required', 'string', 'max:60'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $session = ExamSession::create([
            'institution_id' => $institutionId,
            'code' => strtoupper(trim($validated['code'])),
            'label' => trim($validated['label']),
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'sort_order' => $validated['sort_order'] ?? 1,
            'active' => true,
        ]);
        $this->audit($request, 'exam.session_created', $session, $institutionId, ['code' => $session->code]);
        return response()->json(['session' => $session], 201);
    }

    public function updateSession(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $session = ExamSession::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:60'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'sort_order' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $session->update($validated);
        $this->audit($request, 'exam.session_updated', $session->fresh(), $institutionId);
        return response()->json(['session' => $session->fresh()]);
    }

    /* ---- centres & rooms ---- */

    public function listCentres(Request $request)
    {
        $this->ensureView($request);
        $rows = ExamCentre::where('institution_id', $request->user()->institution_id)
            ->with(['rooms' => fn ($q) => $q->where('active', true)->orderBy('code')])
            ->orderBy('code')
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function storeCentre(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('exam_centres', 'code')->where('institution_id', $institutionId)],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $centre = ExamCentre::create([
            'institution_id' => $institutionId,
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'address' => $validated['address'] ?? null,
            'capacity' => $validated['capacity'] ?? 0,
            'active' => true,
        ]);
        $this->audit($request, 'exam.centre_created', $centre, $institutionId, ['code' => $centre->code]);
        return response()->json(['centre' => $centre], 201);
    }

    public function updateCentre(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $centre = ExamCentre::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'capacity' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $centre->update($validated);
        $this->audit($request, 'exam.centre_updated', $centre->fresh(), $institutionId);
        return response()->json(['centre' => $centre->fresh()]);
    }

    public function listRooms(Request $request, int $centreId)
    {
        $this->ensureView($request);
        $institutionId = $request->user()->institution_id;
        ExamCentre::where('institution_id', $institutionId)->findOrFail($centreId);
        $rooms = ExamRoom::where('institution_id', $institutionId)
            ->where('centre_id', $centreId)
            ->orderBy('code')
            ->get();
        return response()->json(['data' => $rooms]);
    }

    public function storeRoom(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'centre_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:80'],
            'room_type' => ['nullable', 'in:theory,lab,viva,server_room'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        $centre = ExamCentre::where('institution_id', $institutionId)->findOrFail($validated['centre_id']);
        $exists = ExamRoom::where('institution_id', $institutionId)
            ->where('centre_id', $centre->id)
            ->where('code', strtoupper(trim($validated['code'])))
            ->exists();
        abort_if($exists, 422, 'A room with that code already exists in this centre.');
        $room = ExamRoom::create([
            'institution_id' => $institutionId,
            'centre_id' => $centre->id,
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'room_type' => $validated['room_type'] ?? 'theory',
            'capacity' => $validated['capacity'],
            'active' => true,
        ]);
        $this->audit($request, 'exam.room_created', $room, $institutionId, ['code' => $room->code]);
        return response()->json(['room' => $room], 201);
    }

    public function updateRoom(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $room = ExamRoom::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'room_type' => ['sometimes', 'in:theory,lab,viva,server_room'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $room->update($validated);
        $this->audit($request, 'exam.room_updated', $room->fresh(), $institutionId);
        return response()->json(['room' => $room->fresh()]);
    }

    /* ---- exam kinds ---- */

    public function listKinds(Request $request)
    {
        $this->ensureView($request);
        $rows = ExamKind::where('institution_id', $request->user()->institution_id)
            ->orderBy('code')
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function storeKind(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('exam_kinds', 'code')->where('institution_id', $institutionId)],
            'label' => ['required', 'string', 'max:60'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $kind = ExamKind::create([
            'institution_id' => $institutionId,
            'code' => strtolower(trim($validated['code'])),
            'label' => trim($validated['label']),
            'max_attempts' => $validated['max_attempts'] ?? null,
            'active' => true,
        ]);
        $this->audit($request, 'exam.kind_created', $kind, $institutionId, ['code' => $kind->code]);
        return response()->json(['kind' => $kind], 201);
    }

    public function updateKind(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $kind = ExamKind::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:60'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:99'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $kind->update($validated);
        $this->audit($request, 'exam.kind_updated', $kind->fresh(), $institutionId);
        return response()->json(['kind' => $kind->fresh()]);
    }
}
