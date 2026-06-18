<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('google_email')->nullable();
            $table->longText('access_token')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->string('token_type')->default('Bearer');
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('connected');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });

        Schema::create('search_console_properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_console_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('site_url');
            $table->string('permission_level')->nullable();
            $table->boolean('selected')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'site_url']);
            $table->index(['account_id', 'selected']);
        });

        Schema::create('tracked_keywords', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopify_store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword');
            $table->string('target_url', 1024)->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('intent')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'keyword']);
            $table->index(['account_id', 'status']);
            $table->index(['shopify_store_id', 'status']);
        });

        Schema::create('keyword_position_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracked_keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shopify_store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('search_console_property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('search_console');
            $table->date('date')->nullable();
            $table->string('query');
            $table->text('page')->nullable();
            $table->string('country', 8)->nullable();
            $table->string('device', 24)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('position', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'date']);
            $table->index(['account_id', 'query']);
            $table->index(['tracked_keyword_id', 'date']);
            $table->index(['search_console_property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_position_snapshots');
        Schema::dropIfExists('tracked_keywords');
        Schema::dropIfExists('search_console_properties');
        Schema::dropIfExists('search_console_connections');
    }
};
