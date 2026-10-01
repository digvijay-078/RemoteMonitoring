<?php

use App\Http\Controllers\Api\DevicePairingController;
use App\Http\Controllers\Api\DeviceRealtimeController;
use App\Http\Controllers\Api\WebRtcSignallingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Device Pairing Handshake (Public endpoint, rate-limited)
    Route::post('device/pair', [DevicePairingController::class, 'pair']);
    Route::post('devices/pair', [DevicePairingController::class, 'pair']);

    // Zero-Touch Auto-Enrollment for Desktops
    Route::post('device/auto-register', [DevicePairingController::class, 'autoRegister']);
    Route::post('devices/auto-register', [DevicePairingController::class, 'autoRegister']);

    // One-Click Direct Tablet Auto-Pair (Bypasses manual code entry)
    Route::post('tablet/direct-auth', [DevicePairingController::class, 'directTabletAuth']);

    // Authenticated Device Endpoints
    Route::middleware('device.auth')->group(function () {
        Route::prefix('device')->group(function () {
            Route::get('me', function (Request $request) {
                $device = $request->attributes->get('device');
                return response()->json([
                    'success' => true,
                    'device' => [
                        'uuid' => $device->uuid,
                        'identifier' => $device->device_identifier,
                        'name' => $device->name,
                        'device_type' => $device->device_type,
                        'status' => $device->status,
                        'stream_status' => $device->stream_status,
                    ],
                ]);
            });

            // Realtime WebSocket channel authorization
            Route::post('broadcasting/auth', [DeviceRealtimeController::class, 'authenticateChannel']);

            // Application-level device heartbeat
            Route::post('heartbeat', [DeviceRealtimeController::class, 'heartbeat']);

            // Immediate device disconnect / unload signal
            Route::post('disconnect', [DeviceRealtimeController::class, 'disconnect']);
        });

        // Tablet APIs
        Route::prefix('tablet')->group(function () {
            Route::get('desktops', [WebRtcSignallingController::class, 'getAssignedDesktops']);
        });

        // WebRTC Signalling Control Plane
        Route::prefix('webrtc')->group(function () {
            Route::post('session/initiate', [WebRtcSignallingController::class, 'initiateSession']);
            Route::post('signal/offer', [WebRtcSignallingController::class, 'sendOffer']);
            Route::post('signal/answer', [WebRtcSignallingController::class, 'sendAnswer']);
            Route::post('signal/ice-candidate', [WebRtcSignallingController::class, 'sendIceCandidate']);
            Route::post('session/status', [WebRtcSignallingController::class, 'updateSessionStatus']);
        });
    });
});


