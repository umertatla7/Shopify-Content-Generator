<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_loads_without_customer_account(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect('/admin/dashboard');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_super_admin_customer_routes_redirect_to_admin_routes(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);

        $this->actingAs($admin)->get('/stores')->assertRedirect('/admin/stores');
        $this->actingAs($admin)->get('/topics')->assertRedirect('/admin/topics');
        $this->actingAs($admin)->get('/blogs')->assertRedirect('/admin/blogs');
    }
}
