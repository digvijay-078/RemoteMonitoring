<?php

namespace App\Console\Commands;

use App\Services\DevicePresenceService;
use Illuminate\Console\Command;

class SweepPresenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:sweep-presence {--daemon : Run continuously as a lightweight background daemon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate device presence records and transition stale devices (online -> warning -> offline)';

    /**
     * Execute the console command.
     */
    public function handle(DevicePresenceService $presenceService): int
    {
        $isDaemon = $this->option('daemon');

        if ($isDaemon) {
            $this->info('[' . now()->toDateTimeString() . '] Presence sweeper daemon running (3s high-frequency interval)...');

            while (true) {
                try {
                    $results = $presenceService->sweepPresence();

                    if ($results['transitions_count'] > 0) {
                        $this->line(sprintf(
                            '[%s] Swept %d devices. %d transitions detected.',
                            now()->toDateTimeString(),
                            $results['scanned_devices'],
                            $results['transitions_count']
                        ));

                        foreach ($results['transitions'] as $t) {
                            $this->line("  -> Device {$t['identifier']} transitioned from {$t['from']} to {$t['to']}");
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error('Error during presence sweep: ' . $e->getMessage());
                }

                sleep(1);
            }
        }

        $results = $presenceService->sweepPresence();

        $this->info(sprintf(
            'Presence sweep completed: %d devices evaluated, %d state transitions.',
            $results['scanned_devices'],
            $results['transitions_count']
        ));

        return self::SUCCESS;
    }
}
