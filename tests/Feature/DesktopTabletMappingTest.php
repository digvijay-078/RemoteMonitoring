<?php

namespace Tests\Feature;

use App\Models\DesktopTabletMapping;
use App\Models\Device;
use App\Models\User;
use App\Models\WebRtcSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DesktopTabletMappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Device $tablet;
    protected Device $desktop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->tablet = Device::create([
            'uuid' => (string) Str::uuid(),
            'device_identifier' => 'TAB-001',
            'name' => 'Field Tablet 1',
            'location' => 'Zone A',
            'device_type' => 'tablet',
            'status' => 'online',
        ]);

        $this->desktop = Device::create([
            'uuid' => (string) Str::uuid(),
            'device_identifier' => 'DESK-001',
            'name' => 'CAD Workstation 1',
            'location' => 'Office Floor 2',
            'device_type' => 'desktop',
            'status' => 'online',
        ]);
    }

    public function test_admin_can_view_mappings_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/mappings');
        $response->assertStatus(200);
        $response->assertSee('Desktop ↔ Tablet Authorizations');
    }

    public function test_admin_can_authorize_mapping(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/mappings', [
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $response->assertRedirect('/admin/mappings');
        $this->assertDatabaseHas('desktop_tablet_mappings', [
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);
    }

    public function test_cannot_create_duplicate_mapping(): void
    {
        DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/mappings', [
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $response->assertSessionHasErrors('desktop_id');
        $this->assertEquals(1, DesktopTabletMapping::count());
    }

    public function test_cannot_map_desktop_as_tablet_or_vice_versa(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/mappings', [
            'tablet_id' => $this->desktop->id, // desktop passed as tablet
            'desktop_id' => $this->tablet->id,
        ]);

        $response->assertSessionHasErrors('tablet_id');
    }

    public function test_admin_can_revoke_mapping_and_terminates_active_sessions(): void
    {
        $mapping = DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $session = WebRtcSession::create([
            'session_id' => (string) Str::uuid(),
            'desktop_id' => $this->desktop->id,
            'tablet_id' => $this->tablet->id,
            'status' => 'connected',
            'started_at' => now(),
            'connected_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/mappings/{$mapping->id}");
        $response->assertRedirect('/admin/mappings');

        $this->assertDatabaseMissing('desktop_tablet_mappings', ['id' => $mapping->id]);
        $this->assertEquals('terminated', $session->fresh()->status);
    }
}
