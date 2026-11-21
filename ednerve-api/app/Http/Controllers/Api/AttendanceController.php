<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSheet;
use App\Models\AttendanceRecord;
use App\Models\Programme;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /* =========================
     |  Shared authorization
     |========================= */

    /** Staff = institution admin or faculty-like (faculty / HOD / dean). */
    private function ensureStaff(Request $request): void
    {
        abort_unless(
            $request->user()?->isInstitutionAdmin() || $request->user()?->isFaculty(),
            403,
            'Staff access required.'
        );
    }

    /** Admin can mark any slot; a faculty member may only mark slots assigned to them. */
    private function ensureCanMark(Request $request, TimetableEntry $entry): void
    {
        if ($request->user()->isInstitutionAdmin()) {
            return;
        }
        abort_unless(
            $request->user()->isFaculty() && (int) $entry->faculty_user_id === (int) $request->user()->id,
            403,
            'You can only mark attendance for slots assigned to you.'
        );
    }

    private function isFacultyOnly(Request $request): bool
    {
        return !$request->user()->isInstitutionAdmin() && $request->user()->isFaculty();
    }

    private function assertStaffInInstitution(Request $request, int $userId): void
    {
        $isStaff = DB::table('user_role_assignments')
            ->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
            ->where('user_role_assignments.institution_id', $request->user()->institution_id)
            ->where('user_role_assignments.user_id', $userId)
            ->where('user_role_assignments.status', 'active')
            ->whereIn('roles.code', ['faculty', 'hod', 'dean'])
            ->exists();
        abort_unless($isStaff, 422, 'Faculty member does not belong to this institution in a faculty capacity.');
    }

    /* =========================
     |  Timetable
     |========================= */

    public function index(Request $request)
    {
        $this->ensureStaff($request);

        $institutionId = $request->user()->institution_id;

        $query = TimetableEntry::where('institution_id', $institutionId)
            ->with([
                'subject:id,code,name,subject_type,credits',
                'programme:id,code,name',
                'faculty:id,name,email',
            ])
            ->orderBy('day_of_week')
            ->orderBy('period_no');

        if ($programmeId = $request->query('programme_id')) {
            $query->where('programme_id', (int) $programmeId);
        }
        if ($semester = $request->query('semester')) {
            $query->where('semester', (int) $semester);
        }
        if ($section = $request->query('section')) {
            $query->where('section', $section);
        }
        // Faculty-only staff see the grid but can filter to their own slots.
        if ($this->isFacultyOnly($request) && $request->query('mine') === '1') {
            $query->where('faculty_user_id', $request->user()->id);
        }

        return response()->json([
            'timetable' => $query->get(),
        ]);
    }

    public function mine(Request $request)
    {
        $this->ensureStaff($request);

        $entries = TimetableEntry::where('institution_id', $request->user()->institution_id)
            ->where('faculty_user_id', $request->user()->id)
            ->with([
                'subject:id,code,name,subject_type,credits',
                'programme:id,code,name',
            ])
            ->orderBy('day_of_week')
            ->orderBy('period_no')
            ->get();

        return response()->json([
            'timetable' => $entries,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'programme_id' => ['required', 'integer'],
            'semester' => ['required', 'integer', 'min:1', 'max:12'],
            'section' => ['nullable', 'string', 'max:20'],
            'day_of_week' => ['required', 'integer', 'min:1', 'max:6'],
            'period_no' => ['required', 'integer', 'min:1', 'max:12'],
            'subject_id' => ['required', 'integer'],
            'faculty_user_id' => ['nullable', 'integer'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        Programme::where('institution_id', $institutionId)->findOrFail($validated['programme_id']);
        Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);
        if (!empty($validated['faculty_user_id'])) {
            $this->assertStaffInInstitution($request, (int) $validated['faculty_user_id']);
        }

        $slotTaken = TimetableEntry::where('institution_id', $institutionId)
            ->where('programme_id', $validated['programme_id'])
            ->where('semester', $validated['semester'])
            ->where('section', $validated['section'] ?? 'A')
            ->where('day_of_week', $validated['day_of_week'])
            ->where('period_no', $validated['period_no'])
            ->exists();
        abort_if($slotTaken, 422, 'That slot is already occupied for this programme.');

        $entry = TimetableEntry::create([
            'institution_id' => $institutionId,
            'programme_id' => $validated['programme_id'],
            'semester' => $validated['semester'],
            'section' => $validated['section'] ?? 'A',
            'day_of_week' => $validated['day_of_week'],
            'period_no' => $validated['period_no'],
            'subject_id' => $validated['subject_id'],
            'faculty_user_id' => $validated['faculty_user_id'] ?? null,
            'room' => $validated['room'] ?? null,
            'status' => 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'timetable.entry_created',
            module: 'institution',
            subject: $entry,
            newValues: $entry->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Timetable slot added.',
            'entry' => $entry->fresh(['subject', 'faculty:id,name,email', 'programme']),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $entry = TimetableEntry::where('institution_id', $institutionId)->findOrFail($id);

        $validated = $request->validate([
            'programme_id' => ['sometimes', 'integer'],
            'semester' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'section' => ['sometimes', 'string', 'max:20'],
            'day_of_week' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'period_no' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'subject_id' => ['sometimes', 'integer'],
            'faculty_user_id' => ['nullable', 'integer'],
            'room' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:active,archived'],
        ]);

        if (isset($validated['programme_id'])) {
            Programme::where('institution_id', $institutionId)->findOrFail($validated['programme_id']);
        }
        if (isset($validated['subject_id'])) {
            Subject::where('institution_id', $institutionId)->findOrFail($validated['subject_id']);
        }
        if (!empty($validated['faculty_user_id'])) {
            $this->assertStaffInInstitution($request, (int) $validated['faculty_user_id']);
        }

        // Slot uniqueness when slot-defining fields change
        $merged = array_merge($entry->only(['programme_id', 'semester', 'section', 'day_of_week', 'period_no']), $validated);
        $slotTaken = TimetableEntry::where('institution_id', $institutionId)
            ->where('id', '!=', $entry->id)
            ->where('programme_id', $merged['programme_id'])
            ->where('semester', $merged['semester'])
            ->where('section', $merged['section'])
            ->where('day_of_week', $merged['day_of_week'])
            ->where('period_no', $merged['period_no'])
            ->exists();
        abort_if($slotTaken, 422, 'That slot is already occupied for this programme.');

        $entry->update($validated);

        $this->audit->record(
            $request,
            event: 'timetable.entry_updated',
            module: 'institution',
            subject: $entry->fresh(),
            newValues: $validated,
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Timetable slot updated.',
            'entry' => $entry->fresh(['subject', 'faculty:id,name,email', 'programme']),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $entry = TimetableEntry::where('institution_id', $institutionId)->findOrFail($id);

        $hasSheets = AttendanceSheet::where('institution_id', $institutionId)
            ->where('timetable_entry_id', $entry->id)
            ->exists();

        abort_if($hasSheets, 409, 'Cannot delete a slot that has attendance records. Archive it instead.');

        $entry->delete();

        $this->audit->record(
            $request,
            event: 'timetable.entry_deleted',
            module: 'institution',
            subject: null,
            newValues: ['id' => $id],
            institutionId: $institutionId,
        );

        return response()->json(['message' => 'Timetable slot removed.']);
    }

    /* =========================
     |  Attendance
     |========================= */

    public function openSheet(Request $request)
    {
        $this->ensureStaff($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'timetable_entry_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $entry = TimetableEntry::where('institution_id', $institutionId)
            ->with(['subject:id,code,name,subject_type', 'programme:id,code,name'])
            ->findOrFail($validated['timetable_entry_id']);

        $this->ensureCanMark($request, $entry);

        $date = \Illuminate\Support\Carbon::parse($validated['date'])->toDateString();
        abort_if($date > now()->toDateString(), 422, 'Attendance cannot be marked for a future date.');

        $sheet = AttendanceSheet::firstOrCreate(
            [
                'institution_id' => $institutionId,
                'timetable_entry_id' => $entry->id,
                'date' => $date,
            ],
            ['marked_by' => $request->user()->id]
        );

        return response()->json([
            'sheet' => $sheet,
            'entry' => $entry,
            'roster' => $this->rosterWithStatus($institutionId, $entry, $sheet->id),
        ]);
    }

    public function showSheet(Request $request, int $sheetId)
    {
        $this->ensureStaff($request);

        $institutionId = $request->user()->institution_id;

        $sheet = AttendanceSheet::where('institution_id', $institutionId)
            ->with(['entry.subject:id,code,name,subject_type', 'entry.programme:id,code,name', 'entry.faculty:id,name,email'])
            ->findOrFail($sheetId);

        $this->ensureCanMark($request, $sheet->entry);

        return response()->json([
            'sheet' => $sheet,
            'entry' => $sheet->entry,
            'roster' => $this->rosterWithStatus($institutionId, $sheet->entry, $sheet->id),
        ]);
    }

    public function saveSheet(Request $request, int $sheetId)
    {
        $this->ensureStaff($request);

        $institutionId = $request->user()->institution_id;

        $sheet = AttendanceSheet::where('institution_id', $institutionId)
            ->with('entry')
            ->findOrFail($sheetId);

        $this->ensureCanMark($request, $sheet->entry);

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_profile_id' => ['required', 'integer'],
            'records.*.status' => ['required', 'in:present,absent,late'],
        ]);

        $validStudentIds = DB::table('student_profiles')
            ->where('institution_id', $institutionId)
            ->where('programme_id', $sheet->entry->programme_id)
            ->where('enrollment_status', 'current')
            ->whereIn('id', collect($validated['records'])->pluck('student_profile_id')->all())
            ->pluck('id')
            ->all();

        $records = collect($validated['records'])
            ->filter(fn ($r) => in_array((int) $r['student_profile_id'], $validStudentIds, true))
            ->values();

        DB::transaction(function () use ($institutionId, $sheet, $records) {
            $keepIds = [];
            foreach ($records as $record) {
                $attendance = AttendanceRecord::updateOrCreate(
                    [
                        'attendance_sheet_id' => $sheet->id,
                        'student_profile_id' => (int) $record['student_profile_id'],
                    ],
                    [
                        'institution_id' => $institutionId,
                        'status' => $record['status'],
                    ]
                );
                $keepIds[] = $attendance->id;
            }

            // Remove stale records for students no longer in the roster payload.
            AttendanceRecord::where('attendance_sheet_id', $sheet->id)
                ->whereNotIn('id', $keepIds ?: [0])
                ->delete();
        });

        $this->audit->record(
            $request,
            event: 'attendance.marked',
            module: 'institution',
            subject: $sheet->fresh(),
            newValues: ['sheet_id' => $sheet->id, 'records' => count($records)],
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Attendance saved.',
            'sheet' => $sheet->fresh(),
            'saved' => count($records),
        ]);
    }

    public function summary(Request $request)
    {
        $this->ensureStaff($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'programme_id' => ['required', 'integer'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:12'],
            'section' => ['nullable', 'string', 'max:20'],
        ]);

        $programme = Programme::where('institution_id', $institutionId)->findOrFail($validated['programme_id']);

        $section = $validated['section'] ?? 'A';

        $entriesQuery = TimetableEntry::where('institution_id', $institutionId)
            ->where('programme_id', $programme->id)
            ->where('section', $section)
            ->with('subject:id,code,name,subject_type');

        if (isset($validated['semester'])) {
            $entriesQuery->where('semester', (int) $validated['semester']);
        }
        // Faculty-only staff see only their own subjects.
        if ($this->isFacultyOnly($request)) {
            $entriesQuery->where('faculty_user_id', $request->user()->id);
        }

        $entries = $entriesQuery->get();
        if ($entries->isEmpty()) {
            return response()->json([
                'programme' => $programme,
                'section' => $section,
                'subjects' => [],
                'rows' => [],
            ]);
        }

        $sheets = AttendanceSheet::where('institution_id', $institutionId)
            ->whereIn('timetable_entry_id', $entries->pluck('id'))
            ->get();

        $sheetsByEntry = $sheets->groupBy('timetable_entry_id');
        $sheetToEntry = $sheets->pluck('timetable_entry_id', 'id');

        $records = AttendanceRecord::where('institution_id', $institutionId)
            ->whereIn('attendance_sheet_id', $sheets->pluck('id'))
            ->get()
            ->groupBy(fn ($r) => $r->attendance_sheet_id);

        // Which subjects were actually delivered (have ≥1 sheet)?
        $deliveredEntryIds = $sheets->pluck('timetable_entry_id')->unique();
        $subjects = $entries->whereIn('id', $deliveredEntryIds)
            ->unique('subject_id')
            ->map(fn ($e) => $e->subject)
            ->values();

        $students = DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.institution_id', $institutionId)
            ->where('student_profiles.programme_id', $programme->id)
            ->where('student_profiles.enrollment_status', 'current')
            ->orderBy('users.name')
            ->get([
                'student_profiles.id as profile_id',
                'student_profiles.application_number',
                'users.name',
                'users.email',
            ]);

        $rows = [];
        foreach ($students as $student) {
            $subjectStats = [];
            $totalAttended = 0;
            $totalTotal = 0;

            foreach ($entries->whereIn('id', $deliveredEntryIds) as $entry) {
                $key = $entry->subject_id;
                if (!isset($subjectStats[$key])) {
                    $subjectStats[$key] = ['attended' => 0, 'total' => 0];
                }
                foreach ($sheetsByEntry->get($entry->id, collect()) as $sheet) {
                    $record = $records->get($sheet->id, collect())
                        ->firstWhere('student_profile_id', (int) $student->profile_id);
                    if (!$record) {
                        continue;
                    }
                    $subjectStats[$key]['total']++;
                    $totalTotal++;
                    if (in_array($record->status, ['present', 'late'], true)) {
                        $subjectStats[$key]['attended']++;
                        $totalAttended++;
                    }
                }
            }

            $subjectPayload = [];
            foreach ($subjectStats as $subjectId => $stat) {
                $subjectPayload[] = [
                    'subject_id' => (int) $subjectId,
                    'attended' => $stat['attended'],
                    'total' => $stat['total'],
                    'percent' => $stat['total'] > 0 ? round($stat['attended'] / $stat['total'] * 100, 1) : 0.0,
                ];
            }

            $rows[] = [
                'student' => [
                    'id' => (int) $student->profile_id,
                    'application_number' => $student->application_number,
                    'name' => $student->name,
                    'email' => $student->email,
                ],
                'subjects' => $subjectPayload,
                'total' => [
                    'attended' => $totalAttended,
                    'total' => $totalTotal,
                    'percent' => $totalTotal > 0 ? round($totalAttended / $totalTotal * 100, 1) : 0.0,
                ],
            ];
        }

        return response()->json([
            'programme' => $programme,
            'section' => $section,
            'semester' => $validated['semester'] ?? null,
            'subjects' => $subjects,
            'rows' => $rows,
        ]);
    }

    /* =========================
     |  Roster helper
     |========================= */

    private function rosterWithStatus(int $institutionId, TimetableEntry $entry, int $sheetId): array
    {
        $students = DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.institution_id', $institutionId)
            ->where('student_profiles.programme_id', $entry->programme_id)
            ->where('student_profiles.enrollment_status', 'current')
            ->orderBy('users.name')
            ->get([
                'student_profiles.id as profile_id',
                'student_profiles.application_number',
                'users.name',
                'users.email',
            ]);

        $statuses = AttendanceRecord::where('attendance_sheet_id', $sheetId)
            ->pluck('status', 'student_profile_id');

        return $students->map(fn ($s) => [
            'student_profile_id' => (int) $s->profile_id,
            'application_number' => $s->application_number,
            'name' => $s->name,
            'email' => $s->email,
            'status' => isset($statuses[$s->profile_id]) ? $statuses[$s->profile_id] : null,
        ])->all();
    }
}
