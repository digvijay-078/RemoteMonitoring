<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PairDeviceRequest;
use App\Services\PairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DevicePairingController extends Controller
{
    public function __construct(
        protected PairingService $pairingService
    ) {}

    /**
     * Authenticate and pair a tablet using an active pairing code.
     */
    public function pair(PairDeviceRequest $request): JsonResponse
    {
        try {
            $result = $this->pairingService->pairDevice(
                pairingCode: $request->input('pairing_code'),
                hardwareInfo: $request->input('hardware_info'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'success' => true,
                'message' => 'Device paired successfully.',
                'data' => $result,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first() ?: 'Pairing failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during pairing.',
            ], 500);
        }
    }

    /**
     * Auto-enroll a desktop machine without manual pairing codes (Zero-Touch Provisioning).
     */
    public function autoRegister(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $hostname = $request->input('hostname') ?: 'DESKTOP-PC';
            $username = $request->input('username');
            $machineId = $request->input('machine_id');
            $screenResolution = $request->input('screen_resolution', '1920x1080');
            $fps = (int) $request->input('fps', 30);
            $hardwareInfo = $request->input('hardware_info', []);

            $result = $this->pairingService->autoRegisterDesktop(
                hostname: $hostname,
                username: $username,
                machineId: $machineId,
                screenResolution: $screenResolution,
                fps: $fps,
                hardwareInfo: is_array($hardwareInfo) ? $hardwareInfo : [],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'success' => true,
                'message' => 'Desktop auto-enrolled successfully.',
                'token' => $result['device_token'],
                'device_token' => $result['device_token'],
                'server_url' => $result['server_url'],
                'ws_url' => $result['ws_url'],
                'app_key' => $result['app_key'],
                'device' => $result['device'],
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auto-enrollment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * One-Click Direct Tablet Login / Auto-Pair.
     * Takes a tablet identifier/ID (e.g. TAB-001) and issues an active token immediately.
     */
    public function directTabletAuth(\Illuminate\Http\Request $request): JsonResponse
    {
        $identifier = trim($request->input('device') ?: $request->input('identifier') ?: $request->input('t') ?: '');

        if (!$identifier) {
            return response()->json([
                'success' => false,
                'message' => 'Tablet identifier is required.',
            ], 422);
        }

        $device = \App\Models\Device::where(function ($q) use ($identifier) {
            $q->where('device_identifier', $identifier)
              ->orWhere('uuid', $identifier);
            if (is_numeric($identifier)) {
                $q->orWhere('id', (int) $identifier);
            }
        })->where('device_type', 'tablet')->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => "Tablet '{$identifier}' not found.",
            ], 404);
        }

        if ($device->status === 'disabled') {
            return response()->json([
                'success' => false,
                'message' => 'Tablet access is currently disabled by administrator.',
            ], 403);
        }

        $plainToken = 'rmt_live_' . bin2hex(random_bytes(32));
        $tokenPrefix = substr($plainToken, 0, 16);
        $tokenHash = hash('sha256', $plainToken);

        $credential = \App\Models\DeviceCredential::where('device_id', $device->id)
            ->whereNull('revoked_at')
            ->first();

        if (!$credential) {
            \App\Models\DeviceCredential::create([
                'device_id' => $device->id,
                'token_prefix' => $tokenPrefix,
                'token_hash' => $tokenHash,
                'last_used_at' => now(),
                'created_at' => now(),
            ]);
        } else {
            $credential->update([
                'token_prefix' => $tokenPrefix,
                'token_hash' => $tokenHash,
                'last_used_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'token' => $plainToken,
            'device_token' => $plainToken,
            'device' => [
                'id' => $device->id,
                'uuid' => $device->uuid,
                'identifier' => $device->device_identifier,
                'device_identifier' => $device->device_identifier,
                'name' => $device->name,
                'device_type' => $device->device_type,
                'status' => $device->status,
            ],
            'data' => [
                'device_token' => $plainToken,
                'device' => [
                    'id' => $device->id,
                    'uuid' => $device->uuid,
                    'identifier' => $device->device_identifier,
                    'device_identifier' => $device->device_identifier,
                    'name' => $device->name,
                    'device_type' => $device->device_type,
                    'status' => $device->status,
                ],
            ],
        ]);
    }
}
