<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceCredential;
use App\Services\DevicePresenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BenchmarkPresenceCommand extends Command
{
    protected $signature = 'monitor:benchmark-presence {--devices=100 : Number of devices to benchmark}';
    protected $description = 'Benchmark presence tracking, heartbeat processing, and MySQL write frequency across device tiers';

    public function handle(DevicePresenceService $presenceService): int
    {
        $targetDevices = (int) $this->option('devices');
        $tiers = array_filter([10, 20, 40, 60, 100], fn ($n) => $n <= $targetDevices);
        if (!in_array($targetDevices, $tiers)) {
            $tiers[] = $targetDevices;
            sort($tiers);
        }

        $this->info("===============================================================");
        $this->info(" RemoteMonitor Phase 2 - Device Scale & Performance Benchmark ");
        $this->info("===============================================================");
        $this->line("Target tiers: " . implode(', ', $tiers) . " devices");
        $this->line("Database: MySQL 8+ | Cache: local file cache | PHP: " . PHP_VERSION);
        $this->newLine();

        $rows = [];

        foreach ($tiers as $tier) {
            $result = $this->benchmarkTier($tier, $presenceService);
            $rows[] = [
                $tier . ' devices',
                $result['heartbeat_time_ms'] . ' ms',
                $result['avg_hb_latency_ms'] . ' ms',
                $result['hb_db_writes'],
                $result['sweep_time_ms'] . ' ms',
                $result['auth_storm_time_ms'] . ' ms',
                $result['memory_peak_mb'] . ' MB',
            ];
        }

        $this->table(
            ['Device Tier', 'Total HB Time', 'Avg HB Latency', 'MySQL Writes (HB)', 'Sweeper Time', 'Reconnect Storm', 'Peak Memory'],
            $rows
        );

        $this->info("Benchmark completed successfully.");
        return self::SUCCESS;
    }

    private function benchmarkTier(int $count, DevicePresenceService $presenceService): array
    {
        // 1. Prepare simulated device instances
        $devices = [];
        $tokens = [];
        for ($i = 1; $i <= $count; $i++) {
            $ident = sprintf('BENCH-%03d', $i);
            $device = new Device([
                'uuid' => (string) Str::uuid(),
                'device_identifier' => $ident,
                'name' => "Bench Display {$i}",
                'location' => "Ward {$i}",
                'status' => 'online',
                'view_mode' => 'single',
                'cycle_interval_seconds' => 15,
                'last_seen_at' => now(),
            ]);
            $device->id = 90000 + $i;
            $device->exists = true;
            $devices[] = $device;
            $tokens[] = 'rmt_live_' . bin2hex(random_bytes(32));
        }

        // 2. Measure steady-state heartbeat processing
        DB::flushQueryLog();
        DB::enableQueryLog();

        $memStart = memory_get_peak_usage(true);
        $hbStart = microtime(true);

        foreach ($devices as $d) {
            $presenceService->recordHeartbeat($d, [
                'battery' => 92,
                'isCharging' => true,
                'network' => 'wifi',
            ]);
        }

        $hbDurationMs = round((microtime(true) - $hbStart) * 1000, 2);
        $avgHbLatency = round($hbDurationMs / $count, 3);

        $queries = DB::getQueryLog();
        $writeQueries = array_filter($queries, function ($q) {
            $sql = strtolower($q['query']);
            return str_starts_with($sql, 'insert') || str_starts_with($sql, 'update') || str_starts_with($sql, 'delete');
        });
        $hbDbWrites = count($writeQueries);

        // 3. Measure Sweeper Performance
        $sweepStart = microtime(true);
        foreach ($devices as $d) {
            $cached = Cache::store('file')->get($presenceService->getCacheKey($d->id));
            $state = $presenceService->evaluatePresenceState($cached['timestamp'] ?? null);
        }
        $sweepDurationMs = round((microtime(true) - $sweepStart) * 1000, 2);

        // 4. Measure Reconnect Storm (Simulated channel auth HMAC calculations)
        $appKey = env('REVERB_APP_KEY', 'bench_key');
        $appSecret = env('REVERB_APP_SECRET', 'bench_secret_very_long');
        $authStart = microtime(true);

        foreach ($devices as $d) {
            $socketId = sprintf('%d.%d', rand(10000, 99999), rand(10000, 99999));
            $channelName = 'private-device.' . $d->uuid;
            $sig = hash_hmac('sha256', "{$socketId}:{$channelName}", $appSecret);
            $auth = "{$appKey}:{$sig}";
        }
        $authStormMs = round((microtime(true) - $authStart) * 1000, 2);

        $peakMemMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

        // Cleanup test cache keys
        foreach ($devices as $d) {
            Cache::store('file')->forget($presenceService->getCacheKey($d->id));
        }

        return [
            'heartbeat_time_ms' => $hbDurationMs,
            'avg_hb_latency_ms' => $avgHbLatency,
            'hb_db_writes' => $hbDbWrites,
            'sweep_time_ms' => $sweepDurationMs,
            'auth_storm_time_ms' => $authStormMs,
            'memory_peak_mb' => $peakMemMb,
        ];
    }
}
