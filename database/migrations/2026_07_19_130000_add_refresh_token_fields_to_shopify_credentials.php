<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_credentials', function (Blueprint $table): void {
            $table->text('refresh_token')->nullable()->after('admin_api_access_token');
            $table->timestamp('refresh_token_expires_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_credentials', function (Blueprint $table): void {
            $table->dropColumn(['refresh_token', 'refresh_token_expires_at']);
        });
    }
};
