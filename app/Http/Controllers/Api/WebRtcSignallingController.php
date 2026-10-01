<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\WebRtcSignallingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WebRtcSignallingController extends Controller
{
    public function __construct(
        protected WebRtcSignallingService $signallingService
    ) {}

    /**
     * Get list of desktops assigned to the authenticated tablet.
     */
    public function getAssignedDesktops(Request $request): JsonResponse
    {
        /** @var Device $tablet */
        $tablet = $request->attributes->get('device');
        if (!$tablet || $tablet->device_type !== 'tablet') {
            return response()->json(['error' => 'Only authenticated tablets can access assigned desktops.'], 403);
        }

        $desktops = $tablet->assignedDesktops()
            ->where('devices.status', '!=', 'disabled')
            ->select([
                'devices.id',
                'devices.uuid',
                'devices.device_identifier',
                'devices.name',
                'devices.location',
                'devices.status',
                'devices.stream_status',
                'devices.active_viewers_count',
                'devices.screen_resolution',
                'devices.fps',
                'devices.last_seen_at',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'tablet' => [
                'uuid' => $tablet->uuid,
                'identifier' => $tablet->device_identifier,
                'name' => $tablet->name,
            ],
            'desktops' => $desktops,
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Tablet initiates a WebRTC session with an authorized desktop.
     */
    public function initiateSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'desktop_uuid' => ['required', 'string'],
        ]);

        /** @var Device $tablet */
        $tablet = $request->attributes->get('device');
        if (!$tablet) {
            return response()->json(['error' => 'Device authentication required.'], 401);
        }

        $desktop = Device::where('uuid', $validated['desktop_uuid'])->first();
        if (!$desktop) {
            return response()->json(['error' => 'Desktop not found.'], 404);
        }

        try {
            $session = $this->signallingService->initiateSession($tablet, $desktop);
            return response()->json(array_merge(['success' => true], $session));
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * Desktop relays SDP Offer to Tablet.
     */
    public function sendOffer(Request $request): JsonResponse
    {
        \Log::info('[WebRTC Signalling] sendOffer called', [
            'body' => $request->all(),
            'ip' => $request->ip(),
            'auth' => $request->header('Authorization') ? 'Bearer present' : 'none'
        ]);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'sdp' => ['required', 'array'],
            'sdp.type' => ['required', 'string', 'in:offer'],
            'sdp.sdp' => ['required', 'string'],
        ]);

        /** @var Device $desktop */
        $desktop = $request->attributes->get('device');
        if (!$desktop) {
            return response()->json(['error' => 'Device authentication required.'], 401);
        }

        try {
            $result = $this->signallingService->relayOffer($desktop, $validated['session_id'], $validated['sdp']);
            return response()->json($result);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * Tablet relays SDP Answer to Desktop.
     */
    public function sendAnswer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'sdp' => ['required', 'array'],
            'sdp.type' => ['required', 'string', 'in:answer'],
            'sdp.sdp' => ['required', 'string'],
        ]);

        /** @var Device $tablet */
        $tablet = $request->attributes->get('device');
        if (!$tablet) {
            return response()->json(['error' => 'Device authentication required.'], 401);
        }

        try {
            $result = $this->signallingService->relayAnswer($tablet, $validated['session_id'], $validated['sdp']);
            return response()->json($result);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * Relay ICE Candidate between peers.
     */
    public function sendIceCandidate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'candidate' => ['required', 'array'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');
        if (!$device) {
            return response()->json(['error' => 'Device authentication required.'], 401);
        }

        try {
            $result = $this->signallingService->relayIceCandidate($device, $validated['session_id'], $validated['candidate']);
            return response()->json($result);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * Update session status (connected, terminated, failed).
     */
    public function updateSessionStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:connected,terminated,failed'],
            'reason' => ['nullable', 'string', 'max:128'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');
        if (!$device) {
            return response()->json(['error' => 'Device authentication required.'], 401);
        }

        try {
            $result = $this->signallingService->updateSessionStatus(
                $device,
                $validated['session_id'],
                $validated['status'],
                $validated['reason'] ?? null
            );
            return response()->json($result);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
