<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('webhook_id')->unique();
            $table->string('topic');
            $table->string('shop_domain');
            $table->string('payload_hash', 64);
            $table->string('status')->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['shop_domain', 'topic', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_webhook_deliveries');
    }
};
