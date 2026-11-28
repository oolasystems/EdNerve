<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SubjectRegistration;
use App\Models\ElectiveGroup;
use App\Models\ProgrammeSubject;
use App\Models\StudentProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubjectRegistrationController extends Controller
{
    use RequiresAccountType;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $institutionId = $request->user()->institution_id;
        $query = SubjectRegistration::where('institution_id', $institutionId)
            ->with([
                'studentProfile:id,user_id,programme_id,academic_session_id,enrollment_status',
                'studentProfile.user:id,name,email',
                'studentProfile.programme:id,code,name',
                'academicSession:id,code,name',
                'programmeSubject:id,programme_id,subject_id,semester,requirement_type',
                'programmeSubject.subject:id,code,name,credits',
                'programmeSubject.programme:id,code,name',
                'electiveGroup:id,code,name,programme_id,semester,required_choices',
            ]);

        if ($request->filled('student_profile_id')) {
            $query->where('student_profile_id', (int) $request->query('student_profile_id'));
        }
        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', (int) $request->query('academic_session_id'));
        }
        if ($request->filled('semester')) {
            $query->where('semester', (int) $request->query('semester'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $registrations = $query->orderByDesc('id')->get();
        return response()->json(['registrations' => $registrations]);
    }

    public function store(Request $request)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'student_profile_id' => ['required', 'integer'],
            'academic_session_id' => ['required', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.programme_subject_id' => ['required', 'integer'],
            'items.*.elective_group_id' => ['nullable', 'integer'],
        ]);

        $student = StudentProfile::where('institution_id', $institutionId)
            ->with('programme:id,code,name')
            ->findOrFail((int) $validated['student_profile_id']);
        $session = AcademicSession::where('institution_id', $institutionId)
            ->findOrFail((int) $validated['academic_session_id']);

        $created = [];
        $errors = [];

        DB::transaction(function () use ($request, $institutionId, $student, $session, $validated, &$created, &$errors) {
            foreach ($validated['items'] as $idx => $item) {
                $pcId = (int) $item['programme_subject_id'];
                $pc = ProgrammeSubject::where('institution_id', $institutionId)
                    ->with('subject:id,code,name')
                    ->find($pcId);
                if (!$pc) {
                    $errors[] = "Item {$idx}: invalid programme subject.";
                    continue;
                }
                if ($student->programme_id && (int) $pc->programme_id !== (int) $student->programme_id) {
                    $errors[] = "Item {$idx}: course does not belong to the student's programme.";
                    continue;
                }
                $semester = $validated['semester'] ?? $pc->semester;
                if ($semester !== null && (int) $pc->semester !== (int) $semester) {
                    $errors[] = "Item {$idx}: course semester does not match.";
                    continue;
                }

                $existing = SubjectRegistration::where('institution_id', $institutionId)
                    ->where('student_profile_id', $student->id)
                    ->where('academic_session_id', $session->id)
                    ->where('programme_subject_id', $pc->id)
                    ->first();
                if ($existing && $existing->status === 'registered') {
                    $errors[] = "Item {$idx}: student already registered for {$pc->subject?->code}.";
                    continue;
                }

                $electiveGroupId = null;
                if ($pc->requirement_type === 'elective') {
                    $group = $this->resolveElectiveGroup($institutionId, $pc, $item['elective_group_id'] ?? null);
                    if (!$group) {
                        $errors[] = "Item {$idx}: elective subject {$pc->subject?->code} is not in a valid elective group for this programme/semester.";
                        continue;
                    }
                    $electiveGroupId = $group->id;

                    // required_choices enforcement: count this student's registered elective picks in this group/session
                    $studentGroupCount = SubjectRegistration::where('institution_id', $institutionId)
                        ->where('student_profile_id', $student->id)
                        ->where('academic_session_id', $session->id)
                        ->where('elective_group_id', $group->id)
                        ->where('status', 'registered')
                        ->count();
                    // if re-registering same dropped record later handled above as not registered; new pick counts as +1
                    if ($studentGroupCount >= (int) $group->required_choices) {
                        $errors[] = "Item {$idx}: elective group {$group->code} already has {$studentGroupCount}/{$group->required_choices} required choice(s) selected for this student.";
                        continue;
                    }

                }

                $data = [
                    'institution_id' => $institutionId,
                    'student_profile_id' => $student->id,
                    'academic_session_id' => $session->id,
                    'programme_subject_id' => $pc->id,
                    'elective_group_id' => $electiveGroupId,
                    'semester' => $pc->semester,
                    'status' => 'registered',
                    'registered_at' => now(),
                    'registered_by' => $request->user()->id,
                ];

                if ($existing) {
                    $before = $existing->toArray();
                    $existing->fill($data);
                    $existing->save();
                    $reg = $existing;
                    $this->audit->record($request, 'subject_registration.updated', 'academic', $reg, $before, $reg->toArray(), null, $institutionId);
                } else {
                    $reg = SubjectRegistration::create($data);
                    $this->audit->record($request, 'subject_registration.created', 'academic', $reg, [], $reg->toArray(), null, $institutionId);
                }
                $created[] = $reg->id;
            }

            if (!empty($errors) && empty($created)) {
                // nothing created, abort with validation-style message
                abort(422, implode(' ', $errors));
            }
        });

        $registrations = SubjectRegistration::whereIn('id', $created)
            ->with([
                'academicSession:id,code,name',
                'programmeSubject.subject:id,code,name,credits',
                'programmeSubject.programme:id,code,name',
                'electiveGroup:id,code,name',
            ])->get();

        $message = count($created) . ' course(s) registered.';
        if (!empty($errors)) {
            $message .= ' Some items were skipped: ' . implode(' ', $errors);
        }

        return response()->json(['message' => $message, 'registrations' => $registrations, 'errors' => $errors], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;
        $reg = SubjectRegistration::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['registered', 'dropped', 'completed'])],
        ]);
        $before = $reg->toArray();
        $reg->status = $validated['status'];
        $reg->save();
        $this->audit->record($request, 'subject_registration.status_updated', 'academic', $reg, $before, $reg->toArray(), null, $institutionId);
        $reg->load(['academicSession:id,code,name', 'programmeSubject.subject:id,code,name,credits', 'electiveGroup:id,code,name']);
        return response()->json(['message' => 'Course registration updated.', 'registration' => $reg]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);
        $institutionId = $request->user()->institution_id;
        $reg = SubjectRegistration::where('institution_id', $institutionId)->findOrFail($id);
        $before = $reg->toArray();
        $reg->delete();
        $this->audit->record($request, 'subject_registration.deleted', 'academic', null, $before, ['id' => $id], null, $institutionId);
        return response()->json(['message' => 'Subject registration removed.']);
    }

    private function resolveElectiveGroup(int $institutionId, ProgrammeSubject $pc, $requestedGroupId): ?ElectiveGroup
    {
        $groups = ElectiveGroup::where('institution_id', $institutionId)
            ->where('programme_id', $pc->programme_id)
            ->where('semester', $pc->semester)
            ->where('status', 'active')
            ->whereHas('programmeSubjects', function ($q) use ($pc) {
                $q->where('programme_subjects.id', $pc->id);
            })
            ->get();

        if ($requestedGroupId) {
            return $groups->firstWhere('id', (int) $requestedGroupId);
        }
        if ($groups->count() === 1) {
            return $groups->first();
        }
        return null; // ambiguous or none
    }
}
