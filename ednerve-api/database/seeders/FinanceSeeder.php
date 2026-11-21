<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceSeeder extends Seeder
{
    /**
     * Seed demo fee heads & structures per institution, and synthesize
     * mock dues for the seeded BTCSE students.
     * Idempotent.
     */
    public function run(): void
    {
        $institutions = DB::table('institutions')->get();

        $feeHeads = [
            ['code' => 'TUI', 'name' => 'Tuition Fees', 'description' => 'Annual tuition fee for the academic programme.'],
            ['code' => 'LIB', 'name' => 'Library Fees', 'description' => 'Library subscription and access fees.'],
            ['code' => 'SPT', 'name' => 'Sports Fees', 'description' => 'Sports and recreation fees.'],
            ['code' => 'DEV', 'name' => 'Development Fees', 'description' => 'Institutional development fees.'],
            ['code' => 'ITS', 'name' => 'IT Services Fees', 'description' => 'IT infrastructure and wifi fees.'],
            ['code' => 'EXM', 'name' => 'Examination Fees', 'description' => 'Examination and evaluation fees.'],
        ];

        foreach ($institutions as $institution) {
            $headIds = [];
            foreach ($feeHeads as $head) {
                DB::table('fee_heads')->updateOrInsert(
                    ['institution_id' => $institution->id, 'code' => $head['code']],
                    array_merge($head, [
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ])
                );
                $headIds[$head['code']] = DB::table('fee_heads')
                    ->where('institution_id', $institution->id)
                    ->where('code', $head['code'])
                    ->value('id');
            }

            // Fee structures: BTCSE 2026-27 session, annual frequency
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

            $programmeFeeSchedule = ['TUI' => 85000, 'LIB' => 1500, 'SPT' => 2000, 'DEV' => 10000, 'ITS' => 3000, 'EXM' => 2500];
            foreach ($programmeFeeSchedule as $code => $amount) {
                DB::table('fee_structures')->updateOrInsert(
                    [
                        'institution_id' => $institution->id,
                        'programme_id' => $programme->id,
                        'academic_session_id' => $session->id,
                        'fee_head_id' => $headIds[$code],
                        'frequency' => 'annual',
                    ],
                    [
                        'amount' => $amount,
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // Generate dues for each seeded student profile
            $students = DB::table('student_profiles')
                ->where('institution_id', $institution->id)
                ->get();

            foreach ($students as $student) {
                // Only for those enrolled in BTCSE for the seeded session
                foreach ($programmeFeeSchedule as $code => $amount) {
                    DB::table('student_fee_dues')->updateOrInsert(
                        [
                            'institution_id' => $institution->id,
                            'student_profile_id' => $student->id,
                            'academic_session_id' => $session->id,
                            'fee_head_id' => $headIds[$code],
                        ],
                        [
                            'programme_id' => $programme->id,
                            'amount' => $amount,
                            'paid_amount' => 0,
                            'due_date' => '2026-09-15',
                            'status' => 'pending',
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
