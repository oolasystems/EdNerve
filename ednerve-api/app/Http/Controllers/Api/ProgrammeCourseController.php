<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeCourseRequest;
use App\Http\Requests\UpdateProgrammeCourseRequest;
use App\Models\Programme;
use App\Models\ProgrammeCourse;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProgrammeCourseController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * Map a subject to a programme.
     */
    public function store(StoreProgrammeCourseRequest $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;
        $validated = $request->validated();

        $programme = Programme::where('institution_id', $institutionId)
            ->findOrFail($validated['programme_id']);
        $subject = Subject::where('institution_id', $institutionId)
            ->findOrFail($validated['subject_id']);

        abort_if(
            $programme->programmeCourses()
                ->where('subject_id', $subject->id)
                ->exists(),
            422,
            'This subject is already mapped to the programme.'
        );

        $position = $validated['position'] ?? (
            $programme->programmeCourses()
                ->where('semester', $validated['semester'])
                ->max('position') + 1
        );

        $course = ProgrammeCourse::create([
            'institution_id' => $institutionId,
            'programme_id' => $programme->id,
            'subject_id' => $subject->id,
            'semester' => $validated['semester'],
            'course_type' => $validated['course_type'] ?? 'core',
            'position' => $position,
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'programme_course.created',
            module: 'institution',
            subject: $course->load('subject'),
            newValues: $course->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject mapped to programme successfully.',
            'assignment' => $course->load('subject'),
        ], 201);
    }

    /**
     * Update semester/type/position/status of a mapping.
     */
    public function update(UpdateProgrammeCourseRequest $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $course = ProgrammeCourse::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $course->toArray();
        $course->update($request->validated());

        $this->audit->record(
            $request,
            event: 'programme_course.updated',
            module: 'institution',
            subject: $course,
            oldValues: $before,
            newValues: $course->fresh()?->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Course mapping updated successfully.',
            'assignment' => $course->fresh(['subject']),
        ]);
    }

    /**
     * Remove a subject mapping from a programme.
     */
    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $course = ProgrammeCourse::where('institution_id', $institutionId)
            ->findOrFail($id);

        $before = $course->load('subject')->toArray();
        $course->delete();

        $this->audit->record(
            $request,
            event: 'programme_course.deleted',
            module: 'institution',
            subject: $course,
            oldValues: $before,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Subject removed from programme.',
        ]);
    }
}
