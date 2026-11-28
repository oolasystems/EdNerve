<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicSeeder extends Seeder
{
    /**
     * Seed a starter B.Tech-CSE academic dataset per institution.
     * Idempotent via updateOrInsert; safe to re-run.
     */
    public function run(): void
    {
        $institutions = DB::table('institutions')->get();

        $subjects = [
            ['code' => 'CS301', 'name' => 'Data Structures and Algorithms', 'credits' => 4, 'subject_type' => 'theory'],
            ['code' => 'CS302', 'name' => 'Database Management Systems', 'credits' => 3, 'subject_type' => 'theory'],
            ['code' => 'CS303', 'name' => 'Operating Systems', 'credits' => 3, 'subject_type' => 'theory'],
            ['code' => 'CS304', 'name' => 'Computer Networks', 'credits' => 3, 'subject_type' => 'theory'],
            ['code' => 'CS305', 'name' => 'Software Engineering', 'credits' => 3, 'subject_type' => 'theory'],
            ['code' => 'CS306', 'name' => 'Web Technologies', 'credits' => 3, 'subject_type' => 'practical'],
            ['code' => 'CS307', 'name' => 'Introduction to Artificial Intelligence', 'credits' => 3, 'subject_type' => 'theory'],
            ['code' => 'CS308', 'name' => 'Cloud Computing Fundamentals', 'credits' => 3, 'subject_type' => 'theory'],
        ];

        $subjectIdByCode = [];

        foreach ($institutions as $institution) {
            $academicUnitId = DB::table('academic_units')
                ->where('institution_id', $institution->id)
                ->where('code', 'CSE')
                ->value('id');

            // --- Academic sessions: one active, one previous inactive ---
            DB::table('academic_sessions')->updateOrInsert(
                ['institution_id' => $institution->id, 'code' => '2026-27'],
                [
                    'name' => '2026-27 Academic Session',
                    'start_date' => '2026-07-01',
                    'end_date' => '2027-06-30',
                    'is_active' => true,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('academic_sessions')->updateOrInsert(
                ['institution_id' => $institution->id, 'code' => '2025-26'],
                [
                    'name' => '2025-26 Academic Session',
                    'start_date' => '2025-07-01',
                    'end_date' => '2026-06-30',
                    'is_active' => false,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // --- Subjects ---
            foreach ($subjects as $subject) {
                DB::table('subjects')->updateOrInsert(
                    ['institution_id' => $institution->id, 'code' => $subject['code']],
                    array_merge($subject, [
                        'academic_unit_id' => $academicUnitId,
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ])
                );
            }

            $subjectIds = DB::table('subjects')
                ->where('institution_id', $institution->id)
                ->whereIn('code', array_column($subjects, 'code'))
                ->pluck('id', 'code');

            // --- Programme ---
            DB::table('programmes')->updateOrInsert(
                ['institution_id' => $institution->id, 'code' => 'BTCSE'],
                [
                    'name' => 'B.Tech Computer Science and Engineering',
                    'academic_unit_id' => $academicUnitId,
                    'level' => 'UG',
                    'duration_semesters' => 8,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $programme = DB::table('programmes')
                ->where('institution_id', $institution->id)
                ->where('code', 'BTCSE')
                ->first(['id']);

            // --- Programme subjects: semester 3 and semester 4 ---
            $mappings = [
                ['code' => 'CS301', 'semester' => 3, 'requirement_type' => 'core', 'position' => 1],
                ['code' => 'CS302', 'semester' => 3, 'requirement_type' => 'core', 'position' => 2],
                ['code' => 'CS303', 'semester' => 3, 'requirement_type' => 'core', 'position' => 3],
                ['code' => 'CS304', 'semester' => 4, 'requirement_type' => 'core', 'position' => 1],
                ['code' => 'CS305', 'semester' => 4, 'requirement_type' => 'core', 'position' => 2],
                ['code' => 'CS306', 'semester' => 4, 'requirement_type' => 'core', 'position' => 3],
                ['code' => 'CS307', 'semester' => 4, 'requirement_type' => 'elective', 'position' => 4],
                ['code' => 'CS308', 'semester' => 4, 'requirement_type' => 'elective', 'position' => 5],
            ];

            foreach ($mappings as $mapping) {
                $subjectId = $subjectIds[$mapping['code']] ?? null;
                if (!$subjectId) {
                    continue;
                }

                DB::table('programme_subjects')->updateOrInsert(
                    [
                        'institution_id' => $institution->id,
                        'programme_id' => $programme->id,
                        'subject_id' => $subjectId,
                    ],
                    [
                        'semester' => $mapping['semester'],
                        'requirement_type' => $mapping['requirement_type'],
                        'position' => $mapping['position'],
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }


        }
    }
}
