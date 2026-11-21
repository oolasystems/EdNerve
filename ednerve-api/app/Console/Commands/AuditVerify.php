<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;

class AuditVerify extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verify the hash chain and signed checkpoints of the audit log';

    public function handle(): int
    {
        [$valid, $checked, $broken] = AuditService::verifyChain();

        if ($valid) {
            $this->info("Hash chain OK — {$checked} entries verified.");
        } else {
            $this->error("Hash chain BROKEN at entry #{$broken['id']} ({$broken['created_at']}).");
            $this->error("Entries verified before break: {$checked}");
        }

        $checkpoints = DB::table('audit_log_checkpoints')->orderBy('for_date')->get();
        $bad = 0;

        foreach ($checkpoints as $checkpoint) {
            $payload = "{$checkpoint->for_date}|{$checkpoint->record_count}|{$checkpoint->last_entry_hash}";
            $expected = hash_hmac('sha256', $payload, config('app.key'));
            $ok = hash_equals($expected, $checkpoint->signature);
            if (!$ok) {
                $bad++;
                $this->error("Checkpoint {$checkpoint->for_date}: signature INVALID");
            }
        }

        if ($checkpoints->isEmpty()) {
            $this->warn('No checkpoints sealed yet (run `php artisan audit:checkpoint`).');
        } else {
            $this->info('Checkpoints verified: ' . ($checkpoints->count() - $bad) . ' ok, ' . $bad . ' invalid.');
        }

        return $valid && $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
