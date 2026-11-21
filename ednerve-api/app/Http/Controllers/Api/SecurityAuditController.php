<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;
use App\Services\SecurityAlertService;

class SecurityAuditController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly SecurityAlertService $alerts
    ) {}

    /**
     * Verify the tamper-evident audit hash chain and checkpoint signatures.
     * Institution admins and platform admins only; no raw entries are returned.
     */
    public function verify(Request $request)
    {
        abort_unless(
            $request->user()?->isInstitutionAdmin() || $request->user()?->isPlatformAdmin(),
            403,
            'Administrator access required.'
        );

        [$valid, $checked, $broken] = AuditService::verifyChain();

        if (!$valid) {
            $this->alerts->record(
                kind: 'audit.chain_broken',
                severity: 'critical',
                message: "Audit chain verification failed at entry #{$broken['id']}.",
                institutionId: $request->user()->institution_id,
                context: ['broken_at' => $broken, 'entries_verified' => $checked],
                ip: $request->ip(),
            );
        }

        $checkpoints = DB::table('audit_log_checkpoints')
            ->orderByDesc('for_date')
            ->limit(30)
            ->get()
            ->map(function ($c) {
                $payload = "{$c->for_date}|{$c->record_count}|{$c->last_entry_hash}";
                $expected = hash_hmac('sha256', $payload, config('app.key'));

                return [
                    'for_date' => $c->for_date,
                    'record_count' => (int) $c->record_count,
                    'last_entry_hash' => substr($c->last_entry_hash, 0, 16),
                    'signature_valid' => hash_equals($expected, $c->signature),
                ];
            });

        return response()->json([
            'chain_valid' => $valid,
            'entries_verified' => $checked,
            'broken_at' => $broken,
            'checkpoints' => $checkpoints,
            'checkpoints_count' => $checkpoints->count(),
        ]);
    }
}
