<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records security and business events.
 *
 * Design contract:
 *  - Never throws: audit logging must never break the request it observes.
 *  - Credential-like keys are stripped from recorded payloads.
 */
class AuditService
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'authorization',
        'api_key',
    ];

    /**
     * Record a security or business event.
     */
    public function record(
        Request $request,
        string $event,
        string $module,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?int $institutionId = null,
        ?int $actorUserId = null,
    ): ?AuditLog {
        try {
            $user = $request->user();
            $actorId = $actorUserId ?? $user?->id;

            $requestId = $request->header('X-Request-ID');
            if (!is_string($requestId) || !preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $requestId)) {
                $requestId = (string) Str::uuid();
            }

            $log = AuditLog::create([
                'institution_id' => $institutionId ?? $user?->institution_id ?? null,
                'actor_user_id' => $actorId,
                'actor_type' => $actorId ? 'user' : 'system',
                'event' => $event,
                'module' => $module,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'old_values' => $this->redact($oldValues),
                'new_values' => $this->redact($newValues),
                'ip_address' => $request->ip(),
                'request_id' => $requestId,
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            ]);

            $this->chainLog($log);

            return $log;
        } catch (\Throwable $exception) {
            // Audit logging must never break the request flow.
            report($exception);

            return null;
        }
    }

    /**
     * Canonical, order-stable representation of an audit entry for hashing.
     */
    public static function canonicalPayload(AuditLog $log, ?string $prevHash): string
    {
        $payload = [
            'id' => (int) $log->id,
            'created_at' => optional($log->created_at)->toDateTimeString(),
            'institution_id' => $log->institution_id,
            'actor_user_id' => $log->actor_user_id,
            'actor_type' => $log->actor_type,
            'event' => $log->event,
            'module' => $log->module,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id !== null ? (string) $log->subject_id : null,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'request_id' => $log->request_id,
            'user_agent' => $log->user_agent,
            'prev_hash' => $prevHash,
        ];

        return json_encode(self::ksortDeep($payload), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * Recursively ksort arrays so the canonical payload is order-stable
     * regardless of the caller's insertion order (PHP 8.5 removed JSON_SORT_KEYS).
     */
    public static function ksortDeep(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        ksort($value);

        $sorted = [];
        foreach ($value as $key => $item) {
            $sorted[$key] = self::ksortDeep($item);
        }

        return $sorted;
    }

    public static function computeEntryHash(AuditLog $log, ?string $prevHash): string
    {
        return hash('sha256', self::canonicalPayload($log, $prevHash));
    }

    /**
     * Append the entry to the global tamper-evident hash chain.
     * Failures degrade to unverifiable rows rather than broken requests.
     */
    private function chainLog(AuditLog $log): void
    {
        try {
            Cache::lock('audit:chain', 5)->block(3, function () use ($log) {
                $previous = AuditLog::whereNotNull('entry_hash')
                    ->orderByDesc('id')
                    ->first(['id', 'entry_hash']);

                $prevHash = $previous?->entry_hash;

                $log->prev_hash = $prevHash;
                $log->entry_hash = self::computeEntryHash($log, $prevHash);
                $log->save();
            });
        } catch (\Throwable $exception) {
            report($exception);
            // Row remains in the log without chain hashes; verification skips it.
        }
    }

    /**
     * Verify the global hash chain. Returns [valid, checked, firstBroken].
     */
    public static function verifyChain(): array
    {
        $checked = 0;
        $expectedPrev = null;

        foreach (AuditLog::whereNotNull('entry_hash')->orderBy('id')->cursor() as $log) {
            $expected = self::computeEntryHash($log, $expectedPrev);
            if (!hash_equals($expected, $log->entry_hash)) {
                return [false, $checked, ['id' => $log->id, 'created_at' => optional($log->created_at)->toDateTimeString()]];
            }
            $expectedPrev = $log->entry_hash;
            $checked++;
        }

        return [true, $checked, null];
    }

    /**
     * Remove credential-like keys recursively.
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redacted = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }
}
