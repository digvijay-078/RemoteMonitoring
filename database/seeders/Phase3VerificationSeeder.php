<?php

namespace Database\Seeders;

use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DevicePairingTicket;
use App\Models\Monitor;
use App\Models\User;
use App\Services\DeviceConfigService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Phase3VerificationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@remotemonitor.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123456'),
                'role' => 'admin',
            ]
        );

        // 2. Dashboards
        $dash1 = Dashboard::updateOrCreate(
            ['name' => 'ICU Core Vitals'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Realtime physiological monitoring simulation',
                'refresh_rate_ms' => 1000,
                'version' => 1,
                'layout_config' => [
                    'columns' => 2,
                    'widgets' => [
                        ['id' => 'w-1', 'type' => 'vital_ecg', 'title' => 'ECG Lead II Waveform', 'color' => '#10b981', 'speed' => 2],
                        ['id' => 'w-2', 'type' => 'metric_card', 'title' => 'Heart Rate', 'value' => 74, 'unit' => 'BPM', 'status' => 'normal'],
                        ['id' => 'w-3', 'type' => 'metric_card', 'title' => 'SpO2 Oxygen', 'value' => 99, 'unit' => '%', 'status' => 'normal'],
                        ['id' => 'w-4', 'type' => 'metric_card', 'title' => 'Blood Pressure', 'value' => '120/80', 'unit' => 'mmHg', 'status' => 'normal'],
                    ],
                ],
            ]
        );

        $dash2 = Dashboard::updateOrCreate(
            ['name' => 'Telemetry & Signal Diagnostics'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Multi-channel sensor telemetry diagnostics',
                'refresh_rate_ms' => 1500,
                'version' => 1,
                'layout_config' => [
                    'columns' => 2,
                    'widgets' => [
                        ['id' => 'w-21', 'type' => 'waveform_stream', 'title' => 'Sensor Raw Signal Stream', 'color' => '#6366f1', 'speed' => 3],
                        ['id' => 'w-22', 'type' => 'metric_card', 'title' => 'RMS Amplitude', 'value' => 1.48, 'unit' => 'V', 'status' => 'normal'],
                        ['id' => 'w-23', 'type' => 'status_indicator', 'title' => 'RF Signal Quality', 'state' => 'healthy', 'description' => 'Noise floor: -92 dBm'],
                        ['id' => 'w-24', 'type' => 'alert_feed', 'title' => 'Telemetry Alarms', 'items' => [
                            ['severity' => 'info', 'message' => 'Sensor calibrated', 'time' => '10:15'],
                            ['severity' => 'normal', 'message' => 'Baseline steady', 'time' => '10:20'],
                        ]],
                    ],
                ],
            ]
        );

        $dash3 = Dashboard::updateOrCreate(
            ['name' => 'System Infrastructure Feed'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Fleet server and edge node metrics',
                'refresh_rate_ms' => 2000,
                'version' => 1,
                'layout_config' => [
                    'columns' => 2,
                    'widgets' => [
                        ['id' => 'w-31', 'type' => 'metric_card', 'title' => 'CPU Utilization', 'value' => 38, 'unit' => '%', 'status' => 'normal'],
                        ['id' => 'w-32', 'type' => 'metric_card', 'title' => 'Memory Committed', 'value' => 640, 'unit' => 'MB', 'status' => 'normal'],
                        ['id' => 'w-33', 'type' => 'status_indicator', 'title' => 'Database Sync', 'state' => 'healthy', 'description' => 'Replication latency: 1ms'],
                        ['id' => 'w-34', 'type' => 'alert_feed', 'title' => 'Cluster Events', 'items' => [
                            ['severity' => 'info', 'message' => 'Heartbeat sweep active', 'time' => '10:00'],
                        ]],
                    ],
                ],
            ]
        );

        $configService = app(DeviceConfigService::class);

        // 3. Tablet A: TAB-001
        $deviceA = Device::updateOrCreate(
            ['device_identifier' => 'TAB-001'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'ICU Central Display',
                'location' => 'Critical Care Wing 3B',
                'status' => 'pending_pair',
                'view_mode' => 'single',
                'cycle_interval_seconds' => 15,
                'config_version' => 1,
            ]
        );
        $configService->ensureSlotsInitialized($deviceA);
        // Assign Dashboard 1 to Slot 1
        $deviceA->monitors()->where('slot_number', 1)->update([
            'name' => 'Vital Signs Slot 01',
            'is_enabled' => true,
            'dashboard_id' => $dash1->id,
        ]);
        // Assign Dashboard 2 to Slot 2
        $deviceA->monitors()->where('slot_number', 2)->update([
            'name' => 'Diagnostics Slot 02',
            'is_enabled' => true,
            'dashboard_id' => $dash2->id,
        ]);

        DevicePairingTicket::where('device_id', $deviceA->id)->delete();
        DevicePairingTicket::create([
            'device_id' => $deviceA->id,
            'pairing_code' => 'RM-TAB1',
            'pairing_token' => Str::random(40),
            'status' => 'active',
            'expires_at' => now()->addHours(6),
            'created_by_user_id' => $admin->id,
        ]);

        // 4. Tablet B: TAB-002
        $deviceB = Device::updateOrCreate(
            ['device_identifier' => 'TAB-002'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Emergency Room Display',
                'location' => 'ER Triage Bay 2',
                'status' => 'pending_pair',
                'view_mode' => 'single',
                'cycle_interval_seconds' => 15,
                'config_version' => 1,
            ]
        );
        $configService->ensureSlotsInitialized($deviceB);
        $deviceB->monitors()->where('slot_number', 1)->update([
            'name' => 'Triage Feed Slot 01',
            'is_enabled' => true,
            'dashboard_id' => $dash3->id,
        ]);

        DevicePairingTicket::where('device_id', $deviceB->id)->delete();
        DevicePairingTicket::create([
            'device_id' => $deviceB->id,
            'pairing_code' => 'RM-TAB2',
            'pairing_token' => Str::random(40),
            'status' => 'active',
            'expires_at' => now()->addHours(6),
            'created_by_user_id' => $admin->id,
        ]);
    }
}
