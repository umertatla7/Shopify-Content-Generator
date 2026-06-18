<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->text('featured_image_prompt')->nullable()->after('featured_image_idea');
            $table->string('featured_image_alt')->nullable()->after('featured_image_prompt');
            $table->string('featured_image_url')->nullable()->after('featured_image_alt');
            $table->string('featured_image_status')->default('pending')->after('featured_image_url');
            $table->json('featured_image_payload')->nullable()->after('featured_image_status');
            $table->timestamp('featured_image_generated_at')->nullable()->after('featured_image_payload');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn([
                'featured_image_prompt',
                'featured_image_alt',
                'featured_image_url',
                'featured_image_status',
                'featured_image_payload',
                'featured_image_generated_at',
            ]);
        });
    }
};
