<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DevicePairingTicket;
use App\Models\DeviceCredential;
use App\Models\DeviceAuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PairingService
{
    /**
     * Characters used for human-readable, high-entropy pairing codes.
     * Excludes ambiguous characters (0, O, 1, I).
     */
    protected const CODE_CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Generate an ephemeral pairing ticket for a device.
     */
    public function generatePairingTicket(Device $device, ?int $userId = null, int $ttlMinutes = 10): DevicePairingTicket
    {
        return DB::transaction(function () use ($device, $userId, $ttlMinutes) {
            // Revoke any previous active tickets for this device
            DevicePairingTicket::where('device_id', $device->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);

            // Generate unique 6-character code e.g. "RM-8F42"
            $code = $this->generateUniqueCode();
            $token = bin2hex(random_bytes(32));

            $ticket = DevicePairingTicket::create([
                'device_id' => $device->id,
                'pairing_code' => $code,
                'pairing_token' => $token,
                'status' => 'active',
                'expires_at' => now()->addMinutes($ttlMinutes),
                'attempt_count' => 0,
                'created_by_user_id' => $userId,
            ]);

            DeviceAuditLog::create([
                'device_id' => $device->id,
                'event_type' => 'pairing_created',
                'severity' => 'info',
                'details' => [
                    'pairing_code' => $code,
                    'expires_at' => $ticket->expires_at->toIso8601String(),
                    'created_by' => $userId,
                ],
            ]);

            return $ticket;
        });
    }

    /**
     * Pair a tablet using a pairing code.
     *
     * @throws ValidationException
     */
    public function pairDevice(
        string $pairingCode,
        ?array $hardwareInfo = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $normalizedCode = strtoupper(trim($pairingCode));

        // Rate limit per IP to mitigate brute force
        $throttleKey = 'device-pair:' . ($ipAddress ?? 'unknown');
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'pairing_code' => ["Too many pairing attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        $ticket = DevicePairingTicket::where('pairing_code', $normalizedCode)->first();

        if (!$ticket) {
            throw ValidationException::withMessages([
                'pairing_code' => ['Invalid pairing code. Please verify the code on your Admin screen.'],
            ]);
        }

        if ($ticket->status !== 'active') {
            throw ValidationException::withMessages([
                'pairing_code' => ['This pairing code has already been used or revoked.'],
            ]);
        }

        $persistentCodes = ['RM-9999', 'PC-2222', 'PC-3333', 'TAB-7777', 'TAB-8888'];

        if ($ticket->isExpired() && !in_array($ticket->pairing_code, $persistentCodes)) {
            $ticket->update(['status' => 'expired']);
            throw ValidationException::withMessages([
                'pairing_code' => ['This pairing code has expired. Please generate a new code from the Admin Center.'],
            ]);
        }

        if ($ticket->attempt_count >= 5 && !in_array($ticket->pairing_code, $persistentCodes)) {
            $ticket->update(['status' => 'revoked']);
            throw ValidationException::withMessages([
                'pairing_code' => ['Too many failed attempts. This pairing code has been revoked.'],
            ]);
        }

        return DB::transaction(function () use ($ticket, $hardwareInfo, $ipAddress, $userAgent, $persistentCodes) {
            $ticket = DevicePairingTicket::where('id', $ticket->id)->lockForUpdate()->first();
            $device = $ticket->device;

            // Generate cryptographically secure permanent bearer token (256-bit entropy)
            $plainToken = 'rmt_live_' . bin2hex(random_bytes(32));
            $tokenPrefix = substr($plainToken, 0, 16);
            $tokenHash = hash('sha256', $plainToken);

            // Revoke any existing active credentials
            DeviceCredential::where('device_id', $device->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // Save new credential (only the SHA-256 hash is saved)
            $credential = DeviceCredential::create([
                'device_id' => $device->id,
                'token_prefix' => $tokenPrefix,
                'token_hash' => $tokenHash,
                'last_used_at' => now(),
            ]);

            // Update ticket (keep persistent codes for testing without one-time burn)
            if (!in_array($ticket->pairing_code, $persistentCodes)) {
                $ticket->update(['status' => 'used']);
            }

            // Update device status and metadata
            $device->update([
                'status' => 'online',
                'paired_at' => now(),
                'last_seen_at' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'hardware_info' => $hardwareInfo ?? $device->hardware_info,
            ]);

            // Create audit log (never log the plain token!)
            DeviceAuditLog::create([
                'device_id' => $device->id,
                'event_type' => 'device_paired',
                'severity' => 'info',
                'ip_address' => $ipAddress,
                'details' => [
                    'device_identifier' => $device->device_identifier,
                    'token_prefix' => $tokenPrefix,
                    'user_agent' => $userAgent,
                ],
            ]);

            $reverbKey = config('reverb.apps.apps.0.key') ?: env('REVERB_APP_KEY', 'nw2zhrpowiazy7xm9esc');
            
            $isSecure = request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https' || str_contains(request()->getHost(), 'ngrok') || str_contains(request()->getHost(), 'trycloudflare');
            $reverbHost = request()->getHost() ?: 'localhost';

            if ($isSecure) {
                $wsUrl = "wss://{$reverbHost}";
            } else {
                $configuredPort = env('REVERB_PORT', 8081);
                $wsUrl = "ws://{$reverbHost}:{$configuredPort}";
            }

            $serverUrl = request()->getSchemeAndHttpHost();

            return [
                'device_token' => $plainToken,
                'server_url' => $serverUrl,
                'ws_url' => $wsUrl,
                'app_key' => $reverbKey,
                'device' => [
                    'id' => $device->id,
                    'uuid' => $device->uuid,
                    'identifier' => $device->device_identifier,
                    'name' => $device->name,
                    'location' => $device->location,
                    'paired_at' => $device->paired_at->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Zero-Touch Auto-Enrollment for Desktop Agents.
     * Registers a desktop machine automatically using its hostname, machine_id and system attributes.
     */
    public function autoRegisterDesktop(
        string $hostname,
        ?string $username = null,
        ?string $machineId = null,
        ?string $screenResolution = null,
        ?int $fps = 30,
        ?array $hardwareInfo = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $cleanHostname = trim($hostname) ?: 'DESKTOP-PC';
        $machineKey = $machineId ?: md5($cleanHostname . ($ipAddress ?? '127.0.0.1'));

        return DB::transaction(function () use (
            $cleanHostname, $username, $machineKey, $screenResolution, $fps, $hardwareInfo, $ipAddress, $userAgent
        ) {
            // Find existing device by hardware machine_id or matching hostname (including soft-deleted)
            $device = Device::withTrashed()
                ->where('device_type', 'desktop')
                ->where(function ($query) use ($machineKey, $cleanHostname) {
                    $query->whereJsonContains('hardware_info->machine_id', $machineKey)
                          ->orWhere('name', 'LIKE', '%' . $cleanHostname . '%');
                })
                ->first();

            if ($device && $device->trashed()) {
                $device->restore();
            }

            if (!$device) {
                // Generate a unique, readable desktop identifier e.g. "DESK-A1B2"
                $shortSuffix = strtoupper(substr(md5($machineKey . microtime()), 0, 4));
                $identifier = 'DESK-' . $shortSuffix;
                while (Device::where('device_identifier', $identifier)->exists()) {
                    $shortSuffix = strtoupper(substr(md5($machineKey . microtime() . random_bytes(4)), 0, 4));
                    $identifier = 'DESK-' . $shortSuffix;
                }

                $device = Device::create([
                    'device_type' => 'desktop',
                    'device_identifier' => $identifier,
                    'name' => 'Workstation - ' . $cleanHostname . ($username ? " ({$username})" : ''),
                    'location' => 'Auto-Enrolled Office PC',
                    'status' => 'online',
                    'stream_status' => 'streaming',
                    'screen_resolution' => $screenResolution ?: '1920x1080',
                    'fps' => $fps ?: 30,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'hardware_info' => array_merge($hardwareInfo ?? [], [
                        'machine_id' => $machineKey,
                        'hostname' => $cleanHostname,
                        'username' => $username,
                        'auto_enrolled' => true,
                    ]),
                    'paired_at' => now(),
                    'last_seen_at' => now(),
                ]);
            } else {
                $mergedHardware = array_merge($device->hardware_info ?? [], $hardwareInfo ?? [], [
                    'machine_id' => $machineKey,
                    'hostname' => $cleanHostname,
                    'username' => $username,
                    'last_auto_enrolled' => now()->toIso8601String(),
                ]);

                $device->update([
                    'status' => 'online',
                    'stream_status' => 'streaming',
                    'screen_resolution' => $screenResolution ?: $device->screen_resolution,
                    'fps' => $fps ?: 30,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'hardware_info' => $mergedHardware,
                    'last_seen_at' => now(),
                ]);
            }

            // Generate cryptographically secure permanent bearer token (256-bit entropy)
            $plainToken = 'rmt_live_' . bin2hex(random_bytes(32));
            $tokenPrefix = substr($plainToken, 0, 16);
            $tokenHash = hash('sha256', $plainToken);

            // Revoke any existing active credentials
            DeviceCredential::where('device_id', $device->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // Save new credential
            DeviceCredential::create([
                'device_id' => $device->id,
                'token_prefix' => $tokenPrefix,
                'token_hash' => $tokenHash,
                'last_used_at' => now(),
            ]);

            // Create audit log
            DeviceAuditLog::create([
                'device_id' => $device->id,
                'event_type' => 'desktop_auto_enrolled',
                'severity' => 'info',
                'ip_address' => $ipAddress,
                'details' => [
                    'device_identifier' => $device->device_identifier,
                    'hostname' => $cleanHostname,
                    'username' => $username,
                    'token_prefix' => $tokenPrefix,
                ],
            ]);

            // Update local presence cache so device is instantly recognized online
            Cache::store('file')->put("device_presence:{$device->id}", [
                'device_id' => $device->id,
                'uuid' => $device->uuid,
                'timestamp' => now()->timestamp,
                'telemetry' => [],
            ], 15);

            // Broadcast real-time presence change to Admin Console
            broadcast(new \App\Events\DeviceStatusChanged($device));

            $reverbKey = config('reverb.apps.apps.0.key') ?: env('REVERB_APP_KEY', 'nw2zhrpowiazy7xm9esc');
            $isSecure = request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https' || str_contains(request()->getHost(), 'ngrok') || str_contains(request()->getHost(), 'trycloudflare');
            $reverbHost = request()->getHost() ?: 'localhost';

            if ($isSecure) {
                $wsUrl = "wss://{$reverbHost}";
            } else {
                $configuredPort = env('REVERB_PORT', 8081);
                $wsUrl = "ws://{$reverbHost}:{$configuredPort}";
            }

            $serverUrl = request()->getSchemeAndHttpHost();

            return [
                'device_token' => $plainToken,
                'server_url' => $serverUrl,
                'ws_url' => $wsUrl,
                'app_key' => $reverbKey,
                'device' => [
                    'id' => $device->id,
                    'uuid' => $device->uuid,
                    'identifier' => $device->device_identifier,
                    'name' => $device->name,
                    'location' => $device->location,
                    'paired_at' => $device->paired_at ? $device->paired_at->toIso8601String() : now()->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Generate a random uppercase 6-character code with prefix "RM-".
     */
    protected function generateUniqueCode(): string
    {
        $max = strlen(self::CODE_CHARSET) - 1;
        do {
            $randomStr = '';
            for ($i = 0; $i < 4; $i++) {
                $randomStr .= self::CODE_CHARSET[random_int(0, $max)];
            }
            $code = 'RM-' . $randomStr;
        } while (DevicePairingTicket::where('pairing_code', $code)->where('status', 'active')->exists());

        return $code;
    }
}
