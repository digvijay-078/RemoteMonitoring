<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\PairingService;
use Illuminate\Http\Request;

class PairingController extends Controller
{
    public function __construct(
        protected PairingService $pairingService
    ) {}

    /**
     * Generate a new pairing ticket for the device.
     */
    public function generate(Request $request, $deviceId)
    {
        $device = Device::findOrFail($deviceId);

        $ticket = $this->pairingService->generatePairingTicket(
            device: $device,
            userId: auth()->id(),
            ttlMinutes: 10
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'pairing_code' => $ticket->pairing_code,
                'expires_at' => $ticket->expires_at->toIso8601String(),
                'qr_payload' => json_encode([
                    'type' => 'rmt_pair',
                    'code' => $ticket->pairing_code,
                    'token' => $ticket->pairing_token,
                    'url' => url('/tablet?code=' . $ticket->pairing_code),
                ]),
            ]);
        }

        return back()->with('pairing_ticket', [
            'code' => $ticket->pairing_code,
            'expires_at' => $ticket->expires_at,
            'qr_payload' => url('/tablet?code=' . $ticket->pairing_code),
        ])->with('status', "Generated new pairing code: {$ticket->pairing_code}");
    }
}
