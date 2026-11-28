<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a small, deterministic elective basket for the sample BTCSE curriculum.
 * Every option is linked to a programme_subjects row, never directly to subjects.
 */
class ElectiveGroupSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DB::table('institutions')->get() as $institution) {
            $programme = DB::table('programmes')
                ->where('institution_id', $institution->id)
                ->where('code', 'BTCSE')
                ->first(['id']);

            if (!$programme) {
                continue;
            }

            DB::table('elective_groups')->updateOrInsert(
                [
                    'institution_id' => $institution->id,
                    'programme_id' => $programme->id,
                    'semester' => 4,
                    'code' => 'PE-IV',
                ],
                [
                    'name' => 'Professional Elective IV',
                    'required_choices' => 1,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $groupId = DB::table('elective_groups')
                ->where('institution_id', $institution->id)
                ->where('programme_id', $programme->id)
                ->where('semester', 4)
                ->where('code', 'PE-IV')
                ->value('id');

            $options = DB::table('programme_subjects')
                ->where('institution_id', $institution->id)
                ->where('programme_id', $programme->id)
                ->where('semester', 4)
                ->where('requirement_type', 'elective')
                ->get(['id']);

            foreach ($options as $option) {
                DB::table('elective_group_subjects')->updateOrInsert(
                    [
                        'institution_id' => $institution->id,
                        'elective_group_id' => $groupId,
                        'programme_subject_id' => $option->id,
                    ],
                    [
                        'programme_id' => $programme->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
