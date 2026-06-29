<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'monthly_seo_report_limit')) {
                $table->unsignedInteger('monthly_seo_report_limit')->nullable()->after('collection_description_limit');
            }

            if (! Schema::hasColumn('plans', 'monthly_ai_visibility_report_limit')) {
                $table->unsignedInteger('monthly_ai_visibility_report_limit')->nullable()->after('monthly_seo_report_limit');
            }

            if (! Schema::hasColumn('plans', 'monthly_image_optimization_limit')) {
                $table->unsignedInteger('monthly_image_optimization_limit')->nullable()->after('monthly_ai_visibility_report_limit');
            }

            if (! Schema::hasColumn('plans', 'monthly_image_alt_text_limit')) {
                $table->unsignedInteger('monthly_image_alt_text_limit')->nullable()->after('monthly_image_optimization_limit');
            }

            if (! Schema::hasColumn('plans', 'tracked_keyword_limit')) {
                $table->unsignedInteger('tracked_keyword_limit')->nullable()->after('monthly_image_alt_text_limit');
            }

            if (! Schema::hasColumn('plans', 'shopify_billing_plan_handle')) {
                $table->string('shopify_billing_plan_handle', 128)->nullable()->after('tracked_keyword_limit');
            }
        });

        DB::transaction(function (): void {
            DB::table('plans')->where('key', 'starter')->update([
                'key' => 'free',
                'name' => 'Free',
                'updated_at' => now(),
            ]);

            DB::table('accounts')->where('plan_key', 'starter')->update([
                'plan_key' => 'free',
            ]);

            $timestamp = now();
            $plans = [
                [
                    'key' => 'free',
                    'name' => 'Free',
                    'monthly_price' => 0,
                    'monthly_blog_limit' => 1,
                    'monthly_ai_token_limit' => 150000,
                    'monthly_credit_allowance' => 500,
                    'word_limit_estimate' => 5000,
                    'store_limit' => 1,
                    'user_limit' => 1,
                    'product_description_limit' => 25,
                    'collection_description_limit' => 0,
                    'monthly_seo_report_limit' => 1,
                    'monthly_ai_visibility_report_limit' => 1,
                    'monthly_image_optimization_limit' => 0,
                    'monthly_image_alt_text_limit' => 0,
                    'tracked_keyword_limit' => 10,
                    'credit_expires_after_days' => 30,
                    'shopify_billing_plan_handle' => 'free',
                    'features' => json_encode(['product_descriptions', 'monthly_blog_generation', 'basic_store_audit']),
                    'is_active' => true,
                ],
                [
                    'key' => 'growth',
                    'name' => 'Growth',
                    'monthly_price' => 0,
                    'monthly_blog_limit' => 10,
                    'monthly_ai_token_limit' => 500000,
                    'monthly_credit_allowance' => 1000,
                    'word_limit_estimate' => 15000,
                    'store_limit' => 1,
                    'user_limit' => 3,
                    'product_description_limit' => 100,
                    'collection_description_limit' => 25,
                    'monthly_seo_report_limit' => 10,
                    'monthly_ai_visibility_report_limit' => 3,
                    'monthly_image_optimization_limit' => 25,
                    'monthly_image_alt_text_limit' => 25,
                    'tracked_keyword_limit' => 50,
                    'credit_expires_after_days' => 30,
                    'shopify_billing_plan_handle' => 'growth',
                    'features' => json_encode(['product_descriptions', 'collection_descriptions', 'monthly_blog_generation', 'seo_reports', 'ai_visibility', 'image_optimization', 'image_alt_text']),
                    'is_active' => true,
                ],
                [
                    'key' => 'pro',
                    'name' => 'Pro',
                    'monthly_price' => 0,
                    'monthly_blog_limit' => 50,
                    'monthly_ai_token_limit' => 1500000,
                    'monthly_credit_allowance' => 3000,
                    'word_limit_estimate' => 50000,
                    'store_limit' => 1,
                    'user_limit' => 10,
                    'product_description_limit' => 500,
                    'collection_description_limit' => 100,
                    'monthly_seo_report_limit' => 50,
                    'monthly_ai_visibility_report_limit' => 15,
                    'monthly_image_optimization_limit' => 250,
                    'monthly_image_alt_text_limit' => 250,
                    'tracked_keyword_limit' => 200,
                    'credit_expires_after_days' => 30,
                    'shopify_billing_plan_handle' => 'pro',
                    'features' => json_encode(['all_features', 'product_descriptions', 'collection_descriptions', 'monthly_blog_generation', 'seo_reports', 'ai_visibility', 'image_optimization', 'image_alt_text', 'rank_tracking']),
                    'is_active' => true,
                ],
            ];

            foreach ($plans as $plan) {
                $query = DB::table('plans')->where('key', $plan['key']);

                if ($query->exists()) {
                    $query->update([...$plan, 'updated_at' => $timestamp]);
                } else {
                    DB::table('plans')->insert([...$plan, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('accounts')->where('plan_key', 'free')->update([
                'plan_key' => 'starter',
            ]);

            DB::table('plans')->where('key', 'free')->update([
                'key' => 'starter',
                'name' => 'Starter',
                'updated_at' => now(),
            ]);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $columns = [
                'monthly_seo_report_limit',
                'monthly_ai_visibility_report_limit',
                'monthly_image_optimization_limit',
                'monthly_image_alt_text_limit',
                'tracked_keyword_limit',
                'shopify_billing_plan_handle',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
