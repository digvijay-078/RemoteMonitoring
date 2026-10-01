<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceAuditLog;
use App\Models\WebRtcSession;
use Illuminate\Console\Command;

class SweepStaleSessions extends Command
{
    protected $signature = 'webrtc:sweep-sessions {--timeout=120 : Timeout in seconds for initiating sessions}';
    protected $description = 'Garbage collect stale or orphan WebRTC streaming sessions';

    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $initiatingCutoff = now()->subSeconds($timeout);
        $connectedCutoff = now()->subMinutes(5);

        $sweptCount = 0;

        // 1. Terminate orphaned 'initiating' sessions that never completed WebRTC negotiation
        $staleInitiating = WebRtcSession::where('status', 'initiating')
            ->where('started_at', '<', $initiatingCutoff)
            ->get();

        foreach ($staleInitiating as $session) {
            $session->update([
                'status' => 'failed',
                'ended_at' => now(),
                'termination_reason' => 'Signalling negotiation timeout',
            ]);

            DeviceAuditLog::create([
                'device_id' => $session->tablet_id,
                'event_type' => 'webrtc_session_failed',
                'severity' => 'warning',
                'details' => [
                    'session_id' => $session->session_id,
                    'reason' => 'Signalling negotiation timeout swept by garbage collector',
                ],
            ]);

            $sweptCount++;
        }

        // 2. Terminate 'connected' sessions where desktop or tablet is offline
        $activeSessions = WebRtcSession::where('status', 'connected')->with(['desktop', 'tablet'])->get();
        foreach ($activeSessions as $session) {
            $isOrphan = false;
            $reason = null;

            if (!$session->desktop || $session->desktop->status === 'disabled') {
                $isOrphan = true;
                $reason = 'Desktop source deleted or disabled';
            } elseif (!$session->tablet || $session->tablet->status === 'disabled') {
                $isOrphan = true;
                $reason = 'Tablet viewer deleted or disabled';
            } elseif ($session->desktop->status === 'offline' && $session->desktop->last_seen_at && $session->desktop->last_seen_at < $connectedCutoff) {
                $isOrphan = true;
                $reason = 'Desktop source offline presence timeout';
            }

            if ($isOrphan) {
                $session->update([
                    'status' => 'terminated',
                    'ended_at' => now(),
                    'termination_reason' => $reason,
                ]);

                DeviceAuditLog::create([
                    'device_id' => $session->desktop_id ?? $session->tablet_id,
                    'event_type' => 'webrtc_session_terminated',
                    'severity' => 'info',
                    'details' => [
                        'session_id' => $session->session_id,
                        'reason' => $reason,
                    ],
                ]);

                $sweptCount++;
            }
        }

        // 3. Recalculate active viewer counts and stream status for all desktops
        $desktops = Device::desktops()->get();
        foreach ($desktops as $desktop) {
            $activeCount = WebRtcSession::where('desktop_id', $desktop->id)
                ->where('status', 'connected')
                ->count();

            $streamStatus = ($desktop->status === 'offline') ? 'offline' : (($activeCount > 0) ? 'streaming' : 'idle');

            if ($desktop->active_viewers_count !== $activeCount || $desktop->stream_status !== $streamStatus) {
                $desktop->update([
                    'active_viewers_count' => $activeCount,
                    'stream_status' => $streamStatus,
                ]);
            }
        }

        $this->info("WebRTC Session Sweeper: {$sweptCount} stale sessions garbage collected.");
        return self::SUCCESS;
    }
}
