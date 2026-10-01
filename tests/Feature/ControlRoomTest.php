<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ControlRoomTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Controller',
            'email' => 'admin@controlroom.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    public function test_guest_is_redirected_from_control_room(): void
    {
        $response = $this->get('/admin/control-room');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_control_room_and_desktops(): void
    {
        $desktop = Device::create([
            'name' => 'DESK-HQ-01',
            'location' => 'Floor 1 HQ',
            'device_type' => 'desktop',
            'device_identifier' => 'DESK-001',
            'status' => 'online',
            'stream_status' => 'streaming',
            'fps' => 30,
            'screen_resolution' => '1920x1080',
            'ip_address' => '192.168.1.10',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/control-room');

        $response->assertStatus(200);
        $response->assertSee('Security Control Room');
        $response->assertSee('Master Video Wall');
        $response->assertSee('DESK-HQ-01');
        $response->assertSee('CAM 01');
    }

    public function test_control_room_devices_json_endpoint(): void
    {
        Device::create([
            'name' => 'DESK-LAB-02',
            'location' => 'Remote Lab',
            'device_type' => 'desktop',
            'device_identifier' => 'DESK-002',
            'status' => 'online',
            'stream_status' => 'streaming',
            'fps' => 30,
            'screen_resolution' => '1920x1080',
            'ip_address' => '192.168.1.11',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/control-room/devices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'timestamp',
            'devices' => [
                '*' => [
                    'id',
                    'uuid',
                    'identifier',
                    'name',
                    'status',
                    'stream_status',
                    'fps',
                    'stream_url',
                    'audio_url',
                ]
            ]
        ]);
        $response->assertJsonFragment([
            'name' => 'DESK-LAB-02',
            'identifier' => 'DESK-002',
        ]);
    }
}
