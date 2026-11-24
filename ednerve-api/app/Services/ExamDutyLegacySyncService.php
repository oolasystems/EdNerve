<?php

namespace App\Services;

use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyRoster;
use App\Models\ExamInvigilation;
use Illuminate\Support\Facades\DB;

class ExamDutyLegacySyncService
{
    /**
     * Rebuild the legacy projection from the latest published roster.
     *
     * Call this inside the transaction that publishes the roster.
     */
    public function syncPublishedRoster(ExamDutyRoster $roster): void
    {
        $this->assertLatestPublishedRoster($roster);

        $subjectIds = DB::table('exam_subjects')
            ->where('institution_id', $roster->institution_id)
            ->where('exam_id', $roster->exam_id)
            ->pluck('id');

        // Remove stale mappings, including mappings for subjects with no duties.
        if ($subjectIds->isNotEmpty()) {
            ExamInvigilation::where('institution_id', $roster->institution_id)
                ->whereIn('exam_subject_id', $subjectIds)
                ->delete();
        }

        $assignments = ExamDutyAssignment::where('institution_id', $roster->institution_id)
            ->where('roster_id', $roster->id)
            ->whereIn('exam_subject_id', $subjectIds)
            ->whereIn('status', ['assigned', 'accepted'])
            ->with('dutyRole:id,code')
            ->orderBy('id')
            ->get();

        foreach ($assignments as $assignment) {
            $this->upsertLegacyAssignment($assignment);
        }
    }

    /**
     * Synchronize a single staff response on the current published roster.
     */
    public function syncPublishedAssignment(
        ExamDutyRoster $roster,
        ExamDutyAssignment $assignment
    ): void {
        $this->assertLatestPublishedRoster($roster);

        abort_unless(
            (int) $assignment->roster_id === (int) $roster->id,
            409,
            'The assignment does not belong to the published roster.'
        );

        $assignment->loadMissing('dutyRole:id,code');

        if (in_array($assignment->status, ['assigned', 'accepted'], true)) {
            $this->upsertLegacyAssignment($assignment);
            return;
        }

        ExamInvigilation::where('institution_id', $roster->institution_id)
            ->where('exam_subject_id', $assignment->exam_subject_id)
            ->where('user_id', $assignment->user_id)
            ->delete();
    }

    private function assertLatestPublishedRoster(ExamDutyRoster $roster): void
    {
        abort_unless(
            $roster->status === 'published',
            409,
            'Only a published roster can be synchronized to legacy invigilations.'
        );

        $latestRosterId = ExamDutyRoster::where('institution_id', $roster->institution_id)
            ->where('exam_id', $roster->exam_id)
            ->orderByDesc('version')
            ->value('id');

        abort_unless(
            (int) $latestRosterId === (int) $roster->id,
            409,
            'Only the latest published roster can be synchronized.'
        );
    }

    private function upsertLegacyAssignment(ExamDutyAssignment $assignment): void
    {
        $legacyDuty = $assignment->dutyRole?->code === 'CHIEF_SUPERINTENDENT'
            ? 'chief'
            : 'invigilator';

        ExamInvigilation::updateOrCreate(
            [
                'institution_id' => $assignment->institution_id,
                'exam_subject_id' => $assignment->exam_subject_id,
                'user_id' => $assignment->user_id,
            ],
            [
                'duty' => $legacyDuty,
            ]
        );
    }
}
