<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_credentials', function (Blueprint $table) {
            $table->text('admin_api_access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_credentials', function (Blueprint $table) {
            $table->text('admin_api_access_token')->nullable(false)->change();
        });
    }
};
