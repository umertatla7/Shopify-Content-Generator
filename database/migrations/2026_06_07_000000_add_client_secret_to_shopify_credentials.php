<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_credentials', 'client_secret')) {
                $table->text('client_secret')->nullable()->after('api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shopify_credentials', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_credentials', 'client_secret')) {
                $table->dropColumn('client_secret');
            }
        });
    }
};
