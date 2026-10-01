<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DesktopTabletMapping;
use App\Models\DeviceAuditLog;

use App\Services\DevicePresenceService;

class DashboardController extends Controller
{
    /**
     * Display the Screen Monitoring Fleet Operations Center.
     */
    public function index(DevicePresenceService $presenceService)
    {
        // Auto-sweep stale devices to guarantee real-time accurate online/offline statuses
        $presenceService->sweepPresence();

        $desktops = Device::desktops()
            ->with(['assignedTablets', 'webrtcSessionsAsDesktop' => function ($q) {
                $q->whereIn('status', ['initiating', 'connected'])->latest('started_at');
            }])
            ->orderBy('name')
            ->get();

        $tablets = Device::tablets()
            ->with(['assignedDesktops', 'activeViewingSession.desktop'])
            ->orderBy('name')
            ->get();

        $activeSessions = \App\Models\WebRtcSession::with(['desktop', 'tablet'])
            ->whereIn('status', ['initiating', 'connected'])
            ->latest('started_at')
            ->get();

        $totalDesktops = $desktops->count();
        $onlineDesktops = $desktops->where('status', 'online')->count();
        $streamingDesktops = $desktops->where('stream_status', 'streaming')->count();

        $totalTablets = $tablets->count();
        $onlineTablets = $tablets->where('status', 'online')->count();

        $mappingsCount = DesktopTabletMapping::count();
        $activeSessionsCount = $activeSessions->count();

        $recentLogs = DeviceAuditLog::with('device')
            ->whereIn('event_type', ['webrtc_session_initiate', 'webrtc_session_connected', 'webrtc_session_terminated', 'device_paired', 'device_disabled'])
            ->orderByDesc('created_at')
            ->take(8)
            ->get();

        return view('admin.overview', compact(
            'desktops',
            'tablets',
            'activeSessions',
            'totalDesktops',
            'onlineDesktops',
            'streamingDesktops',
            'totalTablets',
            'onlineTablets',
            'mappingsCount',
            'activeSessionsCount',
            'recentLogs'
        ));
    }
}
