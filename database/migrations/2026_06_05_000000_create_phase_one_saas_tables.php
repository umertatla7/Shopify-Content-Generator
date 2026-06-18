<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('billing_email')->nullable();
            $table->string('region', 64)->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('plan_key')->default('starter');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_account_id')->nullable()->after('id')->constrained('accounts')->nullOnDelete();
            $table->string('global_role')->default('user')->after('password');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('scope')->default('account');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('account_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('monthly_blog_limit')->nullable();
            $table->unsignedInteger('monthly_ai_token_limit')->nullable();
            $table->unsignedInteger('store_limit')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('shopify_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('shop_domain');
            $table->string('shop_url');
            $table->string('country', 64)->nullable();
            $table->string('default_language', 16)->default('en');
            $table->string('brand_tone')->nullable();
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('validation_error')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'shop_domain']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('shopify_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->text('admin_api_access_token')->nullable();
            $table->text('api_key')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('shopify_store_id');
        });

        Schema::create('shopify_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->string('sync_type')->default('full');
            $table->string('status')->default('pending');
            $table->json('counts')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_product_id')->nullable();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('url')->nullable();
            $table->longText('description')->nullable();
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->string('status')->nullable();
            $table->json('tags')->nullable();
            $table->json('collections')->nullable();
            $table->string('image_url')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['shopify_store_id', 'shopify_product_id']);
            $table->index(['account_id', 'shopify_store_id', 'handle']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_collection_id')->nullable();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('url')->nullable();
            $table->longText('description')->nullable();
            $table->string('image_url')->nullable();
            $table->json('rules')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['shopify_store_id', 'shopify_collection_id']);
            $table->index(['account_id', 'shopify_store_id', 'handle']);
        });

        Schema::create('existing_shopify_blogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_blog_id')->nullable();
            $table->string('shopify_article_id')->nullable();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('url')->nullable();
            $table->longText('body')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('author')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['shopify_store_id', 'shopify_article_id']);
        });

        Schema::create('shopify_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_page_id')->nullable();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('url')->nullable();
            $table->longText('body')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['shopify_store_id', 'shopify_page_id']);
            $table->index(['account_id', 'shopify_store_id', 'handle']);
        });

        Schema::create('store_knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->longText('summary')->nullable();
            $table->longText('editable_notes')->nullable();
            $table->json('brand_profile')->nullable();
            $table->json('audience_profile')->nullable();
            $table->json('product_insights')->nullable();
            $table->json('collection_insights')->nullable();
            $table->json('content_insights')->nullable();
            $table->json('seo_opportunities')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique('shopify_store_id');
            $table->index(['account_id', 'status']);
        });

        Schema::create('blog_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('target_region', 64)->nullable();
            $table->string('target_language', 16)->default('en');
            $table->string('tone')->nullable();
            $table->string('status')->default('active');
            $table->json('strategy')->nullable();
            $table->timestamps();
        });

        Schema::create('store_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('niche')->nullable();
            $table->text('target_audience')->nullable();
            $table->text('brand_voice_summary')->nullable();
            $table->json('main_product_categories')->nullable();
            $table->json('seo_opportunities')->nullable();
            $table->json('content_gaps')->nullable();
            $table->json('suggested_keywords')->nullable();
            $table->json('suggested_blog_categories')->nullable();
            $table->json('region_specific_opportunities')->nullable();
            $table->longText('prompt')->nullable();
            $table->json('response')->nullable();
            $table->json('token_usage')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'shopify_store_id', 'status']);
        });

        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phrase');
            $table->string('source')->default('ai');
            $table->string('region', 64)->nullable();
            $table->string('language', 16)->nullable();
            $table->string('intent')->nullable();
            $table->unsignedInteger('volume')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'shopify_store_id', 'phrase']);
        });

        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('generatable');
            $table->string('provider')->default('stub');
            $table->string('model')->nullable();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->longText('prompt')->nullable();
            $table->longText('response')->nullable();
            $table->json('token_usage')->nullable();
            $table->decimal('cost', 10, 4)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'type', 'status']);
        });

        Schema::create('blog_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('primary_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->string('search_intent')->nullable();
            $table->json('suggested_outline')->nullable();
            $table->json('related_products')->nullable();
            $table->json('related_collections')->nullable();
            $table->unsignedTinyInteger('opportunity_score')->nullable();
            $table->string('estimated_article_size')->nullable();
            $table->string('target_region', 64)->nullable();
            $table->string('target_language', 16)->default('en');
            $table->string('tone')->nullable();
            $table->string('seo_focus')->nullable();
            $table->string('product_category')->nullable();
            $table->string('status')->default('generated');
            $table->longText('prompt')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'shopify_store_id', 'status']);
        });

        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blog_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('seo_title')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->json('faq')->nullable();
            $table->json('internal_links')->nullable();
            $table->json('product_links')->nullable();
            $table->text('featured_image_idea')->nullable();
            $table->string('primary_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->string('status')->default('draft');
            $table->string('generation_status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('shopify_blog_id')->nullable();
            $table->string('shopify_article_id')->nullable();
            $table->string('published_url')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'shopify_store_id', 'status']);
            $table->index(['account_id', 'scheduled_at']);
        });

        Schema::create('blog_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('title')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->json('faq')->nullable();
            $table->json('internal_links')->nullable();
            $table->json('product_links')->nullable();
            $table->string('change_summary')->nullable();
            $table->timestamps();

            $table->unique(['blog_id', 'version']);
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_for');
            $table->string('timezone')->default('UTC');
            $table->string('recurrence_rule')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status', 'scheduled_for']);
        });

        Schema::create('publishing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->default('publish');
            $table->string('status')->default('pending');
            $table->string('shopify_article_id')->nullable();
            $table->string('published_url')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });

        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shopify_store_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('billable');
            $table->string('type');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->default('event');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'type', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('action');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'action', 'created_at']);
        });

        Schema::create('feature_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('status')->default('placeholder');
            $table->text('description')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_modules');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('usage_logs');
        Schema::dropIfExists('publishing_logs');
        Schema::dropIfExists('blog_schedules');
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_revisions');
        Schema::dropIfExists('blogs');
        Schema::dropIfExists('blog_topics');
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('store_analyses');
        Schema::dropIfExists('blog_projects');
        Schema::dropIfExists('existing_shopify_blogs');
        Schema::dropIfExists('store_knowledge_bases');
        Schema::dropIfExists('shopify_pages');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('products');
        Schema::dropIfExists('shopify_sync_logs');
        Schema::dropIfExists('shopify_credentials');
        Schema::dropIfExists('shopify_stores');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('account_users');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_account_id');
            $table->dropColumn('global_role');
        });

        Schema::dropIfExists('accounts');
    }
};
