<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\LessonPlanEntry;
use App\Models\Programme;
use App\Models\Subject;
use App\Models\Syllabus;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LessonPlanController extends Controller
{
    use RequiresAccountType;

    public function __construct(private readonly AuditService $audit) {}

    private function canManageAny(Request $request): bool
    {
        $u = $request->user();
        return $u->isInstitutionAdmin() || $u->hasRole('hod') || $u->hasRole('dean') || $u->hasRole('registrar') || $u->isFaculty();
    }

    private function ensureManage(Request $request): void
    {
        abort_unless($this->canManageAny($request), 403, 'You are not allowed to manage lesson plans.');
    }

    public function index(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $q = LessonPlanEntry::where('institution_id', $institutionId)
            ->with(['subject:id,code,name', 'academicSession:id,code,name', 'faculty:id,name,email', 'programme:id,code,name', 'syllabus:id,version,title']);
        if ($request->filled('subject_id')) $q->where('subject_id', (int)$request->query('subject_id'));
        if ($request->filled('academic_session_id')) $q->where('academic_session_id', (int)$request->query('academic_session_id'));
        if ($request->filled('faculty_user_id')) $q->where('faculty_user_id', (int)$request->query('faculty_user_id'));
        if ($request->filled('status')) $q->where('status', (string)$request->query('status'));
        // Faculty can see all in lite mode; institution-wide view is useful. Keep simple.
        $entries = $q->orderBy('subject_id')->orderBy('unit_number')->orderBy('id')->get();
        return response()->json(['entries' => $entries]);
    }

    public function store(Request $request)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'academic_session_id' => ['nullable', 'integer'],
            'syllabus_id' => ['nullable', 'integer'],
            'faculty_user_id' => ['nullable', 'integer'],
            'programme_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'section' => ['nullable', 'string', 'max:20'],
            'unit_number' => ['nullable', 'string', 'max:30'],
            'topic' => ['required', 'string', 'max:255'],
            'planned_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'taught_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'planned_date' => ['nullable', 'date'],
            'taught_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'deferred'])],
            'remarks' => ['nullable', 'string', 'max:4000'],
        ]);
        $this->assertRefs($institutionId, $validated);
        $entry = LessonPlanEntry::create(['institution_id' => $institutionId, 'updated_by' => $request->user()->id] + $validated + [
            'planned_hours' => $validated['planned_hours'] ?? 0,
            'taught_hours' => $validated['taught_hours'] ?? 0,
            'status' => $validated['status'] ?? 'planned',
        ]);
        $entry->load(['subject:id,code,name', 'academicSession:id,code,name', 'faculty:id,name,email', 'programme:id,code,name', 'syllabus:id,version,title']);
        $this->audit->record($request, 'academic.lesson_plan_created', 'academic', $entry, [], $entry->toArray(), null, $institutionId);
        return response()->json(['message' => 'Lesson plan entry created.', 'entry' => $entry], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $entry = LessonPlanEntry::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'subject_id' => ['sometimes', 'integer'],
            'academic_session_id' => ['nullable', 'integer'],
            'syllabus_id' => ['nullable', 'integer'],
            'faculty_user_id' => ['nullable', 'integer'],
            'programme_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'section' => ['nullable', 'string', 'max:20'],
            'unit_number' => ['nullable', 'string', 'max:30'],
            'topic' => ['sometimes', 'string', 'max:255'],
            'planned_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'taught_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'planned_date' => ['nullable', 'date'],
            'taught_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'deferred'])],
            'remarks' => ['nullable', 'string', 'max:4000'],
        ]);
        $this->assertRefs($institutionId, $validated);
        $before = $entry->toArray();
        $entry->fill($validated);
        $entry->updated_by = $request->user()->id;
        $entry->save();
        $entry->load(['subject:id,code,name', 'academicSession:id,code,name', 'faculty:id,name,email', 'programme:id,code,name', 'syllabus:id,version,title']);
        $this->audit->record($request, 'academic.lesson_plan_updated', 'academic', $entry, $before, $entry->toArray(), null, $institutionId);
        return response()->json(['message' => 'Lesson plan entry updated.', 'entry' => $entry]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureManage($request);
        $institutionId = $request->user()->institution_id;
        $entry = LessonPlanEntry::where('institution_id', $institutionId)->findOrFail($id);
        $before = $entry->toArray();
        $entry->delete();
        $this->audit->record($request, 'academic.lesson_plan_deleted', 'academic', null, $before, ['id' => $id], null, $institutionId);
        return response()->json(['message' => 'Lesson plan entry deleted.']);
    }

    private function assertRefs(int $institutionId, array $validated): void
    {
        if (!empty($validated['subject_id'])) abort_unless(Subject::where('institution_id', $institutionId)->where('id', $validated['subject_id'])->exists(), 422, 'Invalid subject.');
        if (!empty($validated['academic_session_id'])) abort_unless(AcademicSession::where('institution_id', $institutionId)->where('id', $validated['academic_session_id'])->exists(), 422, 'Invalid academic session.');
        if (!empty($validated['syllabus_id'])) abort_unless(Syllabus::where('institution_id', $institutionId)->where('id', $validated['syllabus_id'])->exists(), 422, 'Invalid syllabus.');
        if (!empty($validated['programme_id'])) abort_unless(Programme::where('institution_id', $institutionId)->where('id', $validated['programme_id'])->exists(), 422, 'Invalid programme.');
        if (!empty($validated['faculty_user_id'])) {
            $ok = DB::table('user_role_assignments')->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
                ->where('user_role_assignments.institution_id', $institutionId)->where('user_role_assignments.user_id', $validated['faculty_user_id'])
                ->where('user_role_assignments.status', 'active')->whereIn('roles.code', ['faculty', 'hod', 'dean', 'registrar', 'examination_officer', 'institution_admin'])->exists();
            abort_unless($ok, 422, 'Invalid faculty/staff user.');
        }
    }
}
