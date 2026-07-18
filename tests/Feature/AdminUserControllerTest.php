<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_update_account_membership_roles_and_permissions(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);
        $managedUser = User::factory()->create();

        $account = Account::query()->create([
            'owner_id' => $managedUser->id,
            'name' => 'Moonvera',
            'slug' => 'moonvera',
            'plan_key' => 'pro',
        ]);

        $oldRole = Role::query()->create([
            'name' => 'viewer',
            'label' => 'Viewer',
        ]);

        $newRole = Role::query()->create([
            'name' => 'editor',
            'label' => 'Editor',
        ]);

        Permission::query()->create([
            'name' => 'topics.manage',
            'label' => 'Manage topics',
        ]);

        Permission::query()->create([
            'name' => 'blogs.publish',
            'label' => 'Publish blogs',
        ]);

        $membership = AccountUser::query()->create([
            'account_id' => $account->id,
            'user_id' => $managedUser->id,
            'role_id' => $oldRole->id,
            'status' => 'invited',
            'permissions' => ['topics.view'],
        ]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$managedUser->id}", [
            'name' => 'Updated Member',
            'email' => $managedUser->email,
            'global_role' => 'manager',
            'current_account_id' => $account->id,
            'memberships' => [[
                'id' => $membership->id,
                'role_id' => $newRole->id,
                'status' => 'active',
                'permissions' => ['topics.manage', 'blogs.publish'],
            ]],
        ]);

        $response->assertRedirect("/admin/users/{$managedUser->id}/edit");

        $managedUser->refresh();
        $membership->refresh();

        $this->assertSame('Updated Member', $managedUser->name);
        $this->assertSame('manager', $managedUser->global_role);
        $this->assertSame($account->id, $managedUser->current_account_id);
        $this->assertSame($newRole->id, $membership->role_id);
        $this->assertSame('active', $membership->status);
        $this->assertSame(['topics.manage', 'blogs.publish'], $membership->permissions);
        $this->assertNotNull($membership->accepted_at);
    }

    public function test_internal_team_index_only_shows_platform_staff(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);
        $manager = User::factory()->create(['global_role' => 'manager', 'name' => 'Manager User']);
        User::factory()->create(['global_role' => 'user', 'name' => 'Customer User']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 2)
            )
            ->assertSee($manager->name)
            ->assertDontSee('Customer User');
    }

    public function test_manager_cannot_promote_user_to_super_admin(): void
    {
        $manager = User::factory()->create(['global_role' => 'manager']);
        $managedUser = User::factory()->create();

        $response = $this->actingAs($manager)->patch("/admin/users/{$managedUser->id}", [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'global_role' => 'super_admin',
            'current_account_id' => null,
            'memberships' => [],
        ]);

        $response->assertSessionHasErrors('global_role');
        $this->assertFalse($managedUser->fresh()->isSuperAdmin());
    }

    public function test_manager_cannot_edit_super_admin(): void
    {
        $manager = User::factory()->create(['global_role' => 'manager']);
        $superAdmin = User::factory()->create(['global_role' => 'super_admin']);

        $this->actingAs($manager)
            ->get("/admin/users/{$superAdmin->id}/edit")
            ->assertForbidden();
    }

    public function test_cannot_remove_last_super_admin(): void
    {
        $superAdmin = User::factory()->create(['global_role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->patch("/admin/users/{$superAdmin->id}", [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'global_role' => 'manager',
            'current_account_id' => null,
            'memberships' => [],
        ]);

        $response->assertSessionHasErrors('global_role');
        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
    }
}
