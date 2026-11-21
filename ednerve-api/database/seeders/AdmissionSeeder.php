<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdmissionSeeder extends Seeder
{
    /**
     * Seed one open admission intake (BTech CSE, 2026-27) per institution.
     * Idempotent.
     */
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

            DB::table('admission_intakes')->updateOrInsert(
                [
                    'institution_id' => $institution->id,
                    'programme_id' => $programme->id,
                    'academic_session_id' => $session->id,
                ],
                [
                    'name' => 'BTech CSE 2026 Admission Intake',
                    'open_date' => '2026-06-01',
                    'close_date' => now()->addDays(45)->toDateString(),
                    'seat_capacity' => 120,
                    'status' => 'open',
                    'application_fee' => 1500,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
