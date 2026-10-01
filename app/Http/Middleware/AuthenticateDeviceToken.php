<?php

namespace App\Http\Middleware;

use App\Models\DeviceCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceToken
{
    /**
     * Handle an incoming device request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');
        $plainToken = null;

        if ($header && str_starts_with($header, 'Bearer ')) {
            $plainToken = substr($header, 7);
        } elseif ($request->input('token')) {
            $plainToken = $request->input('token');
        } elseif ($request->query('token')) {
            $plainToken = $request->query('token');
        }

        if (!$plainToken) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or malformed device bearer token.',
            ], 401);
        }

        $tokenHash = hash('sha256', $plainToken);

        $credential = DeviceCredential::where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (!$credential) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or revoked device token.',
            ], 401);
        }

        // Include trashed devices and auto-restore if active agent connects
        $device = \App\Models\Device::withTrashed()->find($credential->device_id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device record not found.',
            ], 401);
        }

        if ($device->trashed()) {
            $device->restore();
            $device->update(['status' => 'online', 'last_seen_at' => now()]);
        }

        if ($device->status === 'disabled') {
            return response()->json([
                'success' => false,
                'message' => 'Device access has been disabled by administrator.',
            ], 403);
        }

        // Touch last_used_at without write amplification on frequent heartbeats
        if (!$request->is('*/heartbeat') || !$credential->last_used_at || $credential->last_used_at->diffInSeconds(now()) > 300) {
            $credential->update(['last_used_at' => now()]);
        }

        // Attach device to request attributes
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
