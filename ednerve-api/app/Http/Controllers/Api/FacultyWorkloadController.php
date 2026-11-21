<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FacultyWorkloadEntry;
use App\Models\Programme;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FacultyWorkloadController extends Controller
{
    use RequiresAccountType;

    public function __construct(private readonly AuditService $audit) {}

    private function ensureManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->isInstitutionAdmin() || $user->hasRole('hod') || $user->hasRole('dean') || $user->hasRole('registrar'), 403, 'You are not allowed to manage faculty workload.');
    }

    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;
        $q = FacultyWorkloadEntry::where('institution_id', $institutionId)
            ->with(['user:id,name,email', 'academicSession:id,code,name', 'subject:id,code,name', 'programme:id,code,name']);
        if ($request->filled('user_id')) $q->where('user_id', (int)$request->query('user_id'));
        if ($request->filled('academic_session_id')) $q->where('academic_session_id', (int)$request->query('academic_session_id'));
        if ($request->filled('subject_id')) $q->where('subject_id', (int)$request->query('subject_id'));
        if ($request->filled('status')) $q->where('status', (string)$request->query('status'));
        $entries = $q->orderByDesc('id')->get();
        return response()->json(['entries' => $entries]);
    }

    public function store(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'academic_session_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'programme_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'section' => ['nullable', 'string', 'max:20'],
            'periods_per_week' => ['nullable', 'integer', 'min:0', 'max:200'],
            'teaching_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'role' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $this->assertFacultyUser($institutionId, (int)$validated['user_id']);
        $this->assertRefs($institutionId, $validated);
        $entry = FacultyWorkloadEntry::create(['institution_id' => $institutionId] + $validated + [
            'periods_per_week' => $validated['periods_per_week'] ?? 0,
            'status' => $validated['status'] ?? 'active',
        ]);
        $entry->load(['user:id,name,email', 'academicSession:id,code,name', 'subject:id,code,name', 'programme:id,code,name']);
        $this->audit->record($request, 'academic.workload_created', 'academic', $entry, [], $entry->toArray(), null, $institutionId);
        return response()->json(['message' => 'Workload entry created.', 'entry' => $entry], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $entry = FacultyWorkloadEntry::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer'],
            'academic_session_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'programme_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'section' => ['nullable', 'string', 'max:20'],
            'periods_per_week' => ['nullable', 'integer', 'min:0', 'max:200'],
            'teaching_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'role' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        if (isset($validated['user_id'])) $this->assertFacultyUser($institutionId, (int)$validated['user_id']);
        $this->assertRefs($institutionId, $validated);
        $before = $entry->toArray();
        $entry->fill($validated)->save();
        $entry->load(['user:id,name,email', 'academicSession:id,code,name', 'subject:id,code,name', 'programme:id,code,name']);
        $this->audit->record($request, 'academic.workload_updated', 'academic', $entry, $before, $entry->toArray(), null, $institutionId);
        return response()->json(['message' => 'Workload entry updated.', 'entry' => $entry]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $entry = FacultyWorkloadEntry::where('institution_id', $institutionId)->findOrFail($id);
        $before = $entry->toArray();
        $entry->delete();
        $this->audit->record($request, 'academic.workload_deleted', 'academic', null, $before, ['id' => $id], null, $institutionId);
        return response()->json(['message' => 'Workload entry deleted.']);
    }

    private function assertFacultyUser(int $institutionId, int $userId): void
    {
        $ok = DB::table('user_role_assignments')
            ->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
            ->where('user_role_assignments.institution_id', $institutionId)
            ->where('user_role_assignments.user_id', $userId)
            ->where('user_role_assignments.status', 'active')
            ->whereIn('roles.code', ['faculty', 'hod', 'dean', 'registrar', 'examination_officer', 'institution_admin'])
            ->exists();
        abort_unless($ok, 422, 'The selected user is not an eligible staff member in this institution.');
    }

    private function assertRefs(int $institutionId, array $validated): void
    {
        if (!empty($validated['academic_session_id'])) abort_unless(AcademicSession::where('institution_id', $institutionId)->where('id', $validated['academic_session_id'])->exists(), 422, 'Invalid academic session.');
        if (!empty($validated['subject_id'])) abort_unless(Subject::where('institution_id', $institutionId)->where('id', $validated['subject_id'])->exists(), 422, 'Invalid subject.');
        if (!empty($validated['programme_id'])) abort_unless(Programme::where('institution_id', $institutionId)->where('id', $validated['programme_id'])->exists(), 422, 'Invalid programme.');
    }
}
