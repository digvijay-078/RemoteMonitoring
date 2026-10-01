<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin@devices.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_create_device_with_auto_generated_identifier(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/devices', [
            'name' => 'ICU Monitor',
            'location' => 'Ward 4',
            'view_mode' => 'single',
            'cycle_interval_seconds' => 30,
        ]);

        $this->assertDatabaseHas('devices', [
            'name' => 'ICU Monitor',
            'location' => 'Ward 4',
            'device_identifier' => 'TAB-001',
            'status' => 'pending_pair',
        ]);

        $device = Device::where('device_identifier', 'TAB-001')->first();
        $this->assertNotNull($device);

        $response->assertRedirect('/admin/devices/' . $device->id);
    }

    public function test_device_identifier_must_be_unique(): void
    {
        Device::create([
            'device_identifier' => 'TAB-999',
            'name' => 'First Tablet',
            'location' => 'Room A',
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/devices', [
            'device_identifier' => 'TAB-999',
            'name' => 'Duplicate Tablet',
            'location' => 'Room B',
        ]);

        $response->assertSessionHasErrors('device_identifier');
    }

    public function test_admin_can_view_device_details(): void
    {
        $device = Device::create([
            'device_identifier' => 'TAB-010',
            'name' => 'Pediatrics Hall',
            'location' => 'Floor 2',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/devices/' . $device->id);
        $response->assertStatus(200);
        $response->assertSee('TAB-010');
        $response->assertSee('Pediatrics Hall');
    }

    public function test_admin_can_update_device_details(): void
    {
        $device = Device::create([
            'device_identifier' => 'TAB-015',
            'name' => 'Old Name',
            'location' => 'Old Location',
        ]);

        $response = $this->actingAs($this->admin)->put('/admin/devices/' . $device->id, [
            'name' => 'New Name',
            'location' => 'New Location',
        ]);

        $response->assertRedirect('/admin/devices/' . $device->id);
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'name' => 'New Name',
            'location' => 'New Location',
        ]);
    }

    public function test_admin_can_toggle_device_disable_and_enable(): void
    {
        $device = Device::create([
            'device_identifier' => 'TAB-020',
            'name' => 'Toggle Device',
            'location' => 'Lobby',
            'status' => 'online',
        ]);

        // Toggle to disabled
        $this->actingAs($this->admin)->post('/admin/devices/' . $device->id . '/toggle');
        $this->assertEquals('disabled', $device->fresh()->status);

        // Toggle back to offline/enabled
        $this->actingAs($this->admin)->post('/admin/devices/' . $device->id . '/toggle');
        $this->assertEquals('offline', $device->fresh()->status);
    }
}
