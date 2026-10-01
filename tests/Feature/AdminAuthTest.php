<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin_pages(): void
    {
        $response = $this->get('/admin/overview');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_login_page(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
        $response->assertSee('RemoteMonitor');
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_cannot_login_with_wrong_password(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_non_admin_user_is_rejected_from_admin_login(): void
    {
        User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => Hash::make('password123'),
            'role' => 'viewer',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'user@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_logout(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $response = $this->post('/admin/logout');
        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
