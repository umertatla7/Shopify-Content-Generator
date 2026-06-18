<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_pages')) {
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
        }

        if (! Schema::hasTable('store_knowledge_bases')) {
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
        }

        Schema::table('blog_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_topics', 'estimated_article_size')) {
                $table->string('estimated_article_size')->nullable()->after('opportunity_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blog_topics', function (Blueprint $table) {
            if (Schema::hasColumn('blog_topics', 'estimated_article_size')) {
                $table->dropColumn('estimated_article_size');
            }
        });

        Schema::dropIfExists('store_knowledge_bases');
        Schema::dropIfExists('shopify_pages');
    }
};
