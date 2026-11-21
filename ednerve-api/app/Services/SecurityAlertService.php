<?php

namespace App\Services;

use App\Models\SecurityAlert;

/**
 * Records security-relevant anomalies for administrator review.
 * Never throws — alerting must not break the request it observes.
 * Deduplicates so a noisy source cannot flood the panel.
 */
class SecurityAlertService
{
    public function record(
        string $kind,
        string $severity,
        string $message,
        ?int $institutionId = null,
        ?array $context = null,
        ?int $subjectUserId = null,
        ?string $ip = null,
    ): ?SecurityAlert {
        try {
            if ($this->isDuplicate($kind, $institutionId, $subjectUserId)) {
                return null;
            }

            return SecurityAlert::create([
                'institution_id' => $institutionId,
                'kind' => $kind,
                'severity' => $severity,
                'message' => $message,
                'context' => $context,
                'subject_user_id' => $subjectUserId,
                'ip_address' => $ip,
                'acknowledged_at' => null,
                'acknowledged_by' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Suppress repeat alerts of the same kind/subject within an hour.
     */
    private function isDuplicate(string $kind, ?int $institutionId, ?int $subjectUserId): bool
    {
        $query = SecurityAlert::query()
            ->where('kind', $kind)
            ->whereNull('acknowledged_at')
            ->where('created_at', '>=', now()->subHour());

        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        if ($subjectUserId) {
            $query->where('subject_user_id', $subjectUserId);
        } else {
            $query->whereNull('subject_user_id');
        }

        return $query->exists();
    }
}
