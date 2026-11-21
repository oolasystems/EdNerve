<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditCheckpoint extends Command
{
    protected $signature = 'audit:checkpoint {date? : Date to seal (YYYY-MM-DD), defaults to yesterday}';

    protected $description = 'Create a signed checkpoint for a day of audit entries';

    public function handle(): int
    {
        $date = $this->argument('date') ?: now()->subDay()->toDateString();

        $entries = AuditLog::whereDate('created_at', $date)
            ->whereNotNull('entry_hash')
            ->orderBy('id')
            ->get(['id', 'entry_hash']);

        if ($entries->isEmpty()) {
            $this->warn("No hashed audit entries for {$date}; nothing to seal.");
            return self::SUCCESS;
        }

        $last = $entries->last();
        $count = $entries->count();
        $signature = hash_hmac('sha256', "{$date}|{$count}|{$last->entry_hash}", config('app.key'));

        DB::table('audit_log_checkpoints')->updateOrInsert(
            ['for_date' => $date],
            [
                'record_count' => $count,
                'last_entry_hash' => $last->entry_hash,
                'signature' => $signature,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info("Checkpoint sealed for {$date}: {$count} entries, last hash " . substr($last->entry_hash, 0, 16) . '…');

        return self::SUCCESS;
    }
}
