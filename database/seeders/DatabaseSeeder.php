<?php

namespace Database\Seeders;

use App\Models\FeatureModule;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'accounts.manage', 'label' => 'Manage accounts'],
            ['name' => 'team.manage', 'label' => 'Manage team members'],
            ['name' => 'stores.view', 'label' => 'View Shopify stores'],
            ['name' => 'stores.manage', 'label' => 'Connect and manage Shopify stores'],
            ['name' => 'stores.sync', 'label' => 'Sync Shopify data'],
            ['name' => 'analysis.run', 'label' => 'Run AI store analysis'],
            ['name' => 'topics.manage', 'label' => 'Generate and approve topics'],
            ['name' => 'blogs.review', 'label' => 'Review blogs'],
            ['name' => 'blogs.edit', 'label' => 'Edit blogs'],
            ['name' => 'blogs.approve', 'label' => 'Approve blogs'],
            ['name' => 'blogs.publish', 'label' => 'Publish blogs'],
            ['name' => 'analytics.view', 'label' => 'View analytics'],
            ['name' => 'billing.manage', 'label' => 'Manage billing'],
        ])->mapWithKeys(fn (array $permission) => [
            $permission['name'] => Permission::query()->updateOrCreate(
                ['name' => $permission['name']],
                $permission
            ),
        ]);

        $rolePermissions = [
            'agency_admin' => $permissions->keys()->all(),
            'customer_admin' => $permissions->keys()->all(),
            'store_owner' => [
                'stores.view',
                'stores.manage',
                'stores.sync',
                'analysis.run',
                'topics.manage',
                'blogs.review',
                'blogs.edit',
                'blogs.approve',
                'blogs.publish',
                'analytics.view',
            ],
            'team_member' => [
                'stores.view',
                'blogs.review',
                'blogs.edit',
                'analytics.view',
            ],
        ];

        collect([
            ['name' => 'agency_admin', 'label' => 'Agency Admin'],
            ['name' => 'customer_admin', 'label' => 'Customer Admin'],
            ['name' => 'store_owner', 'label' => 'Store Owner'],
            ['name' => 'team_member', 'label' => 'Team Member'],
        ])->each(function (array $role) use ($permissions, $rolePermissions): void {
            $model = Role::query()->updateOrCreate(['name' => $role['name']], $role);

            $model->permissions()->sync(
                collect($rolePermissions[$role['name']])
                    ->map(fn (string $name) => $permissions[$name]->id)
                    ->all()
            );
        });

        Plan::query()->updateOrCreate(
            ['key' => 'starter'],
            [
                'name' => 'Starter',
                'monthly_price' => 0,
                'monthly_blog_limit' => 40,
                'monthly_ai_token_limit' => 500000,
                'monthly_credit_allowance' => 1000,
                'word_limit_estimate' => 10000,
                'store_limit' => 1,
                'user_limit' => 1,
                'product_description_limit' => 70,
                'collection_description_limit' => 10,
                'credit_expires_after_days' => 30,
                'features' => ['phase_1_blog_manager'],
                'is_active' => true,
            ]
        );

        collect([
            ['key' => 'product_optimization', 'name' => 'Product Optimization', 'description' => 'Phase 2 product descriptions, SEO titles, meta descriptions, and bulk Shopify product updates.'],
            ['key' => 'image_generation', 'name' => 'AI Image Generation', 'description' => 'Phase 3 product image generation and lifestyle image creation.'],
            ['key' => 'background_removal', 'name' => 'Background Removal', 'description' => 'Phase 3 product image background removal tools.'],
            ['key' => 'competitor_analysis', 'name' => 'Competitor Analysis', 'description' => 'Phase 4 competitor research and SEO intelligence.'],
            ['key' => 'content_calendar_automation', 'name' => 'Content Calendar Automation', 'description' => 'Phase 4 autonomous monthly planning, generation, and publishing.'],
        ])->each(fn (array $module) => FeatureModule::query()->updateOrCreate(
            ['account_id' => null, 'key' => $module['key']],
            [...$module, 'status' => 'placeholder']
        ));

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'global_role' => 'super_admin',
        ]);
    }
}
