<?php

namespace Database\Seeders;

use App\Models\ExamSession;
use App\Models\ExamKind;
use App\Models\ExaminationOrdinance;
use App\Models\ExamDutyRole;
use Illuminate\Database\Seeder;

/**
 * Idempotent defaults for F4 infrastructure: FN/AN/EV sessions, the four
 * exam kinds, and two ordinances (legacy-compatible default + AMU-style
 * BTCSE ordinance with external-component pass requirement).
 *
 * Production note: run explicitly — php artisan db:seed --class=ExamDefaultsSeeder
 */
class ExamDefaultsSeeder extends Seeder
{
    /** Matches the historical hard-coded bands byte-for-byte. */
    private const LEGACY_BANDS = [
        ['min' => 90.0, 'grade' => 'O', 'points' => 10],
        ['min' => 80.0, 'grade' => 'A+', 'points' => 9],
        ['min' => 70.0, 'grade' => 'A', 'points' => 8],
        ['min' => 60.0, 'grade' => 'B+', 'points' => 7],
        ['min' => 50.0, 'grade' => 'B', 'points' => 6],
        ['min' => 40.0, 'grade' => 'C', 'points' => 5],
        ['min' => 0.0, 'grade' => 'F', 'points' => 0],
    ];

    public function run(): void
    {
        $institutions = \DB::table('institutions')->pluck('id');

        foreach ($institutions as $institutionId) {
            // Sessions
            foreach ([
                ['code' => 'FN', 'label' => 'Forenoon', 'start_time' => '09:30', 'end_time' => '12:30', 'sort_order' => 1],
                ['code' => 'AN', 'label' => 'Afternoon', 'start_time' => '14:00', 'end_time' => '17:00', 'sort_order' => 2],
                ['code' => 'EV', 'label' => 'Evening', 'start_time' => '18:00', 'end_time' => '21:00', 'sort_order' => 3],
            ] as $session) {
                ExamSession::firstOrCreate(
                    ['institution_id' => $institutionId, 'code' => $session['code']],
                    ['label' => $session['label'], 'start_time' => $session['start_time'], 'end_time' => $session['end_time'], 'sort_order' => $session['sort_order'], 'active' => true]
                );
            }

            // Exam kinds
            foreach ([
                ['code' => 'class_test', 'label' => 'Class Test / Unit Test', 'max_attempts' => 6],
                ['code' => 'mid_sem', 'label' => 'Mid Semester', 'max_attempts' => 2],
                ['code' => 'end_sem', 'label' => 'End Semester', 'max_attempts' => 1],
                ['code' => 'supplementary', 'label' => 'Supplementary / Backlog', 'max_attempts' => 3],
            ] as $kind) {
                ExamKind::firstOrCreate(
                    ['institution_id' => $institutionId, 'code' => $kind['code']],
                    ['label' => $kind['label'], 'max_attempts' => $kind['max_attempts'], 'active' => true]
                );
            }

            // LEGACY default ordinance (byte-compatible with previous behaviour)
            ExaminationOrdinance::firstOrCreate(
                ['institution_id' => $institutionId, 'code' => 'LEGACY'],
                [
                    'name' => 'Legacy grading (O–F)',
                    'description' => 'Default bands matching the original hard-coded behaviour.',
                    'grading_bands' => self::LEGACY_BANDS,
                    'pass_criteria' => ['overall_min_pct' => 40.0, 'external_min_pct' => 0.0, 'condonation_pct' => 0.0, 'max_backlogs' => null],
                    'attendance_threshold' => 75.00,
                    'is_default' => true,
                    'status' => 'active',
                ]
            );
            ExaminationOrdinance::where('institution_id', $institutionId)->where('is_default', true)->where('code', '!=', 'LEGACY')->update(['is_default' => false]);

            // AMU-style BTCSE ordinance (available, not forced as default)
            ExaminationOrdinance::firstOrCreate(
                ['institution_id' => $institutionId, 'code' => 'AMU-BTCSE'],
                [
                    'name' => 'AMU B.Tech (CSE) ordinance',
                    'description' => 'O ≥ 90 … C ≥ 40. Overall ≥ 40 and external (theory) ≥ 40 to pass. Attendance ≥ 75%.',
                    'grading_bands' => self::LEGACY_BANDS,
                    'pass_criteria' => ['overall_min_pct' => 40.0, 'external_min_pct' => 40.0, 'condonation_pct' => 0.0, 'max_backlogs' => 10],
                    'attendance_threshold' => 75.00,
                    'is_default' => false,
                    'status' => 'active',
                ]
            );

            // F5 duty roles (configurable per institution)
            foreach ([
                ['code' => 'INVIGILATOR', 'name' => 'Invigilator', 'weight' => 1, 'order' => 1],
                ['code' => 'SENIOR_INVIGILATOR', 'name' => 'Senior Invigilator', 'weight' => 2, 'order' => 2],
                ['code' => 'CHIEF_SUPERINTENDENT', 'name' => 'Chief Superintendent', 'weight' => 3, 'order' => 3],
                ['code' => 'OBSERVER', 'name' => 'Observer', 'weight' => 1, 'order' => 4],
                ['code' => 'FLYING_SQUAD', 'name' => 'Flying Squad', 'weight' => 1, 'order' => 5],
                ['code' => 'PAPER_CUSTODIAN', 'name' => 'Paper Custodian', 'weight' => 2, 'order' => 6],
                ['code' => 'RESULT_STEWARD', 'name' => 'Result Steward', 'weight' => 2, 'order' => 7],
                ['code' => 'EXTERNAL_CHECKER', 'name' => 'External Checker', 'weight' => 1, 'order' => 8],
            ] as $role) {
                ExamDutyRole::firstOrCreate(
                    ['institution_id' => $institutionId, 'code' => $role['code']],
                    [
                        'name' => $role['name'],
                        'description' => null,
                        'min_qualification' => null,
                        'duty_weight' => $role['weight'],
                        'active' => true,
                        'sort_order' => $role['order'],
                    ]
                );
            }
        }
    }
}
