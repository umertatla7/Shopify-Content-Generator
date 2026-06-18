<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'monthly_price')) {
                $table->decimal('monthly_price', 10, 2)->default(0)->after('name');
            }

            if (! Schema::hasColumn('plans', 'word_limit_estimate')) {
                $table->unsignedInteger('word_limit_estimate')->nullable()->after('monthly_credit_allowance');
            }

            if (! Schema::hasColumn('plans', 'user_limit')) {
                $table->unsignedInteger('user_limit')->nullable()->after('store_limit');
            }

            if (! Schema::hasColumn('plans', 'product_description_limit')) {
                $table->unsignedInteger('product_description_limit')->nullable()->after('monthly_blog_limit');
            }

            if (! Schema::hasColumn('plans', 'collection_description_limit')) {
                $table->unsignedInteger('collection_description_limit')->nullable()->after('product_description_limit');
            }

            if (! Schema::hasColumn('plans', 'credit_expires_after_days')) {
                $table->unsignedInteger('credit_expires_after_days')->nullable()->after('collection_description_limit');
            }
        });

        Schema::table('accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounts', 'status')) {
                $table->string('status')->default('active')->after('plan_key');
            }

            if (! Schema::hasColumn('accounts', 'credits_expire_at')) {
                $table->timestamp('credits_expire_at')->nullable()->after('credits_reset_at');
            }
        });

        Schema::table('shopify_stores', function (Blueprint $table): void {
            if (! Schema::hasColumn('shopify_stores', 'currency')) {
                $table->string('currency', 8)->nullable()->after('country');
            }

            if (! Schema::hasColumn('shopify_stores', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('shopify_stores', 'primary_locale')) {
                $table->string('primary_locale', 16)->nullable()->after('timezone');
            }
        });

        Schema::table('collections', function (Blueprint $table): void {
            if (! Schema::hasColumn('collections', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('image_url');
            }

            if (! Schema::hasColumn('collections', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }

            if (! Schema::hasColumn('collections', 'product_count')) {
                $table->unsignedInteger('product_count')->nullable()->after('seo_description');
            }

            if (! Schema::hasColumn('collections', 'generated_description')) {
                $table->longText('generated_description')->nullable()->after('product_count');
            }

            if (! Schema::hasColumn('collections', 'generated_intro')) {
                $table->text('generated_intro')->nullable()->after('generated_description');
            }

            if (! Schema::hasColumn('collections', 'generated_benefits')) {
                $table->json('generated_benefits')->nullable()->after('generated_intro');
            }

            if (! Schema::hasColumn('collections', 'generated_faq')) {
                $table->json('generated_faq')->nullable()->after('generated_benefits');
            }

            if (! Schema::hasColumn('collections', 'generated_meta_title')) {
                $table->string('generated_meta_title')->nullable()->after('generated_faq');
            }

            if (! Schema::hasColumn('collections', 'generated_meta_description')) {
                $table->text('generated_meta_description')->nullable()->after('generated_meta_title');
            }

            if (! Schema::hasColumn('collections', 'generated_handle')) {
                $table->string('generated_handle')->nullable()->after('generated_meta_description');
            }

            if (! Schema::hasColumn('collections', 'generated_aeo_content')) {
                $table->longText('generated_aeo_content')->nullable()->after('generated_handle');
            }

            if (! Schema::hasColumn('collections', 'generation_status')) {
                $table->string('generation_status')->default('pending')->after('generated_aeo_content');
            }

            if (! Schema::hasColumn('collections', 'generation_error')) {
                $table->text('generation_error')->nullable()->after('generation_status');
            }

            if (! Schema::hasColumn('collections', 'generated_at')) {
                $table->timestamp('generated_at')->nullable()->after('generation_error');
            }

            if (! Schema::hasColumn('collections', 'last_optimized_at')) {
                $table->timestamp('last_optimized_at')->nullable()->after('generated_at');
            }

            if (! Schema::hasColumn('collections', 'shopify_push_error')) {
                $table->text('shopify_push_error')->nullable()->after('last_optimized_at');
            }

            if (! Schema::hasColumn('collections', 'shopify_pushed_at')) {
                $table->timestamp('shopify_pushed_at')->nullable()->after('shopify_push_error');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_logs', 'shopify_store_id')) {
                $table->foreignId('shopify_store_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('activity_logs', 'entity_type')) {
                $table->string('entity_type')->nullable()->after('action');
            }

            if (! Schema::hasColumn('activity_logs', 'status')) {
                $table->string('status')->default('success')->after('entity_type');
            }

            if (! Schema::hasColumn('activity_logs', 'previous_values')) {
                $table->json('previous_values')->nullable()->after('description');
            }

            if (! Schema::hasColumn('activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('previous_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            foreach (['new_values', 'previous_values', 'status', 'entity_type'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('activity_logs', 'shopify_store_id')) {
                $table->dropConstrainedForeignId('shopify_store_id');
            }
        });

        Schema::table('collections', function (Blueprint $table): void {
            $columns = [
                'shopify_pushed_at',
                'shopify_push_error',
                'last_optimized_at',
                'generated_at',
                'generation_error',
                'generation_status',
                'generated_aeo_content',
                'generated_handle',
                'generated_meta_description',
                'generated_meta_title',
                'generated_faq',
                'generated_benefits',
                'generated_intro',
                'generated_description',
                'product_count',
                'seo_description',
                'seo_title',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('collections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('shopify_stores', function (Blueprint $table): void {
            foreach (['primary_locale', 'timezone', 'currency'] as $column) {
                if (Schema::hasColumn('shopify_stores', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('accounts', function (Blueprint $table): void {
            foreach (['credits_expire_at', 'status'] as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('plans', function (Blueprint $table): void {
            $columns = [
                'credit_expires_after_days',
                'collection_description_limit',
                'product_description_limit',
                'user_limit',
                'word_limit_estimate',
                'monthly_price',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
