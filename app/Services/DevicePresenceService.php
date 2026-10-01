<?php

namespace App\Services;

use App\Events\DeviceStatusChanged;
use App\Models\Device;
use App\Models\DeviceAuditLog;
use App\Models\WebRtcSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DevicePresenceService
{
    // Configurable presence thresholds in seconds (fast real-time sweep)
    public const THRESHOLD_ONLINE_SECONDS = 5;
    public const THRESHOLD_WARNING_SECONDS = 7;
    public const CACHE_TTL_SECONDS = 15;

    /**
     * Cache key helper for device presence record.
     */
    public function getCacheKey(int $deviceId): string
    {
        return "device_presence:{$deviceId}";
    }

    /**
     * Record an incoming application-level heartbeat from a device.
     */
    public function recordHeartbeat(Device $device, array $telemetry = []): array
    {
        $now = now();
        $cacheKey = $this->getCacheKey($device->id);

        $payload = [
            'device_id' => $device->id,
            'uuid' => $device->uuid,
            'timestamp' => $now->timestamp,
            'telemetry' => $telemetry,
        ];

        // Store exclusively in local cache (zero MySQL write during steady state)
        Cache::store('file')->put($cacheKey, $payload, self::CACHE_TTL_SECONDS);

        // If device was disabled by admin, do not change state
        if ($device->status === 'disabled') {
            return [
                'status' => 'disabled',
                'timestamp' => $now->timestamp,
            ];
        }

        // State transition: If device was offline or pending_pair, transition to online
        if (in_array($device->status, ['offline', 'pending_pair'])) {
            $device->update([
                'status' => 'online',
                'last_seen_at' => $now,
            ]);

            DeviceAuditLog::create([
                'device_id' => $device->id,
                'event_type' => 'device_online',
                'severity' => 'info',
                'details' => [
                    'source' => 'heartbeat_restored',
                    'telemetry' => $telemetry,
                ],
            ]);

            // Broadcast real-time presence change to Admin Control Center
            broadcast(new DeviceStatusChanged($device));
        }

        return [
            'status' => 'online',
            'timestamp' => $now->timestamp,
            'next_heartbeat_seconds' => 4,
        ];
    }

    /**
     * Instantly mark a device offline upon client disconnect / page unload / stop.
     */
    public function recordDisconnect(Device $device, string $reason = 'client_disconnected'): void
    {
        Cache::store('file')->forget($this->getCacheKey($device->id));

        if ($device->status !== 'disabled') {
            $oldStatus = $device->status;

            $device->update([
                'status' => 'offline',
                'stream_status' => 'offline',
                'last_seen_at' => now(),
            ]);

            DeviceAuditLog::create([
                'device_id' => $device->id,
                'event_type' => 'device_offline',
                'severity' => 'info',
                'details' => [
                    'source' => 'client_disconnect_signal',
                    'previous_status' => $oldStatus,
                    'reason' => $reason,
                ],
            ]);

            // Cleanly terminate any active streaming sessions
            if ($device->isTablet()) {
                $sessions = WebRtcSession::with('desktop')
                    ->where('tablet_id', $device->id)
                    ->whereIn('status', ['initiating', 'connected'])
                    ->whereNull('ended_at')
                    ->get();

                foreach ($sessions as $s) {
                    $s->update([
                        'status' => 'terminated',
                        'ended_at' => now(),
                        'termination_reason' => $reason ?? 'Tablet closed or disconnected',
                    ]);

                    if ($s->desktop) {
                        $remaining = WebRtcSession::where('desktop_id', $s->desktop_id)
                            ->whereIn('status', ['initiating', 'connected'])
                            ->whereNull('ended_at')
                            ->count();

                        $s->desktop->update([
                            'active_viewers_count' => $remaining,
                            'stream_status' => ($remaining > 0 && $s->desktop->status === 'online') ? 'streaming' : ($s->desktop->status === 'online' ? 'idle' : 'offline'),
                        ]);

                        broadcast(new DeviceStatusChanged($s->desktop))->toOthers();
                    }
                }
            } elseif ($device->isDesktop()) {
                WebRtcSession::where('desktop_id', $device->id)
                    ->whereIn('status', ['initiating', 'connected'])
                    ->whereNull('ended_at')
                    ->update([
                        'status' => 'terminated',
                        'ended_at' => now(),
                        'termination_reason' => 'Desktop disconnected',
                    ]);
            }

            // Broadcast immediate transition to Admin Control Center
            broadcast(new DeviceStatusChanged($device));
        }
    }

    /**
     * Determine current presence state based on heartbeat age.
     */
    public function evaluatePresenceState(?int $lastHeartbeatTimestamp): string
    {
        if (!$lastHeartbeatTimestamp) {
            return 'offline';
        }

        $age = now()->timestamp - $lastHeartbeatTimestamp;

        if ($age <= self::THRESHOLD_ONLINE_SECONDS) {
            return 'online';
        }

        if ($age <= self::THRESHOLD_WARNING_SECONDS) {
            return 'warning';
        }

        return 'offline';
    }

    /**
     * Query local stream relay hub for live desktop video/audio frame push timestamps.
     */
    public function getActiveStreamTimestamps(): array
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 0.3,
                    'ignore_errors' => true,
                ]
            ]);
            $raw = @file_get_contents('http://127.0.0.1:8085/api/active-streams', false, $ctx);
            if ($raw) {
                $data = json_decode($raw, true);
                if (!empty($data['streams']) && is_array($data['streams'])) {
                    return $data['streams'];
                }
            }
        } catch (\Throwable $e) {
            // Silently fallback if stream relay hub is busy
        }
        return [];
    }

    /**
     * Periodic evaluation of all non-disabled devices for stale heartbeats and active streams.
     * Evaluates state transitions and broadcasts updates to Admin.
     */
    public function sweepPresence(bool $force = false): array
    {
        $devices = Device::where('status', '!=', 'disabled')->get();
        $transitions = [];
        $activeStreams = $this->getActiveStreamTimestamps();

        foreach ($devices as $device) {
            $cached = Cache::store('file')->get($this->getCacheKey($device->id));
            $heartbeatTime = $cached['timestamp'] ?? null;

            // Check stream hub active frame push for desktop
            $streamTime = null;
            if ($device->isDesktop()) {
                $normUuid = strtolower(str_replace('-', '', $device->uuid));
                $normId = strtolower(str_replace('-', '', $device->device_identifier));

                foreach ($activeStreams as $key => $info) {
                    $normKey = strtolower(str_replace('-', '', $key));
                    if ($normKey === $normUuid || $normKey === $normId || (strlen($normKey) >= 8 && str_starts_with($normUuid, $normKey))) {
                        if (!empty($info['last_ts'])) {
                            $streamTime = (int) $info['last_ts'];
                            break;
                        }
                    }
                }
            }

            // Most recent live signal: either heartbeat or live frame push
            $liveSignalTs = max(array_filter([$heartbeatTime, $streamTime]) ?: [0]);

            if ($liveSignalTs > 0) {
                $effectiveTime = $liveSignalTs;
            } else {
                $effectiveTime = $device->last_seen_at ? $device->last_seen_at->timestamp : null;
            }

            $targetState = $this->evaluatePresenceState($effectiveTime);
            $targetStreamStatus = ($targetState === 'online' && ($streamTime !== null || $device->isDesktop())) ? 'streaming' : ($targetState === 'online' ? 'idle' : 'offline');

            if ($targetState !== $device->status || ($device->isDesktop() && $targetStreamStatus !== $device->stream_status)) {
                $oldStatus = $device->status;

                $updateData = [
                    'status' => $targetState,
                    'stream_status' => $targetStreamStatus,
                ];

                if ($liveSignalTs > 0) {
                    $updateData['last_seen_at'] = Carbon::createFromTimestamp($liveSignalTs);
                }

                $device->update($updateData);

                if ($targetState === 'offline') {
                    if ($device->isTablet()) {
                        $sessions = WebRtcSession::with('desktop')
                            ->where('tablet_id', $device->id)
                            ->whereIn('status', ['initiating', 'connected'])
                            ->whereNull('ended_at')
                            ->get();

                        foreach ($sessions as $s) {
                            $s->update([
                                'status' => 'terminated',
                                'ended_at' => now(),
                                'termination_reason' => 'Tablet presence timeout',
                            ]);

                            if ($s->desktop) {
                                $remaining = WebRtcSession::where('desktop_id', $s->desktop_id)
                                    ->whereIn('status', ['initiating', 'connected'])
                                    ->whereNull('ended_at')
                                    ->count();

                                $s->desktop->update([
                                    'active_viewers_count' => $remaining,
                                    'stream_status' => ($remaining > 0 && $s->desktop->status === 'online') ? 'streaming' : ($s->desktop->status === 'online' ? 'idle' : 'offline'),
                                ]);

                                broadcast(new DeviceStatusChanged($s->desktop));
                            }
                        }
                    } elseif ($device->isDesktop()) {
                        WebRtcSession::where('desktop_id', $device->id)
                            ->whereIn('status', ['initiating', 'connected'])
                            ->whereNull('ended_at')
                            ->update([
                                'status' => 'terminated',
                                'ended_at' => now(),
                                'termination_reason' => 'Desktop presence timeout',
                            ]);
                    }
                }

                DeviceAuditLog::create([
                    'device_id' => $device->id,
                    'event_type' => "device_{$targetState}",
                    'severity' => ($targetState === 'offline' ? 'warning' : 'info'),
                    'details' => [
                        'previous_status' => $oldStatus,
                        'new_status' => $targetState,
                        'heartbeat_age_seconds' => $heartbeatTime ? (now()->timestamp - $heartbeatTime) : null,
                    ],
                ]);

                broadcast(new DeviceStatusChanged($device));

                $transitions[] = [
                    'device_id' => $device->id,
                    'identifier' => $device->device_identifier,
                    'from' => $oldStatus,
                    'to' => $targetState,
                ];
            }
        }

        return [
            'scanned_devices' => $devices->count(),
            'transitions_count' => count($transitions),
            'transitions' => $transitions,
        ];
    }

    /**
     * Batch persist last_seen_at timestamps for active online devices.
     * Prevents write amplification by flushing once every several minutes.
     */
    public function batchFlushLastSeen(): int
    {
        $onlineDevices = Device::where('status', 'online')->get();
        $deviceIdsToUpdate = [];

        foreach ($onlineDevices as $device) {
            $cached = Cache::store('file')->get($this->getCacheKey($device->id));
            if ($cached && isset($cached['timestamp'])) {
                $deviceIdsToUpdate[] = $device->id;
            }
        }

        if (!empty($deviceIdsToUpdate)) {
            DB::table('devices')
                ->whereIn('id', $deviceIdsToUpdate)
                ->update(['last_seen_at' => now()]);
        }

        return count($deviceIdsToUpdate);
    }
}
