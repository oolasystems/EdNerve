<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthController extends Controller
{
    use RequiresAccountType;

    private const WARNING_DISK_FREE_BYTES = 250_000_000; // 250 MB

    /**
     * Live probe of the services this application depends on.
     * No external network calls; each check is local and bounded.
     */
    public function show(Request $request)
    {
        $this->ensurePlatformAdmin($request);

        $checks = [
            $this->checkDatabase(),
            $this->checkCache(),
            $this->checkStorage(),
            $this->checkLogs(),
            $this->checkQueue(),
            $this->checkDisk(),
        ];

        $overall = 'ok';

        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $overall = 'critical';
                break;
            }

            if ($check['status'] === 'warning') {
                $overall = 'warning';
            }
        }

        return response()->json([
            'status' => $overall,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
            'info' => [
                'app_name' => config('app.name'),
                'environment' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'db_connection' => config('database.default'),
                'app_url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return $this->result('database', 'Database', 'ok', 'Connection established.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('database', 'Database', 'critical', 'Could not reach the database.');
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_probe_' . uniqid();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            $ok = $value === 'ok';

            return $this->result(
                'cache',
                'Cache',
                $ok ? 'ok' : 'warning',
                $ok ? 'Read/write succeeded.' : 'Wrote but could not read back the probe key.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('cache', 'Cache', 'critical', 'Cache store is not working.');
        }
    }

    private function checkStorage(): array
    {
        try {
            $file = 'health-probe-' . uniqid() . '.tmp';
            Storage::disk('local')->put($file, 'ok');
            $value = Storage::disk('local')->get($file);
            Storage::disk('local')->delete($file);

            $ok = $value === 'ok';

            return $this->result(
                'storage',
                'Storage',
                $ok ? 'ok' : 'warning',
                $ok ? 'Read/write on storage succeeded.' : 'Storage write or read-back failed.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('storage', 'Storage', 'critical', 'Local storage is not writable.');
        }
    }

    private function checkLogs(): array
    {
        $writable = is_writable(storage_path('logs'));

        return $this->result(
            'logs',
            'Log directory',
            $writable ? 'ok' : 'critical',
            $writable ? 'storage/logs is writable.' : 'storage/logs is not writable.'
        );
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');

        if ($driver === 'sync') {
            return $this->result('queue', 'Queue', 'ok', 'Queue runs synchronously (driver: sync).');
        }

        try {
            $hasJobs = \Illuminate\Support\Facades\Schema::hasTable('jobs');

            return $this->result(
                'queue',
                'Queue',
                $hasJobs ? 'ok' : 'warning',
                $hasJobs
                    ? "Driver '{$driver}' configured; jobs table present."
                    : "Driver '{$driver}' configured but jobs table is missing."
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('queue', 'Queue', 'warning', "Could not verify driver '{$driver}'.");
        }
    }

    private function checkDisk(): array
    {
        $free = disk_free_space(storage_path());

        if ($free === false) {
            return $this->result('disk', 'Disk space', 'warning', 'Could not determine free disk space.');
        }

        $status = $free >= self::WARNING_DISK_FREE_BYTES ? 'ok' : 'warning';

        return $this->result(
            'disk',
            'Disk space',
            $status,
            'Free space: ' . $this->humanBytes($free) . '.'
        );
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 2) . ' ' . $units[$unit];
    }

    private function result(string $key, string $label, string $status, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
        ];
    }
}
