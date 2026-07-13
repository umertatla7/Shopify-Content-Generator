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
            if (! Schema::hasColumn('plans', 'monthly_topic_limit')) {
                $table->unsignedInteger('monthly_topic_limit')->nullable()->after('monthly_blog_limit');
            }

            if (! Schema::hasColumn('plans', 'max_blog_word_count')) {
                $table->unsignedInteger('max_blog_word_count')->nullable()->after('word_limit_estimate');
            }
        });

        DB::transaction(function (): void {
            $timestamp = now();

            if (! DB::table('plans')->where('key', 'starter')->exists() && DB::table('plans')->where('key', 'growth')->exists()) {
                DB::table('plans')->where('key', 'growth')->update([
                    'key' => 'starter',
                    'name' => 'Starter',
                    'updated_at' => $timestamp,
                ]);

                DB::table('accounts')->where('plan_key', 'growth')->update([
                    'plan_key' => 'starter',
                ]);

                DB::table('subscriptions')->where('provider_plan_handle', 'growth')->update([
                    'provider_plan_handle' => 'starter',
                ]);
            }

            if (! DB::table('plans')->where('key', 'growth')->exists() && DB::table('plans')->where('key', 'pro')->exists()) {
                DB::table('plans')->where('key', 'pro')->update([
                    'key' => 'growth',
                    'name' => 'Growth',
                    'updated_at' => $timestamp,
                ]);

                DB::table('accounts')->where('plan_key', 'pro')->update([
                    'plan_key' => 'growth',
                ]);

                DB::table('subscriptions')->where('provider_plan_handle', 'pro')->update([
                    'provider_plan_handle' => 'growth',
                ]);
            }

            $plans = [
                [
                    'key' => 'free',
                    'name' => 'Free',
                    'monthly_price' => 0,
                    'trial_days' => 14,
                    'monthly_blog_limit' => 1,
                    'monthly_topic_limit' => 4,
                    'monthly_ai_token_limit' => 150000,
                    'monthly_credit_allowance' => 250,
                    'word_limit_estimate' => 3000,
                    'max_blog_word_count' => 1000,
                    'store_limit' => 1,
                    'user_limit' => 1,
                    'product_description_limit' => 10,
                    'collection_description_limit' => 0,
                    'monthly_seo_report_limit' => 1,
                    'monthly_ai_visibility_report_limit' => 0,
                    'monthly_image_optimization_limit' => 0,
                    'monthly_image_alt_text_limit' => 0,
                    'tracked_keyword_limit' => 0,
                    'credit_expires_after_days' => 30,
                    'shopify_billing_plan_handle' => 'free',
                    'features' => json_encode([
                        'product_descriptions',
                        'monthly_blog_generation',
                        'basic_store_audit',
                    ]),
                    'is_active' => true,
                ],
                [
                    'key' => 'starter',
                    'name' => 'Starter',
                    'monthly_price' => 19,
                    'trial_days' => 14,
                    'monthly_blog_limit' => 1,
                    'monthly_topic_limit' => 8,
                    'monthly_ai_token_limit' => 300000,
                    'monthly_credit_allowance' => 600,
                    'word_limit_estimate' => 8000,
                    'max_blog_word_count' => 1200,
                    'store_limit' => 1,
                    'user_limit' => 1,
                    'product_description_limit' => 20,
                    'collection_description_limit' => 5,
                    'monthly_seo_report_limit' => 1,
                    'monthly_ai_visibility_report_limit' => 1,
                    'monthly_image_optimization_limit' => 0,
                    'monthly_image_alt_text_limit' => 0,
                    'tracked_keyword_limit' => 0,
                    'credit_expires_after_days' => 30,
                    'shopify_billing_plan_handle' => 'starter',
                    'features' => json_encode([
                        'product_descriptions',
                        'collection_descriptions',
                        'monthly_blog_generation',
                        'store_audit',
                        'ai_visibility',
                    ]),
                    'is_active' => true,
                ],
                [
                    'key' => 'growth',
                    'name' => 'Growth',
                    'monthly_price' => 39,
                    'trial_days' => 14,
                    'monthly_blog_limit' => 4,
                    'monthly_topic_limit' => 30,
                    'monthly_ai_token_limit' => 900000,
                    'monthly_credit_allowance' => 1600,
                    'word_limit_estimate' => 24000,
                    'max_blog_word_count' => 1500,
                    'store_limit' => 1,
                    'user_limit' => 3,
                    'product_description_limit' => 60,
                    'collection_description_limit' => 15,
                    'monthly_seo_report_limit' => 3,
                    'monthly_ai_visibility_report_limit' => 4,
                    'monthly_image_optimization_limit' => 0,
                    'monthly_image_alt_text_limit' => 0,
                    'tracked_keyword_limit' => 25,
                    'credit_expires_after_days' => 30,
                    'shopify_billing_plan_handle' => 'growth',
                    'features' => json_encode([
                        'product_descriptions',
                        'collection_descriptions',
                        'monthly_blog_generation',
                        'store_audit',
                        'seo_reports',
                        'ai_visibility',
                        'rank_tracking',
                    ]),
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

            DB::table('plans')->where('key', 'pro')->update([
                'name' => 'Legacy Pro',
                'is_active' => false,
                'updated_at' => $timestamp,
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $timestamp = now();

            if (DB::table('plans')->where('key', 'starter')->exists() && ! DB::table('plans')->where('key', 'growth')->where('name', 'Growth')->exists()) {
                DB::table('plans')->where('key', 'starter')->update([
                    'key' => 'growth',
                    'name' => 'Growth',
                    'updated_at' => $timestamp,
                ]);

                DB::table('accounts')->where('plan_key', 'starter')->update([
                    'plan_key' => 'growth',
                ]);

                DB::table('subscriptions')->where('provider_plan_handle', 'starter')->update([
                    'provider_plan_handle' => 'growth',
                ]);
            }

            if (DB::table('plans')->where('key', 'growth')->exists() && DB::table('plans')->where('key', 'pro')->doesntExist()) {
                DB::table('plans')->where('key', 'growth')->where('name', 'Growth')->update([
                    'key' => 'pro',
                    'name' => 'Pro',
                    'updated_at' => $timestamp,
                ]);

                DB::table('accounts')->where('plan_key', 'growth')->update([
                    'plan_key' => 'pro',
                ]);

                DB::table('subscriptions')->where('provider_plan_handle', 'growth')->update([
                    'provider_plan_handle' => 'pro',
                ]);
            }
        });

        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'monthly_topic_limit')) {
                $table->dropColumn('monthly_topic_limit');
            }

            if (Schema::hasColumn('plans', 'max_blog_word_count')) {
                $table->dropColumn('max_blog_word_count');
            }
        });
    }
};
