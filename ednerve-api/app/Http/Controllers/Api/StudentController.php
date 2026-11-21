<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\StudentProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List students enrolled at the caller's institution.
     * Filters: ?q=, ?programme_id=, ?academic_session_id=, ?status=
     */
    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $query = StudentProfile::where('institution_id', $institutionId)
            ->with(['user:id,name,email,phone,status', 'programme:id,code,name,level,duration_semesters', 'academicSession:id,code,name'])
            ->orderBy('admitted_on', 'desc');

        if ($q = trim((string) $request->query('q'))) {
            $query->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"))
                ->orWhere('application_number', 'like', "%{$q}%");
        }

        if ($programmeId = $request->query('programme_id')) {
            $query->where('programme_id', (int) $programmeId);
        }

        if ($sessionId = $request->query('academic_session_id')) {
            $query->where('academic_session_id', (int) $sessionId);
        }

        if ($status = $request->query('status')) {
            $query->where('enrollment_status', $status);
        }

        return response()->json(
            $query->paginate((int) $request->query('per_page', 20) ?: 20)
        );
    }

    public function show(Request $request, int $id)
    {
        $student = StudentProfile::where(
            'institution_id',
            $request->user()->institution_id
        )
            ->with([
                'user:id,name,email,phone,status,last_login_at',
                'programme:id,code,name,level,duration_semesters',
                'academicSession:id,code,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'student' => $student,
        ]);
    }

    public function update(UpdateStudentProfileRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $student = StudentProfile::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $student->toArray();
        $student->update($request->validated());

        $this->audit->record(
            $request,
            event: 'student_profile.updated',
            module: 'institution',
            subject: $student,
            oldValues: $before,
            newValues: $student->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Student profile updated.',
            'student' => $student->fresh([
                'user', 'programme', 'academicSession',
            ]),
        ]);
    }

    /**
     * Change enrollment state (current | leave | withheld | graduated | terminated).
     */
    public function updateStatus(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'enrollment_status' => ['required', 'in:current,leave,withheld,graduated,terminated'],
            'left_on' => ['nullable', 'date', 'after_or_equal:admitted_on'],
            'remarks' => ['nullable', 'string'],
        ]);

        $student = StudentProfile::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $student->toArray();

        $student->update([
            'enrollment_status' => $validated['enrollment_status'],
            'left_on' => $validated['left_on'] ?? $student->left_on,
            'remarks' => $validated['remarks'] ?? $student->remarks,
        ]);

        $this->audit->record(
            $request,
            event: 'student_profile.status_changed',
            module: 'institution',
            subject: $student,
            oldValues: ['enrollment_status' => $before['enrollment_status']],
            newValues: ['enrollment_status' => $validated['enrollment_status']],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Enrollment status updated.',
            'student' => $student->fresh(),
        ]);
    }
}
