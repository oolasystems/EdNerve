<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InstitutionSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            AcademicUnitSeeder::class,
            UserSeeder::class,
            AcademicSeeder::class,
            AdmissionSeeder::class,
            StudentSeeder::class,
            FinanceSeeder::class,
            TimetableAttendanceSeeder::class,
            ExaminationSeeder::class,
            QuestionBankSeeder::class,
            PlatformSettingsSeeder::class,
            ExamDefaultsSeeder::class,
        ]);
    }
}
