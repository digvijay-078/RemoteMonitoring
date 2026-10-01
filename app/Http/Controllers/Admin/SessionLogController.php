<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\WebRtcSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionLogController extends Controller
{
    /**
     * Display session logs with filters and real-time metrics.
     */
    public function index(Request $request)
    {
        // Auto-cleanup stale initiating sessions older than 5 minutes
        \App\Models\WebRtcSession::where('status', 'initiating')
            ->where('started_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'terminated',
                'ended_at' => \Illuminate\Support\Facades\DB::raw('started_at'),
                'termination_reason' => 'Connection timeout / Closed'
            ]);

        $query = WebRtcSession::with(['desktop', 'tablet'])->latest('started_at');

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Desktop
        if ($request->filled('desktop_id')) {
            $query->where('desktop_id', $request->desktop_id);
        }

        // Filter by Tablet
        if ($request->filled('tablet_id')) {
            $query->where('tablet_id', $request->tablet_id);
        }

        // Filter by Date From
        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }

        // Filter by Date To
        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        // Global Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('session_id', 'like', "%{$search}%")
                  ->orWhereHas('desktop', function ($dq) use ($search) {
                      $dq->where('device_identifier', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tablet', function ($tq) use ($search) {
                      $tq->where('device_identifier', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        $sessions = $query->paginate(20)->withQueryString();

        // Calculate KPI Metrics
        $activeSessionsCount = WebRtcSession::where('status', 'connected')->whereNull('ended_at')->count();
        $todaySessionsCount = WebRtcSession::whereDate('started_at', today())->count();
        $totalSessionsCount = WebRtcSession::count();
        $desktops = Device::where('device_type', 'desktop')->orderBy('name')->get();
        $tablets = Device::where('device_type', 'tablet')->orderBy('name')->get();

        return view('admin.sessions.index', compact(
            'sessions',
            'activeSessionsCount',
            'todaySessionsCount',
            'totalSessionsCount',
            'desktops',
            'tablets'
        ));
    }

    /**
     * Export session logs to Excel/CSV format.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = WebRtcSession::with(['desktop', 'tablet'])->latest('started_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('desktop_id')) {
            $query->where('desktop_id', $request->desktop_id);
        }
        if ($request->filled('tablet_id')) {
            $query->where('tablet_id', $request->tablet_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        $fileName = 'remotemonitor_sessions_' . now()->format('Y-m-d_His') . '.csv';

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // CSV Column Headers
            fputcsv($handle, [
                'Session ID',
                'Tablet ID',
                'Tablet Name',
                'Desktop ID',
                'Desktop Name',
                'Status',
                'Started At (IST)',
                'Connected At (IST)',
                'Ended At (IST)',
                'Duration (Seconds)',
                'Duration (Human)',
                'Termination Reason',
            ]);

            $query->chunk(200, function ($sessions) use ($handle) {
                foreach ($sessions as $session) {
                    fputcsv($handle, [
                        $session->session_id,
                        $session->tablet ? $session->tablet->device_identifier : 'N/A',
                        $session->tablet ? $session->tablet->name : 'N/A',
                        $session->desktop ? $session->desktop->device_identifier : 'N/A',
                        $session->desktop ? $session->desktop->name : 'N/A',
                        strtoupper($session->status),
                        $session->started_at_ist ?: 'N/A',
                        $session->connected_at_ist ?: 'N/A',
                        $session->ended_at_ist ?: 'N/A',
                        $session->duration_seconds,
                        $session->duration_human,
                        $session->termination_reason ?: 'Normal termination',
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    /**
     * Return instantaneous session updates for zero-refresh dynamic UI.
     */
    public function realtimeData(Request $request)
    {
        $query = WebRtcSession::with(['desktop', 'tablet'])->latest('started_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('desktop_id')) {
            $query->where('desktop_id', $request->desktop_id);
        }
        if ($request->filled('tablet_id')) {
            $query->where('tablet_id', $request->tablet_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('session_id', 'like', "%{$search}%")
                  ->orWhereHas('desktop', function ($dq) use ($search) {
                      $dq->where('device_identifier', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tablet', function ($tq) use ($search) {
                      $tq->where('device_identifier', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        $sessions = $query->limit(25)->get()->map(function ($s) {
            $isActive = ($s->status === 'connected' && !$s->ended_at);
            return [
                'id' => $s->id,
                'session_id' => $s->session_id,
                'session_id_short' => \Illuminate\Support\Str::limit($s->session_id, 12, '...'),
                'tablet_name' => $s->tablet?->name ?? 'Unknown Tablet',
                'tablet_identifier' => $s->tablet?->device_identifier ?? 'N/A',
                'desktop_name' => $s->desktop?->name ?? 'Unknown Desktop',
                'desktop_identifier' => $s->desktop?->device_identifier ?? 'N/A',
                'status' => $s->status,
                'is_active' => $isActive,
                'connect_time_ist' => $s->connected_at_ist ?: ($s->started_at_ist ?: '—'),
                'disconnect_time_ist' => $s->ended_at_ist ?: ($isActive ? 'Still Connected' : '—'),
                'started_at_ts' => $s->started_at?->timestamp,
                'duration_human' => $s->duration_human,
                'termination_reason' => $s->termination_reason ?: ($isActive ? 'Active Stream' : 'Normal Session'),
            ];
        });

        return response()->json([
            'success' => true,
            'kpis' => [
                'active_count' => WebRtcSession::where('status', 'connected')->whereNull('ended_at')->count(),
                'today_count' => WebRtcSession::whereDate('started_at', today())->count(),
                'total_count' => WebRtcSession::count(),
            ],
            'sessions' => $sessions,
        ]);
    }
}

