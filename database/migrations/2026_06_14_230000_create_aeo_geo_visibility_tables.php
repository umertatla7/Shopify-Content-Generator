<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aeo_geo_visibility_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('completed');
            $table->unsignedTinyInteger('overall_score')->default(0);
            $table->unsignedTinyInteger('aeo_score')->default(0);
            $table->unsignedTinyInteger('geo_score')->default(0);
            $table->unsignedTinyInteger('llm_readiness_score')->default(0);
            $table->unsignedTinyInteger('answer_coverage_score')->default(0);
            $table->unsignedTinyInteger('entity_confidence_score')->default(0);
            $table->unsignedTinyInteger('content_depth_score')->default(0);
            $table->unsignedTinyInteger('schema_readiness_score')->default(0);
            $table->unsignedTinyInteger('prompt_coverage_score')->default(0);
            $table->unsignedInteger('tracked_prompt_count')->default(0);
            $table->unsignedInteger('covered_prompt_count')->default(0);
            $table->unsignedInteger('partial_prompt_count')->default(0);
            $table->unsignedInteger('missing_prompt_count')->default(0);
            $table->text('summary')->nullable();
            $table->json('findings')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('content_gaps')->nullable();
            $table->json('top_questions')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'shopify_store_id', 'created_at'], 'aeo_geo_reports_store_created_idx');
            $table->index(['account_id', 'status'], 'aeo_geo_reports_status_idx');
        });

        Schema::create('aeo_geo_prompt_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aeo_geo_visibility_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('intent')->nullable();
            $table->string('target_entity_type')->nullable();
            $table->unsignedBigInteger('target_entity_id')->nullable();
            $table->string('target_entity_label')->nullable();
            $table->string('status')->default('missing');
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('evidence')->nullable();
            $table->text('recommended_source_url')->nullable();
            $table->text('recommendation')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status'], 'aeo_geo_prompt_status_idx');
            $table->index(['shopify_store_id', 'status'], 'aeo_geo_prompt_store_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aeo_geo_prompt_checks');
        Schema::dropIfExists('aeo_geo_visibility_reports');
    }
};
