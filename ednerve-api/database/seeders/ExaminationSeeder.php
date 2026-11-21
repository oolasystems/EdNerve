<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for the BTCSE 2026-27 end-semester examination.
 * Idempotent. Marks/results are left UNPUBLISHED so the publish flow can be demoed.
 */
class ExaminationSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = DB::table('institutions')->get();

        foreach ($institutions as $institution) {
            $programme = DB::table('programmes')
                ->where('institution_id', $institution->id)
                ->where('code', 'BTCSE')
                ->first(['id']);
            $session = DB::table('academic_sessions')
                ->where('institution_id', $institution->id)
                ->where('code', '2026-27')
                ->first(['id']);
            if (!$programme || !$session) {
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

            // Create or find the exam
            $examId = DB::table('exams')
                ->where('institution_id', $institution->id)
                ->where('title', 'BTCSE End-Semester Examination — December 2026')
                ->value('id');
            if (!$examId) {
                $examId = DB::table('exams')->insertGetId([
                    'institution_id' => $institution->id,
                    'title' => 'BTCSE End-Semester Examination — December 2026',
                    'kind' => 'end_sem',
                    'academic_session_id' => $session->id,
                    'programme_id' => $programme->id,
                    'semester' => 3,
                    'section' => 'A',
                    'instructions' => 'Carry your hall ticket and a valid photo ID. Mobile phones are not permitted in the examination hall.',
                    'status' => 'scheduled',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            // Exam subjects with configurable components
            $subjectDefs = [
                ['code' => 'CS301', 'date' => '2026-12-08', 'start' => '10:00', 'end' => '13:00', 'venue' => 'FET-201'],
                ['code' => 'CS302', 'date' => '2026-12-10', 'start' => '10:00', 'end' => '13:00', 'venue' => 'FET-202'],
                ['code' => 'CS303', 'date' => '2026-12-12', 'start' => '10:00', 'end' => '13:00', 'venue' => 'FET-203'],
            ];

            $subjectEntryIds = [];
            $componentIds = [];

            foreach ($subjectDefs as $def) {
                $subject = DB::table('subjects')
                    ->where('institution_id', $institution->id)
                    ->where('code', $def['code'])
                    ->first(['id']);
                if (!$subject) {
                    continue;
                }

                $entryId = DB::table('exam_subjects')
                    ->where('exam_id', $examId)
                    ->where('subject_id', $subject->id)
                    ->value('id');
                if (!$entryId) {
                    $entryId = DB::table('exam_subjects')->insertGetId([
                        'institution_id' => $institution->id,
                        'exam_id' => $examId,
                        'subject_id' => $subject->id,
                        'exam_date' => $def['date'],
                        'start_time' => $def['start'],
                        'end_time' => $def['end'],
                        'venue' => $def['venue'],
                        'pass_percent' => 40,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                } else {
                    DB::table('exam_subjects')->where('id', $entryId)->update([
                        'exam_date' => $def['date'],
                        'start_time' => $def['start'],
                        'end_time' => $def['end'],
                        'venue' => $def['venue'],
                        'updated_at' => now(),
                    ]);
                }
                $subjectEntryIds[$def['code']] = $entryId;

                foreach ([['name' => 'Internal', 'max' => 20, 'position' => 1], ['name' => 'Theory', 'max' => 80, 'position' => 2]] as $component) {
                    $componentId = DB::table('exam_components')
                        ->where('exam_subject_id', $entryId)
                        ->where('name', $component['name'])
                        ->value('id');
                    if (!$componentId) {
                        $componentId = DB::table('exam_components')->insertGetId([
                            'institution_id' => $institution->id,
                            'exam_subject_id' => $entryId,
                            'name' => $component['name'],
                            'max_marks' => $component['max'],
                            'position' => $component['position'],
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]);
                    }
                    $componentIds[$def['code'] . ':' . $component['name']] = $componentId;
                }
            }

            // Demo marks for the seeded student (present, fixed scores)
            $student = DB::table('student_profiles')
                ->where('institution_id', $institution->id)
                ->where('programme_id', $programme->id)
                ->orderBy('id')
                ->first(['id']);

            if ($student) {
                $demoMarks = [
                    'CS301:Internal' => 18,
                    'CS301:Theory' => 70,
                    'CS302:Internal' => 15,
                    'CS302:Theory' => 55,
                    'CS303:Internal' => 12,
                    'CS303:Theory' => 40,
                ];
                foreach ($demoMarks as $key => $marks) {
                    if (!isset($componentIds[$key])) {
                        continue;
                    }
                    DB::table('marks')->updateOrInsert(
                        [
                            'exam_component_id' => $componentIds[$key],
                            'student_profile_id' => $student->id,
                        ],
                        [
                            'institution_id' => $institution->id,
                            'status' => 'present',
                            'marks_obtained' => $marks,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            // Invigilation roster
            if ($facultyUserId) {
                foreach ([
                    ['code' => 'CS301', 'duty' => 'chief'],
                    ['code' => 'CS302', 'duty' => 'invigilator'],
                ] as $assignment) {
                    if (!isset($subjectEntryIds[$assignment['code']])) {
                        continue;
                    }
                    DB::table('exam_invigilations')->updateOrInsert(
                        [
                            'exam_subject_id' => $subjectEntryIds[$assignment['code']],
                            'user_id' => $facultyUserId,
                        ],
                        [
                            'institution_id' => $institution->id,
                            'duty' => $assignment['duty'],
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }

                // Demo revaluation request for CS303
                $cs303Subject = DB::table('subjects')
                    ->where('institution_id', $institution->id)
                    ->where('code', 'CS303')
                    ->first(['id']);
                if ($student && $cs303Subject) {
                    DB::table('revaluation_requests')->updateOrInsert(
                        [
                            'institution_id' => $institution->id,
                            'exam_id' => $examId,
                            'student_profile_id' => $student->id,
                            'subject_id' => $cs303Subject->id,
                        ],
                        [
                            'reason' => 'Student suspects a totaling error in the Theory component valuation.',
                            'fee_amount' => 100.00,
                            'status' => 'requested',
                            'requested_by' => $facultyUserId,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
