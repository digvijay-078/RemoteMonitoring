<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DevicePresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceRealtimeController extends Controller
{
    /**
     * Authorize device WebSocket private channel subscription.
     * Enforces strict 1-to-1 device channel isolation.
     */
    public function authenticateChannel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'socket_id' => ['required', 'string', 'max:100'],
            'channel_name' => ['required', 'string', 'max:150'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');
        if (!$device) {
            return response()->json([
                'error' => 'Device authentication required.',
            ], 401);
        }

        $channelName = $validated['channel_name'];
        $expectedChannel = 'private-device.' . $device->uuid;

        // Strict cross-device isolation: Device can ONLY subscribe to its own private channel
        if ($channelName !== $expectedChannel) {
            return response()->json([
                'error' => "Cross-device access forbidden. Device {$device->device_identifier} cannot subscribe to {$channelName}.",
            ], 403);
        }

        $appKey = config('reverb.apps.apps.0.key') ?: config('broadcasting.connections.reverb.key') ?: env('REVERB_APP_KEY');
        $appSecret = config('reverb.apps.apps.0.secret') ?: config('broadcasting.connections.reverb.secret') ?: env('REVERB_APP_SECRET');

        if (empty($appSecret) || empty($appKey)) {
            return response()->json([
                'error' => 'WebSocket broadcaster is not configured on server.',
            ], 500);
        }

        // Generate Pusher/Reverb standard HMAC SHA-256 signature
        $stringToSign = "{$validated['socket_id']}:{$channelName}";
        $signature = hash_hmac('sha256', $stringToSign, $appSecret);

        return response()->json([
            'auth' => "{$appKey}:{$signature}",
        ]);
    }

    /**
     * Handle incoming application heartbeat from device (Desktop or Tablet).
     * Updates in-memory/file presence cache without database write amplification.
     */
    public function heartbeat(Request $request, DevicePresenceService $presenceService): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device authentication required.',
            ], 401);
        }

        $telemetry = $request->input('telemetry', []);
        if (!is_array($telemetry)) {
            $telemetry = [];
        }

        $result = $presenceService->recordHeartbeat($device, $telemetry);

        return response()->json([
            'success' => true,
            'action' => 'HEARTBEAT_ACK',
            'status' => $result['status'],
            'timestamp' => $result['timestamp'],
            'next_heartbeat_seconds' => $result['next_heartbeat_seconds'] ?? 4,
        ]);
    }

    /**
     * Instantly handle graceful device disconnect or tab close signal.
     */
    public function disconnect(Request $request, DevicePresenceService $presenceService): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device authentication required.',
            ], 401);
        }

        $reason = $request->input('reason', 'client_disconnected');
        $presenceService->recordDisconnect($device, $reason);

        return response()->json([
            'success' => true,
            'action' => 'DISCONNECT_ACK',
            'status' => 'offline',
            'timestamp' => now()->timestamp,
        ]);
    }
}

