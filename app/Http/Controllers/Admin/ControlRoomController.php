<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DevicePresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlRoomController extends Controller
{
    /**
     * Display the CCTV-style Master Control Room / Video Wall.
     */
    public function index(DevicePresenceService $presenceService)
    {
        $presenceService->sweepPresence();

        $desktops = Device::desktops()
            ->with(['assignedTablets'])
            ->orderByRaw("CASE WHEN status = 'online' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $totalDesktops = $desktops->count();
        $onlineDesktops = $desktops->where('status', 'online')->count();
        $streamingDesktops = $desktops->where('stream_status', 'streaming')->count();

        return view('admin.control_room.index', compact(
            'desktops',
            'totalDesktops',
            'onlineDesktops',
            'streamingDesktops'
        ));
    }

    /**
     * Return dynamic device status telemetry for asynchronous status refresh.
     */
    public function devices(DevicePresenceService $presenceService): JsonResponse
    {
        $presenceService->sweepPresence();

        $desktops = Device::desktops()
            ->select([
                'id',
                'uuid',
                'device_identifier',
                'name',
                'location',
                'status',
                'stream_status',
                'fps',
                'ip_address',
                'screen_resolution',
                'active_viewers_count',
                'last_seen_at',
            ])
            ->orderByRaw("CASE WHEN status = 'online' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'uuid' => $device->uuid,
                    'identifier' => $device->device_identifier,
                    'name' => $device->name,
                    'location' => $device->location ?? 'Main Floor',
                    'status' => $device->status,
                    'stream_status' => $device->stream_status,
                    'fps' => $device->fps ?? 30,
                    'ip_address' => $device->ip_address ?? '127.0.0.1',
                    'screen_resolution' => $device->screen_resolution ?? '1920x1080',
                    'active_viewers_count' => $device->active_viewers_count ?? 0,
                    'last_seen_diff' => $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never',
                    'stream_url' => "/stream/{$device->uuid}/live",
                    'audio_url' => "/stream/{$device->uuid}/audio?raw=1",
                    'audio_wav_url' => "/stream/{$device->uuid}/audio",
                ];
            });

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'devices' => $desktops,
        ]);
    }
}
