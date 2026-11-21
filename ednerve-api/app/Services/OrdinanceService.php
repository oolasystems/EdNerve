<?php

namespace App\Services;

use App\Models\AssessmentScheme;
use App\Models\ExaminationOrdinance;

/**
 * Result engine data source: grading bands and pass criteria live in the
 * examination_ordinances table (one is_default per institution). When an
 * institution has no ordinance rows, byte-compatible legacy bands are used
 * so existing deployments keep their historical pass/grade behaviour.
 */
class OrdinanceService
{
    public const LEGACY_BANDS = [
        ['min' => 90.0, 'grade' => 'O', 'points' => 10],
        ['min' => 80.0, 'grade' => 'A+', 'points' => 9],
        ['min' => 70.0, 'grade' => 'A', 'points' => 8],
        ['min' => 60.0, 'grade' => 'B+', 'points' => 7],
        ['min' => 50.0, 'grade' => 'B', 'points' => 6],
        ['min' => 40.0, 'grade' => 'C', 'points' => 5],
        ['min' => 0.0, 'grade' => 'F', 'points' => 0],
    ];

    public const LEGACY_CRITERIA = [
        'overall_min_pct' => 40.0,
        'external_min_pct' => 0.0,
        'condonation_pct' => 0.0,
        'max_backlogs' => null,
    ];

    public const DEFAULT_ATTENDANCE_THRESHOLD = 75.0;

    /**
     * The institution's default active ordinance (null => legacy constants).
     */
    public function resolveOrNull(?int $institutionId): ?ExaminationOrdinance
    {
        if ($institutionId === null) {
            return null;
        }

        return ExaminationOrdinance::where('institution_id', $institutionId)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function bandsFor(?ExaminationOrdinance $ordinance): array
    {
        $bands = $ordinance?->grading_bands;
        if (!is_array($bands) || count($bands) === 0) {
            return self::LEGACY_BANDS;
        }

        usort($bands, fn ($a, $b) => ($b['min'] ?? 0) <=> ($a['min'] ?? 0));

        return $bands;
    }

    public function criteriaFor(?ExaminationOrdinance $ordinance): array
    {
        $criteria = $ordinance?->pass_criteria;

        return is_array($criteria) ? array_merge(self::LEGACY_CRITERIA, $criteria) : self::LEGACY_CRITERIA;
    }

    public function attendanceThreshold(?ExaminationOrdinance $ordinance): float
    {
        return $ordinance ? (float) $ordinance->attendance_threshold : self::DEFAULT_ATTENDANCE_THRESHOLD;
    }

    public function gradeForBands(array $bands, float $percent): string
    {
        foreach ($bands as $band) {
            if ($percent >= (float) ($band['min'] ?? 0)) {
                return (string) ($band['grade'] ?? 'F');
            }
        }

        return 'F';
    }

    /**
     * Overall + external pass check. external_pct is null when no external
     * components exist (legacy behaviour) — external rule is then skipped.
     */
    public function passes(array $criteria, float $overallPercent, ?float $externalPercent): bool
    {
        if ($overallPercent < (float) ($criteria['overall_min_pct'] ?? 40)) {
            return false;
        }

        $externalMin = (float) ($criteria['external_min_pct'] ?? 0);
        if ($externalMin > 0 && $externalPercent !== null && $externalPercent < $externalMin) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the external-component flag map for an exam's programme/subject.
     * Subject scheme wins over programme scheme; returns [] when none exist.
     *
     * @return array<string, bool> lowercased component name => is_external
     */
    public function externalComponentFlags(?int $institutionId, ?int $subjectId, ?int $programmeId): array
    {
        if ($institutionId === null) {
            return [];
        }

        $schemes = AssessmentScheme::where('institution_id', $institutionId)
            ->where('status', 'active')
            ->where(function ($q) use ($subjectId, $programmeId) {
                if ($subjectId !== null) {
                    $q->orWhere(function ($q2) use ($subjectId) {
                        $q2->where('scope', 'subject')->where('subject_id', $subjectId);
                    });
                }
                if ($programmeId !== null) {
                    $q->orWhere(function ($q2) use ($programmeId) {
                        $q2->where('scope', 'programme')->where('programme_id', $programmeId);
                    });
                }
            })
            ->whereIn('scope', ['subject', 'programme'])
            ->with('components')
            ->get();

        $subjectScheme = $schemes->firstWhere('scope', 'subject');
        $programmeScheme = $schemes->firstWhere('scope', 'programme');
        $scheme = $subjectScheme ?? $programmeScheme;

        if (!$scheme) {
            return [];
        }

        $flags = [];
        foreach ($scheme->components as $component) {
            $flags[mb_strtolower(trim($component->name))] = (bool) $component->is_external;
        }

        return $flags;
    }

    /**
     * Attendance percentage for a student over an optional date window:
     * present + late count as attended. Date comes from the attendance sheet.
     */
    public function attendancePercent(int $institutionId, int $studentProfileId, ?string $from = null, ?string $to = null): ?float
    {
        $query = \DB::table('attendance_records')
            ->join('attendance_sheets', 'attendance_sheets.id', '=', 'attendance_records.attendance_sheet_id')
            ->where('attendance_records.institution_id', $institutionId)
            ->where('attendance_records.student_profile_id', $studentProfileId);

        if ($from !== null) {
            $query->whereDate('attendance_sheets.date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('attendance_sheets.date', '<=', $to);
        }

        $row = $query->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN attendance_records.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended
            ")
            ->first();

        if (!$row || (int) $row->total === 0) {
            return null;
        }

        return round(((int) $row->attended) / ((int) $row->total) * 100, 2);
    }
}
