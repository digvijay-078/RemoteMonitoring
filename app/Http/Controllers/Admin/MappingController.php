<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesktopTabletMapping;
use App\Models\Device;
use App\Models\WebRtcSession;
use App\Services\WebRtcSignallingService;
use Illuminate\Http\Request;

class MappingController extends Controller
{
    public function __construct(
        protected WebRtcSignallingService $signallingService
    ) {}

    /**
     * Display a listing of desktop-tablet mappings.
     */
    public function index()
    {
        $mappings = DesktopTabletMapping::with(['tablet', 'desktop', 'creator'])
            ->latest()
            ->paginate(20);

        $tablets = Device::where('device_type', 'tablet')
            ->where('status', '!=', 'disabled')
            ->orderBy('name')
            ->get();

        $desktops = Device::where('device_type', 'desktop')
            ->where('status', '!=', 'disabled')
            ->orderBy('name')
            ->get();

        return view('admin.mappings.index', compact('mappings', 'tablets', 'desktops'));
    }

    /**
     * Store a newly created mapping.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tablet_id' => ['required', 'exists:devices,id'],
            'desktop_id' => ['required', 'exists:devices,id', 'different:tablet_id'],
        ]);

        $tablet = Device::findOrFail($validated['tablet_id']);
        $desktop = Device::findOrFail($validated['desktop_id']);

        if ($tablet->device_type !== 'tablet') {
            return back()->withErrors(['tablet_id' => 'The selected device is not a tablet.']);
        }

        if ($desktop->device_type !== 'desktop') {
            return back()->withErrors(['desktop_id' => 'The selected device is not a desktop.']);
        }

        // Reassign or create mapping for this tablet
        DesktopTabletMapping::where('tablet_id', $tablet->id)->delete();

        DesktopTabletMapping::create([
            'tablet_id' => $tablet->id,
            'desktop_id' => $desktop->id,
            'created_by' => auth()->id(),
        ]);

        // Broadcast real-time switch signal to the Tablet
        broadcast(new \App\Events\TabletMappingChanged($tablet, $desktop, 'assigned'))->toOthers();

        return redirect()->route('admin.mappings.index')
            ->with('success', "Assigned Tablet [{$tablet->device_identifier}] to stream Desktop [{$desktop->device_identifier}]. Live feed switched!")
            ->with('tablet_identifier', $tablet->device_identifier);
    }

    /**
     * Remove the specified mapping.
     */
    public function destroy(DesktopTabletMapping $mapping)
    {
        $tablet = $mapping->tablet;
        $desktop = $mapping->desktop;

        // Terminate any active sessions between this tablet and desktop
        $activeSessions = WebRtcSession::where('desktop_id', $mapping->desktop_id)
            ->where('tablet_id', $mapping->tablet_id)
            ->whereIn('status', ['initiating', 'connected'])
            ->get();

        foreach ($activeSessions as $session) {
            if ($desktop) {
                $this->signallingService->updateSessionStatus(
                    $desktop,
                    $session->session_id,
                    'terminated',
                    'Mapping authorization was revoked by administrator'
                );
            }
        }

        $mapping->delete();

        if ($tablet) {
            broadcast(new \App\Events\TabletMappingChanged($tablet, null, 'unassigned'))->toOthers();
        }

        return redirect()->route('admin.mappings.index')->with('success', "Revoked authorization between Tablet [{$tablet?->device_identifier}] and Desktop [{$desktop?->device_identifier}].");
    }
}
