<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\DeviceRevoked;
use App\Events\DeviceStatusChanged;
use App\Models\Device;
use App\Models\DeviceAuditLog;
use App\Models\WebRtcSession;
use App\Services\DevicePresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    /**
     * Display a listing of devices with optional search/filter.
     */
    public function index(Request $request, DevicePresenceService $presenceService)
    {
        $presenceService->sweepPresence();

        $query = Device::with(['latestActivePairingTicket', 'activeCredential', 'assignedDesktops', 'assignedTablets']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('device_identifier', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $devices = $query->orderByDesc('created_at')->paginate(15)->withQueryString();
        $availableDesktops = Device::desktops()->where('status', '!=', 'disabled')->orderBy('name')->get();

        return view('admin.devices.index', compact('devices', 'availableDesktops'));
    }

    /**
     * Store a newly created device in storage.
     */
    public function store(Request $request)
    {
        // Sanitize empty strings to null
        $request->merge([
            'device_identifier' => trim($request->input('device_identifier')) ?: null,
            'assigned_desktop_id' => $request->input('assigned_desktop_id') ?: null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:200'],
            'device_type' => ['required', 'in:tablet,desktop'],
            'device_identifier' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('devices', 'device_identifier')->whereNull('deleted_at'),
            ],
            'assigned_desktop_id' => ['nullable', 'exists:devices,id'],
        ]);

        $device = DB::transaction(function () use ($validated, $request) {
            $deviceType = $validated['device_type'];
            $identifier = !empty($validated['device_identifier'])
                ? strtoupper(trim($validated['device_identifier']))
                : $this->generateNextIdentifier($deviceType);

            // If a soft-deleted record with this identifier exists, permanently remove it so unique key won't conflict
            Device::withTrashed()
                ->where('device_identifier', $identifier)
                ->whereNotNull('deleted_at')
                ->forceDelete();

            $device = Device::create([
                'device_identifier' => $identifier,
                'device_type' => $deviceType,
                'name' => $validated['name'],
                'location' => $validated['location'],
                'status' => 'pending_pair',
            ]);

            // If tablet and desktop specified, automatically create mapping
            if ($deviceType === 'tablet' && !empty($validated['assigned_desktop_id'])) {
                \App\Models\DesktopTabletMapping::create([
                    'tablet_id' => $device->id,
                    'desktop_id' => $validated['assigned_desktop_id'],
                    'created_by' => auth()->id(),
                ]);
            }

            DeviceAuditLog::create([
                'device_id' => $device->id,
                'event_type' => 'device_created',
                'severity' => 'info',
                'ip_address' => $request->ip(),
                'details' => [
                    'name' => $device->name,
                    'device_identifier' => $device->device_identifier,
                    'device_type' => $device->device_type,
                    'created_by' => auth()->id(),
                ],
            ]);

            return $device;
        });

        $directUrl = url('/t/' . $device->device_identifier);

        $msg = $device->isTablet()
            ? "Tablet [{$device->device_identifier}] registered! Fixed 1-Click Link is ready: {$directUrl}"
            : "Desktop [{$device->device_identifier}] registered successfully.";

        return redirect()->route('admin.devices.index')
            ->with('status', $msg)
            ->with('direct_link', $device->isTablet() ? $directUrl : null)
            ->with('tablet_identifier', $device->isTablet() ? $device->device_identifier : null);
    }

    /**
     * Display the specified device details.
     */
    public function show($id)
    {
        $device = Device::with([
            'latestActivePairingTicket',
            'credentials' => function ($q) {
                $q->orderByDesc('created_at');
            },
            'auditLogs' => function ($q) {
                $q->take(25);
            },
            'assignedDesktops',
            'assignedTablets',
        ])->findOrFail($id);

        $availableDesktops = $device->isTablet()
            ? Device::desktops()->where('status', '!=', 'disabled')->orderBy('name')->get()
            : collect();

        return view('admin.devices.show', compact('device', 'availableDesktops'));
    }

    /**
     * Update the specified device in storage.
     */
    public function update(Request $request, $id)
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:200'],
            'device_identifier' => ['nullable', 'string', 'max:64', Rule::unique('devices', 'device_identifier')->ignore($device->id)],
        ]);

        if (!empty($validated['device_identifier'])) {
            $validated['device_identifier'] = strtoupper(trim($validated['device_identifier']));
        }

        $device->update($validated);

        return redirect()->route('admin.devices.show', $device->id)
            ->with('status', "Device details updated successfully.");
    }

    /**
     * Quick-assign desktop feed for a tablet directly.
     */
    public function assignDesktop(Request $request, $id)
    {
        $tablet = Device::findOrFail($id);
        if (!$tablet->isTablet()) {
            return back()->withErrors(['device' => 'Device is not a tablet.']);
        }

        $validated = $request->validate([
            'desktop_id' => ['required', 'exists:devices,id'],
        ]);

        $desktop = Device::findOrFail($validated['desktop_id']);
        if (!$desktop->isDesktop()) {
            return back()->withErrors(['desktop_id' => 'Selected device is not a desktop.']);
        }

        // Reassign
        \App\Models\DesktopTabletMapping::where('tablet_id', $tablet->id)->delete();
        \App\Models\DesktopTabletMapping::create([
            'tablet_id' => $tablet->id,
            'desktop_id' => $desktop->id,
            'created_by' => auth()->id(),
        ]);

        // Broadcast real-time switch signal
        broadcast(new \App\Events\TabletMappingChanged($tablet, $desktop, 'assigned'))->toOthers();

        return back()->with('status', "Live feed switched! Tablet [{$tablet->device_identifier}] is now streaming Desktop [{$desktop->device_identifier}].");
    }

    /**
     * Get JSON list of all registered tablets and direct links.
     */
    public function tabletLinks()
    {
        $tablets = Device::tablets()
            ->with(['assignedDesktops'])
            ->orderBy('device_identifier')
            ->get()
            ->map(function ($t) {
                $desktop = $t->assignedDesktops->first();
                return [
                    'id' => $t->id,
                    'identifier' => $t->device_identifier,
                    'name' => $t->name,
                    'location' => $t->location,
                    'status' => $t->status,
                    'direct_url' => url('/t/' . $t->device_identifier),
                    'assigned_desktop' => $desktop ? [
                        'id' => $desktop->id,
                        'identifier' => $desktop->device_identifier,
                        'name' => $desktop->name,
                        'status' => $desktop->status,
                        'stream_status' => $desktop->stream_status,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'tablets' => $tablets,
        ]);
    }

    /**
     * Auto-generate next device identifier.
     */
    protected function generateNextIdentifier(string $type = 'tablet'): string
    {
        if ($type === 'tablet') {
            $existing = Device::withTrashed()
                ->where('device_type', 'tablet')
                ->pluck('device_identifier')
                ->map(fn($id) => strtoupper(trim($id)))
                ->all();

            $i = 1;
            while (in_array(sprintf('TAB-%03d', $i), $existing, true)) {
                $i++;
            }
            return sprintf('TAB-%03d', $i);
        }

        do {
            $code = 'DESK-' . strtoupper(bin2hex(random_bytes(2)));
        } while (Device::withTrashed()->where('device_identifier', $code)->exists());

        return $code;
    }


    /**
     * Toggle device active/disabled state.
     */
    public function toggleStatus(Request $request, $id)
    {
        $device = Device::findOrFail($id);

        if ($device->status === 'disabled') {
            $device->update(['status' => 'offline']);
            $action = 'enabled';
            broadcast(new DeviceStatusChanged($device))->toOthers();
        } else {
            $device->update(['status' => 'disabled']);
            // Revoke active credentials
            $device->credentials()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            // Clear presence cache
            Cache::store('file')->forget("device_presence:{$device->id}");
            $action = 'disabled';

            // Realtime revocation notification to the tablet's private channel
            broadcast(new DeviceRevoked($device, 'ADMIN_DISABLED'))->toOthers();
            // Realtime presence update to Admin Control Center
            broadcast(new DeviceStatusChanged($device))->toOthers();
        }

        DeviceAuditLog::create([
            'device_id' => $device->id,
            'event_type' => "device_{$action}",
            'severity' => 'warning',
            'ip_address' => $request->ip(),
            'details' => ['new_status' => $device->status],
        ]);

        return back()->with('status', "Device has been {$action}.");
    }

    /**
     * Delete the specified device.
     */
    public function destroy(Request $request, $id)
    {
        $device = Device::findOrFail($id);
        $identifier = $device->device_identifier;

        // Revoke credentials and notify device before deletion
        $device->credentials()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        Cache::store('file')->forget("device_presence:{$device->id}");

        broadcast(new DeviceRevoked($device, 'DEVICE_DELETED'))->toOthers();

        $device->delete();

        $device->status = 'offline';
        broadcast(new DeviceStatusChanged($device))->toOthers();

        DeviceAuditLog::create([
            'device_id' => $device->id,
            'event_type' => 'device_deleted',
            'severity' => 'warning',
            'ip_address' => $request->ip(),
            'details' => ['identifier' => $identifier],
        ]);

        return redirect()->route('admin.devices.index')
            ->with('status', "Device {$identifier} was removed.");
    }

    /**
     * Return instantaneous presence and stream statuses for all fleet devices.
     */
    public function realtimeStatus(DevicePresenceService $presenceService)
    {
        $data = Cache::remember('api_fleet_realtime_status_cache', 1, function () use ($presenceService) {
            $presenceService->sweepPresence();

            $devices = Device::with(['activeViewingSession.desktop'])
                ->select([
                    'id',
                    'uuid',
                    'device_identifier',
                    'name',
                    'location',
                    'device_type',
                    'status',
                    'stream_status',
                    'active_viewers_count',
                    'last_seen_at',
                ])->get()->map(function ($d) {
                    $activeSession = $d->isTablet() ? $d->activeViewingSession : null;
                    return [
                        'id' => $d->id,
                        'uuid' => $d->uuid,
                        'identifier' => $d->device_identifier,
                        'name' => $d->name,
                        'location' => $d->location,
                        'device_type' => $d->device_type,
                        'status' => $d->status,
                        'stream_status' => $d->stream_status,
                        'active_viewers_count' => $d->active_viewers_count,
                        'last_seen_human' => ($d->status === 'online') ? 'Just now' : ($d->last_seen_at ? $d->last_seen_at->diffForHumans() : 'Never'),
                        'currently_viewing' => ($activeSession && $activeSession->desktop) ? [
                            'desktop_id' => $activeSession->desktop->id,
                            'desktop_identifier' => $activeSession->desktop->device_identifier,
                            'desktop_name' => $activeSession->desktop->name,
                            'started_at_ts' => $activeSession->started_at ? $activeSession->started_at->timestamp : now()->timestamp,
                            'started_at_human' => $activeSession->started_at ? $activeSession->started_at->format('H:i:s') : 'Just now',
                        ] : null,
                    ];
                });

            $activeSessions = WebRtcSession::with(['desktop', 'tablet'])
                ->whereIn('status', ['initiating', 'connected'])
                ->whereNull('ended_at')
                ->latest('started_at')
                ->get()
                ->map(function ($s) {
                    $start = $s->started_at ?: now();
                    $diffSeconds = max(0, now()->timestamp - $start->timestamp);
                    return [
                        'id' => $s->id,
                        'session_id' => $s->session_id,
                        'tablet_id' => $s->tablet_id,
                        'tablet_identifier' => $s->tablet?->device_identifier ?? 'Unknown Tablet',
                        'tablet_name' => $s->tablet?->name ?? 'Unknown Tablet',
                        'desktop_id' => $s->desktop_id,
                        'desktop_identifier' => $s->desktop?->device_identifier ?? 'Unknown Desktop',
                        'desktop_name' => $s->desktop?->name ?? 'Unknown Desktop',
                        'status' => $s->status,
                        'started_at_ts' => $start->timestamp,
                        'started_at_ist' => $start->timezone('Asia/Kolkata')->format('h:i:s A'),
                        'duration_seconds' => $diffSeconds,
                        'duration_formatted' => gmdate('H:i:s', $diffSeconds),
                    ];
                });

            return [
                'success' => true,
                'timestamp' => now()->timestamp,
                'devices' => $devices,
                'active_sessions' => $activeSessions,
                'stats' => [
                    'online_desktops' => Device::desktops()->where('status', 'online')->count(),
                    'streaming_desktops' => Device::desktops()->where('stream_status', 'streaming')->count(),
                    'online_tablets' => Device::tablets()->where('status', 'online')->count(),
                    'active_sessions' => $activeSessions->count(),
                ],
            ];
        });

        return response()->json($data);
    }
}
