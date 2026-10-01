<?php

namespace App\Services;

use App\Events\WebRtcAnswerReceived;
use App\Events\WebRtcIceCandidateReceived;
use App\Events\WebRtcOfferReceived;
use App\Events\WebRtcSessionRequested;
use App\Events\WebRtcSessionStatusChanged;
use App\Models\DesktopTabletMapping;
use App\Models\Device;
use App\Models\DeviceAuditLog;
use App\Models\WebRtcSession;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class WebRtcSignallingService
{
    /**
     * Return configured STUN and TURN ICE servers.
     */
    public function getIceServers(): array
    {
        $iceServers = [];

        // 1. Configured STUN servers
        $stunUrls = config('services.webrtc.stun_urls') ?: [
            'stun:stun.l.google.com:19302',
            'stun:stun1.l.google.com:19302',
            'stun:stun.cloudflare.com:3478',
            'stun:openrelay.metered.ca:80',
        ];

        foreach ($stunUrls as $url) {
            $trimmed = trim($url);
            if (!empty($trimmed)) {
                $iceServers[] = ['urls' => $trimmed];
            }
        }

        // 2. Configured TURN server (openrelay / coturn) for symmetric NAT traversal
        $turnUrls = config('services.webrtc.turn_url') ?: 'turn:openrelay.metered.ca:80,turn:openrelay.metered.ca:443,turn:openrelay.metered.ca:443?transport=tcp';
        if (!empty($turnUrls)) {
            $urls = array_map('trim', explode(',', $turnUrls));
            $username = config('services.webrtc.turn_username') ?: 'openrelayproject';
            $credential = config('services.webrtc.turn_credential') ?: 'openrelayproject';

            foreach ($urls as $url) {
                if (!empty($url)) {
                    $turnEntry = ['urls' => $url];
                    if (!empty($username)) {
                        $turnEntry['username'] = $username;
                    }
                    if (!empty($credential)) {
                        $turnEntry['credential'] = $credential;
                    }
                    $iceServers[] = $turnEntry;
                }
            }
        }

        return $iceServers;
    }

    /**
     * Check if a tablet is authorized to access a desktop.
     */
    public function isMappingAuthorized(Device $tablet, Device $desktop): bool
    {
        if ($tablet->status === 'disabled' || $desktop->status === 'disabled') {
            return false;
        }

        return DesktopTabletMapping::where('tablet_id', $tablet->id)
            ->where('desktop_id', $desktop->id)
            ->exists();
    }

    /**
     * Initiate a new WebRTC session from an authorized Tablet to a Desktop.
     */
    public function initiateSession(Device $tablet, Device $desktop): array
    {
        if ($tablet->device_type !== 'tablet') {
            throw new AccessDeniedHttpException('Only tablets can initiate desktop viewing sessions.');
        }

        if ($desktop->device_type !== 'desktop') {
            throw new UnprocessableEntityHttpException('Target device is not a desktop.');
        }

        if (!$this->isMappingAuthorized($tablet, $desktop)) {
            throw new AccessDeniedHttpException("Tablet {$tablet->device_identifier} is not authorized to stream desktop {$desktop->device_identifier}.");
        }

        $sessionId = (string) Str::uuid();
        $iceServers = $this->getIceServers();

        $session = WebRtcSession::create([
            'session_id' => $sessionId,
            'desktop_id' => $desktop->id,
            'tablet_id' => $tablet->id,
            'status' => 'initiating',
            'started_at' => now(),
        ]);

        DeviceAuditLog::create([
            'device_id' => $tablet->id,
            'event_type' => 'webrtc_session_initiate',
            'severity' => 'info',
            'details' => [
                'session_id' => $sessionId,
                'desktop_identifier' => $desktop->device_identifier,
                'desktop_uuid' => $desktop->uuid,
            ],
        ]);

        // Broadcast session request to desktop
        broadcast(new WebRtcSessionRequested(
            $desktop->uuid,
            $sessionId,
            $tablet->uuid,
            $tablet->device_identifier,
            $tablet->name ?? $tablet->device_identifier,
            $iceServers
        ));

        return [
            'session_id' => $sessionId,
            'desktop_uuid' => $desktop->uuid,
            'desktop_identifier' => $desktop->device_identifier,
            'desktop_name' => $desktop->name,
            'status' => 'initiating',
            'ice_servers' => $iceServers,
        ];
    }

    /**
     * Relay SDP Offer from Desktop to Tablet.
     */
    public function relayOffer(Device $desktop, string $sessionId, array $sdp): array
    {
        $session = WebRtcSession::where('session_id', $sessionId)->first();
        if (!$session) {
            throw new NotFoundHttpException('WebRTC session not found.');
        }

        if ($session->desktop_id !== $desktop->id) {
            throw new AccessDeniedHttpException('Only the assigned desktop can send an offer for this session.');
        }

        $tablet = Device::find($session->tablet_id);
        if (!$tablet || !$this->isMappingAuthorized($tablet, $desktop)) {
            throw new AccessDeniedHttpException('Desktop-Tablet mapping is inactive or revoked.');
        }

        $iceServers = $this->getIceServers();

        broadcast(new WebRtcOfferReceived(
            $tablet->uuid,
            $sessionId,
            $desktop->uuid,
            $sdp,
            $iceServers
        ));

        return ['success' => true, 'relayed_to' => $tablet->uuid];
    }

    /**
     * Relay SDP Answer from Tablet to Desktop.
     */
    public function relayAnswer(Device $tablet, string $sessionId, array $sdp): array
    {
        $session = WebRtcSession::where('session_id', $sessionId)->first();
        if (!$session) {
            throw new NotFoundHttpException('WebRTC session not found.');
        }

        if ($session->tablet_id !== $tablet->id) {
            throw new AccessDeniedHttpException('Only the assigned tablet can send an answer for this session.');
        }

        $desktop = Device::find($session->desktop_id);
        if (!$desktop || !$this->isMappingAuthorized($tablet, $desktop)) {
            throw new AccessDeniedHttpException('Desktop-Tablet mapping is inactive or revoked.');
        }

        broadcast(new WebRtcAnswerReceived(
            $desktop->uuid,
            $sessionId,
            $tablet->uuid,
            $sdp
        ));

        return ['success' => true, 'relayed_to' => $desktop->uuid];
    }

    /**
     * Relay ICE Candidate between peers.
     */
    public function relayIceCandidate(Device $sender, string $sessionId, array $candidate): array
    {
        $session = WebRtcSession::where('session_id', $sessionId)->first();
        if (!$session) {
            throw new NotFoundHttpException('WebRTC session not found.');
        }

        $targetUuid = null;
        if ($sender->id === $session->tablet_id) {
            $desktop = Device::find($session->desktop_id);
            $targetUuid = $desktop?->uuid;
        } elseif ($sender->id === $session->desktop_id) {
            $tablet = Device::find($session->tablet_id);
            $targetUuid = $tablet?->uuid;
        } else {
            throw new AccessDeniedHttpException('Device is not a participant in this WebRTC session.');
        }

        if (!$targetUuid) {
            throw new NotFoundHttpException('Target peer device not found.');
        }

        broadcast(new WebRtcIceCandidateReceived(
            $targetUuid,
            $sessionId,
            $sender->uuid,
            $candidate
        ));

        return ['success' => true, 'relayed_to' => $targetUuid];
    }

    /**
     * Update session status (connected, terminated, failed).
     */
    public function updateSessionStatus(Device $device, string $sessionId, string $status, ?string $reason = null): array
    {
        $session = WebRtcSession::where('session_id', $sessionId)->first();
        if (!$session) {
            throw new NotFoundHttpException('WebRTC session not found.');
        }

        if ($device->id !== $session->desktop_id && $device->id !== $session->tablet_id) {
            throw new AccessDeniedHttpException('Device is not a participant in this session.');
        }

        $desktop = Device::find($session->desktop_id);
        $tablet = Device::find($session->tablet_id);

        $updateData = ['status' => $status];
        if ($status === 'connected' && !$session->connected_at) {
            $updateData['connected_at'] = now();
        } elseif (in_array($status, ['terminated', 'failed']) && !$session->ended_at) {
            $updateData['ended_at'] = now();
            $updateData['termination_reason'] = $reason;
        }

        $session->update($updateData);

        // Recalculate desktop active viewers count
        $activeCount = 0;
        $streamStatus = 'idle';

        if ($desktop) {
            $activeCount = WebRtcSession::where('desktop_id', $desktop->id)
                ->where('status', 'connected')
                ->count();

            if ($desktop->status === 'offline') {
                $streamStatus = 'offline';
            } elseif ($activeCount > 0) {
                $streamStatus = 'streaming';
            } else {
                $streamStatus = 'idle';
            }

            $desktop->update([
                'active_viewers_count' => $activeCount,
                'stream_status' => $streamStatus,
            ]);
        }

        // Target peer for notification
        $targetUuid = ($device->id === $session->tablet_id) ? $desktop?->uuid : $tablet?->uuid;

        broadcast(new WebRtcSessionStatusChanged(
            $targetUuid ?? '',
            $sessionId,
            $desktop?->uuid ?? '',
            $tablet?->uuid ?? '',
            $status,
            $reason,
            $activeCount,
            $streamStatus
        ));

        DeviceAuditLog::create([
            'device_id' => $device->id,
            'event_type' => 'webrtc_session_' . $status,
            'severity' => in_array($status, ['failed']) ? 'warning' : 'info',
            'details' => [
                'session_id' => $sessionId,
                'status' => $status,
                'reason' => $reason,
                'active_viewers' => $activeCount,
            ],
        ]);

        return [
            'success' => true,
            'session_id' => $sessionId,
            'status' => $status,
            'active_viewers_count' => $activeCount,
            'stream_status' => $streamStatus,
        ];
    }
}
