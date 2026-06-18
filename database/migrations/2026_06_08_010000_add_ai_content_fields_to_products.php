<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('generated_title')->nullable()->after('seo_description');
            $table->longText('generated_description')->nullable()->after('generated_title');
            $table->string('generated_seo_title')->nullable()->after('generated_description');
            $table->text('generated_seo_description')->nullable()->after('generated_seo_title');
            $table->string('generated_description_style')->nullable()->after('generated_seo_description');
            $table->string('content_generation_status')->default('pending')->after('generated_description_style');
            $table->text('content_generation_error')->nullable()->after('content_generation_status');
            $table->timestamp('content_generated_at')->nullable()->after('content_generation_error');
            $table->timestamp('shopify_content_pushed_at')->nullable()->after('content_generated_at');
            $table->text('shopify_content_push_error')->nullable()->after('shopify_content_pushed_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'generated_title',
                'generated_description',
                'generated_seo_title',
                'generated_seo_description',
                'generated_description_style',
                'content_generation_status',
                'content_generation_error',
                'content_generated_at',
                'shopify_content_pushed_at',
                'shopify_content_push_error',
            ]);
        });
    }
};
