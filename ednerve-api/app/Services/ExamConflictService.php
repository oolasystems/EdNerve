<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExaminationOrdinance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F4 conflict detection for an exam's scheduled subjects.
 * Returns a flat list of human-readable conflict descriptors; empty = clean.
 */
class ExamConflictService
{
    public function __construct(
        private readonly OrdinanceService $ordinances,
    ) {}

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public function checkForExam(Exam $exam, Collection $entries): array
    {
        $conflicts = [];
        $institutionId = $exam->institution_id;

        // Index entries by id for cross-checks.
        $byId = $entries->keyBy('id');

        // 1) Room double-booking: same room + same date + overlapping time span.
        $roomGroups = $entries->filter(fn ($e) => $e->exam_room_id)->groupBy(fn ($e) => $e->exam_room_id . '|' . $e->exam_date->toDateString());
        foreach ($roomGroups as $key => $group) {
            if ($group->count() < 2) {
                continue;
            }
            $items = $group->values();
            for ($i = 0; $i < $items->count(); $i++) {
                for ($j = $i + 1; $j < $items->count(); $j++) {
                    if ($this->slotsOverlap($items[$i], $items[$j])) {
                        $conflicts[] = $this->conflict(
                            'room_double_booked',
                            sprintf(
                                'Room clash on %s: "%s" and "%s" overlap (%s %s-%s vs %s-%s).',
                                $items[$i]->exam_date->toDateString(),
                                $items[$i]->subject->code ?? $items[$i]->subject_id,
                                $items[$j]->subject->code ?? $items[$j]->subject_id,
                                'room ' . $items[$i]->exam_room_id,
                                $items[$i]->start_time ?? '??',
                                $items[$i]->end_time ?? '??',
                                $items[$j]->start_time ?? '??',
                                $items[$j]->end_time ?? '??',
                            )
                        );
                    }
                }
            }
        }

        // 2) Inactive session referenced by a subject's slot.
        $sessionIds = $entries->pluck('exam_session_id')->filter()->unique()->values();
        if ($sessionIds->isNotEmpty()) {
            $inactive = DB::table('exam_sessions')
                ->whereIn('id', $sessionIds)
                ->where('active', false)
                ->pluck('code');
            foreach ($inactive as $code) {
                $conflicts[] = $this->conflict('session_inactive', "Exam session {$code} is marked inactive.");
            }
        }

        // 3) Room capacity vs enrolled cohort (roster for the exam's programme).
        $rosterCount = $this->rosterCount($institutionId, $exam);
        foreach ($entries as $entry) {
            if (!$entry->exam_room_id) {
                continue;
            }
            $capacity = (int) ($entry->room?->capacity ?? 0);
            if ($capacity > 0 && $rosterCount > $capacity) {
                $conflicts[] = $this->conflict(
                    'capacity_exceeded',
                    sprintf(
                        '%s: room capacity %d < enrolled cohort %d.',
                        $entry->subject->code ?? ('subject #' . $entry->subject_id),
                        $capacity,
                        $rosterCount
                    )
                );
            }
        }

        // 4) Invigilator double-duty: same user assigned to overlapping exams on the same date.
        $assignments = DB::table('exam_invigilations')
            ->join('exam_subjects', 'exam_subjects.id', '=', 'exam_invigilations.exam_subject_id')
            ->where('exam_subjects.institution_id', $institutionId)
            ->whereIn('exam_invigilations.exam_subject_id', $byId->keys())
            ->get([
                'exam_invigilations.user_id',
                'exam_invigilations.exam_subject_id',
                'exam_subjects.exam_date',
                'exam_subjects.start_time',
                'exam_subjects.end_time',
                'exam_subjects.exam_room_id',
            ])
            ->groupBy('user_id');

        foreach ($assignments as $userId => $rows) {
            $byDate = collect($rows)->groupBy(fn ($r) => (string) $r->exam_date);
            foreach ($byDate as $date => $dateRows) {
                foreach ($dateRows as $a) {
                    foreach ($dateRows as $b) {
                        if ($a->exam_subject_id >= $b->exam_subject_id) {
                            continue;
                        }
                        if ($this->timeSlotsOverlap($a->start_time, $a->end_time, $b->start_time, $b->end_time)) {
                            $conflicts[] = $this->conflict(
                                'invigilator_double_booked',
                                "User #{$userId} is assigned overlapping duties on {$date} (subjects #{$a->exam_subject_id} and #{$b->exam_subject_id})."
                            );
                        }
                    }
                }
            }
        }

        // 5) Duplicate subject in the same date + session slot.
        $dupKeys = $entries->groupBy(fn ($e) => $e->subject_id . '|' . $e->exam_date->toDateString() . '|' . ($e->exam_session_id ?? 'none'));
        foreach ($dupKeys as $key => $group) {
            if ($group->count() > 1) {
                $subjectId = $group->first()->subject_id;
                $conflicts[] = $this->conflict(
                    'duplicate_subject_slot',
                    "Subject #{$subjectId} is scheduled more than once for the same date/session."
                );
            }
        }

        // 6) Exam date outside the academic session window (soft warning).
        if ($exam->academic_session_id) {
            $session = DB::table('academic_sessions')->where('id', $exam->academic_session_id)->first();
            if ($session) {
                foreach ($entries as $entry) {
                    $date = $entry->exam_date->toDateString();
                    if ($date < $session->start_date || $date > $session->end_date) {
                        $conflicts[] = $this->conflict(
                            'outside_academic_session',
                            sprintf(
                                '%s (%s) falls outside the academic session window (%s to %s).',
                                $entry->subject->code ?? ('subject #' . $entry->subject_id),
                                $date,
                                $session->start_date,
                                $session->end_date
                            )
                        );
                    }
                }
            }
        }

        return $conflicts;
    }

    private function slotsOverlap(ExamSubject $a, ExamSubject $b): bool
    {
        return $this->timeSlotsOverlap($a->start_time, $a->end_time, $b->start_time, $b->end_time);
    }

    private function timeSlotsOverlap(?string $aStart, ?string $aEnd, ?string $bStart, ?string $bEnd): bool
    {
        // Missing times = cannot prove overlap; fall back to identical times only.
        if ($aStart === null || $aEnd === null || $bStart === null || $bEnd === null) {
            return false;
        }

        return $aStart < $bEnd && $aEnd > $bStart;
    }

    private function rosterCount(int $institutionId, Exam $exam): int
    {
        return (int) DB::table('student_profiles')
            ->where('institution_id', $institutionId)
            ->where('programme_id', $exam->programme_id)
            ->where('enrollment_status', 'current')
            ->count();
    }

    private function conflict(string $type, string $message): array
    {
        return ['type' => $type, 'message' => $message];
    }
}
