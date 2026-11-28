<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seed demo timetable + past attendance for the BTCSE 2026-27 cohort.
 * Idempotent. Two sections are intentionally NOT created in MVP — section defaults to 'A'.
 */
class TimetableAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = DB::table('institutions')->get();

        foreach ($institutions as $institution) {
            $programme = DB::table('programmes')
                ->where('institution_id', $institution->id)
                ->where('code', 'BTCSE')
                ->first(['id']);
            if (!$programme) {
                continue;
            }

            $faculty = DB::table('user_role_assignments')
                ->join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
                ->where('user_role_assignments.institution_id', $institution->id)
                ->where('user_role_assignments.status', 'active')
                ->whereIn('roles.code', ['faculty', 'hod', 'dean'])
                ->orderBy('user_role_assignments.id')
                ->first(['user_role_assignments.user_id']);
            $facultyUserId = $faculty ? (int) $faculty->user_id : null;

            $subjects = DB::table('programme_subjects')
                ->join('subjects', 'subjects.id', '=', 'programme_subjects.subject_id')
                ->where('programme_subjects.programme_id', $programme->id)
                ->get(['subjects.id', 'subjects.code', 'programme_subjects.semester']);
            $subjectIds = $subjects->pluck('id', 'code')->all();
            $subjectSemester = $subjects->pluck('semester', 'code')->all();

            $slots = [
                // Sem 3 — weekly grid
                ['code' => 'CS301', 'day' => 1, 'period' => 1, 'room' => 'FET-304'],
                ['code' => 'CS302', 'day' => 1, 'period' => 2, 'room' => 'FET-304'],
                ['code' => 'CS301', 'day' => 3, 'period' => 1, 'room' => 'FET-304'],
                ['code' => 'CS303', 'day' => 3, 'period' => 3, 'room' => 'FET-305'],
                ['code' => 'CS302', 'day' => 5, 'period' => 1, 'room' => 'FET-304'],
                ['code' => 'CS303', 'day' => 5, 'period' => 4, 'room' => 'FET-305'],
                // Sem 4
                ['code' => 'CS304', 'day' => 2, 'period' => 1, 'room' => 'FET-306'],
                ['code' => 'CS305', 'day' => 4, 'period' => 1, 'room' => 'FET-307'],
                ['code' => 'CS306', 'day' => 6, 'period' => 1, 'room' => 'LAB-201'],
            ];

            $entryIds = [];
            foreach ($slots as $slot) {
                if (!isset($subjectIds[$slot['code']])) {
                    continue;
                }
                $this->seedEntry($institution->id, $programme->id, $subjectIds[$slot['code']], $subjectSemester[$slot['code']], $slot, $facultyUserId, $entryIds);
            }

            // Attendance history for Sem-3 entries: last 4 occurrences each.
            $today = now()->startOfDay();
            foreach ($slots as $slot) {
                if (!isset($subjectIds[$slot['code']]) || (int) $subjectSemester[$slot['code']] !== 3) {
                    continue;
                }
                for ($w = 4; $w >= 1; $w--) {
                    $date = $today->copy();
                    while ((int) $date->isoWeekday() !== $slot['day'] || $date >= $today) {
                        $date->subDay();
                    }
                    $date = $date->subWeeks($w - 1);

                    $sheetId = $this->seedSheet($institution->id, $entryIds[$slot['day'] . '-' . $slot['period']] ?? null, $date, $facultyUserId);
                    $this->seedRecords($institution->id, $programme->id, $sheetId, $w);
                }
            }
        }
    }

    private function seedEntry(int $institutionId, int $programmeId, int $subjectId, int $semester, array $slot, ?int $facultyUserId, array &$entryIds): void
    {
        DB::table('timetable_entries')->updateOrInsert(
            [
                'institution_id' => $institutionId,
                'programme_id' => $programmeId,
                'semester' => $semester,
                'section' => 'A',
                'day_of_week' => $slot['day'],
                'period_no' => $slot['period'],
            ],
            [
                'subject_id' => $subjectId,
                'faculty_user_id' => $facultyUserId,
                'room' => $slot['room'],
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        $entryIds[$slot['day'] . '-' . $slot['period']] = DB::table('timetable_entries')
            ->where('institution_id', $institutionId)
            ->where('programme_id', $programmeId)
            ->where('semester', $semester)
            ->where('section', 'A')
            ->where('day_of_week', $slot['day'])
            ->where('period_no', $slot['period'])
            ->value('id');
    }

    private function seedSheet(int $institutionId, ?int $entryId, Carbon $date, ?int $facultyUserId): ?int
    {
        if (!$entryId) {
            return null;
        }
        DB::table('attendance_sheets')->updateOrInsert(
            [
                'institution_id' => $institutionId,
                'timetable_entry_id' => $entryId,
                'date' => $date->toDateString(),
            ],
            [
                'marked_by' => $facultyUserId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        return DB::table('attendance_sheets')
            ->where('institution_id', $institutionId)
            ->where('timetable_entry_id', $entryId)
            ->where('date', $date->toDateString())
            ->value('id');
    }

    private function seedRecords(int $institutionId, int $programmeId, ?int $sheetId, int $weekOffset): void
    {
        if (!$sheetId) {
            return;
        }
        $students = DB::table('student_profiles')
            ->where('institution_id', $institutionId)
            ->where('programme_id', $programmeId)
            ->where('enrollment_status', 'current')
            ->orderBy('id')
            ->get(['id']);

        foreach ($students as $index => $student) {
            $hash = ($weekOffset * 31 + $index * 7 + $sheetId * 13) % 19;
            $status = 'present';
            if ($hash <= 1) {
                $status = 'absent';
            } elseif ($hash === 2) {
                $status = 'late';
            }

            DB::table('attendance_records')->updateOrInsert(
                [
                    'attendance_sheet_id' => $sheetId,
                    'student_profile_id' => $student->id,
                ],
                [
                    'institution_id' => $institutionId,
                    'status' => $status,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
