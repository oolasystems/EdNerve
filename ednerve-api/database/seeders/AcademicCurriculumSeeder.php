<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds illustrative subject components, one prerequisite, and draft syllabi.
 * These are editable sample records, not institution-approved curriculum.
 */
class AcademicCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DB::table('institutions')->get() as $institution) {
            $institutionId = (int) $institution->id;
            $subjects = DB::table('subjects')
                ->where('institution_id', $institutionId)
                ->get(['id', 'code', 'name', 'subject_type', 'credits']);

            foreach ($subjects as $subject) {
                DB::table('subject_components')->updateOrInsert(
                    ['institution_id' => $institutionId, 'subject_id' => $subject->id, 'kind' => $subject->subject_type],
                    [
                        'credits' => (int) $subject->credits,
                        'hours_per_week' => max(1, (int) $subject->credits),
                        'max_marks' => 100,
                        'position' => 1,
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $cs301 = $subjects->firstWhere('code', 'CS301');
            $cs303 = $subjects->firstWhere('code', 'CS304');
            if ($cs301 && $cs303 && $cs301->id !== $cs303->id) {
                DB::table('subject_prerequisites')->updateOrInsert(
                    [
                        'institution_id' => $institutionId,
                        'subject_id' => $cs303->id,
                        'prerequisite_subject_id' => $cs301->id,
                    ],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            $sessionId = DB::table('academic_sessions')
                ->where('institution_id', $institutionId)
                ->where('code', '2026-27')
                ->value('id');

            $authorId = DB::table('users')
                ->where('institution_id', $institutionId)
                ->orderBy('id')
                ->value('id');

            if (!$authorId) {
                continue;
            }

            foreach ($subjects as $subject) {
                DB::table('syllabi')->updateOrInsert(
                    [
                        'institution_id' => $institutionId,
                        'subject_id' => $subject->id,
                        'version' => '2026.1',
                    ],
                    [
                        'academic_session_id' => $sessionId,
                        'title' => $subject->code . ' — ' . $subject->name,
                        'status' => 'draft',
                        'author_id' => $authorId,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $syllabusId = DB::table('syllabi')
                    ->where('institution_id', $institutionId)
                    ->where('subject_id', $subject->id)
                    ->where('version', '2026.1')
                    ->value('id');

                DB::table('syllabus_units')->updateOrInsert(
                    ['institution_id' => $institutionId, 'syllabus_id' => $syllabusId, 'unit_number' => '1'],
                    [
                        'position' => 1,
                        'title' => 'Foundations and Core Concepts',
                        'topics' => 'Introductory concepts, terminology, and foundational principles.',
                        'learning_outcomes' => 'Explain the core concepts and apply them to introductory problems.',
                        'prescribed_hours' => 8,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
