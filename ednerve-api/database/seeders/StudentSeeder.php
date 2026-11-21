<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentSeeder extends Seeder
{
    /**
     * Seed student profiles for seeded student users (one per demo
     * institution, tied to the seeded BTech-CSE programme + active session).
     * Idempotent via updateOrInsert.
     */
    public function run(): void
    {
        $institutions = ['AMU', 'JNU', 'JMI', 'JSPM', 'SPPU'];

        foreach ($institutions as $code) {
            $institution = DB::table('institutions')->where('code', $code)->first();
            if (!$institution) continue;

            // Pick the account that is assigned the student role for this institution.
            $studentUser = DB::table('users')
                ->join('user_role_assignments', 'users.id', '=', 'user_role_assignments.user_id')
                ->join('roles', 'user_role_assignments.role_id', '=', 'roles.id')
                ->where('users.institution_id', $institution->id)
                ->where('roles.code', 'student')
                ->where('user_role_assignments.institution_id', $institution->id)
                ->select('users.id', 'users.email')
                ->first();
            if (!$studentUser) continue;

            $programme = DB::table('programmes')
                ->where('institution_id', $institution->id)
                ->where('code', 'BTCSE')
                ->first(['id']);
            if (!$programme) continue;

            $session = DB::table('academic_sessions')
                ->where('institution_id', $institution->id)
                ->where('is_active', true)
                ->first(['id']);
            if (!$session) continue;

            DB::table('student_profiles')->updateOrInsert(
                [
                    'institution_id' => $institution->id,
                    'user_id' => $studentUser->id,
                ],
                [
                    'application_number' => strtoupper($code) . '-2026-DEMO0001',
                    'programme_id' => $programme->id,
                    'academic_session_id' => $session->id,
                    'enrollment_status' => 'current',
                    'admitted_on' => '2026-08-01',
                    'father_name' => 'Mr. Demo Father',
                    'mother_name' => 'Mrs. Demo Mother',
                    'dob' => '2007-03-15',
                    'gender' => 'Male',
                    'nationality' => 'Indian',
                    'category' => 'General',
                    'guardian_name' => 'Mr. Demo Father',
                    'guardian_relation' => 'Father',
                    'guardian_phone' => '9000000000',
                    'address_line' => 'Demo Hostel, Campus Road',
                    'city' => $institution->city ?? 'Aligarh',
                    'state' => $institution->state ?? 'Uttar Pradesh',
                    'pin_code' => '202001',
                    'remarks' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
