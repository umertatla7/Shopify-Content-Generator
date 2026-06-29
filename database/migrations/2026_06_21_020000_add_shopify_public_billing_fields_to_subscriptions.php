<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'shopify_store_id')) {
                $table->foreignId('shopify_store_id')->nullable()->after('account_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('subscriptions', 'provider')) {
                $table->string('provider', 32)->nullable()->after('plan_id');
            }

            if (! Schema::hasColumn('subscriptions', 'external_id')) {
                $table->string('external_id')->nullable()->after('provider')->index();
            }

            if (! Schema::hasColumn('subscriptions', 'provider_plan_handle')) {
                $table->string('provider_plan_handle', 128)->nullable()->after('external_id');
            }

            if (! Schema::hasColumn('subscriptions', 'provider_line_item_id')) {
                $table->string('provider_line_item_id')->nullable()->after('provider_plan_handle');
            }

            if (! Schema::hasColumn('subscriptions', 'amount')) {
                $table->decimal('amount', 10, 2)->nullable()->after('status');
            }

            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 8)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('subscriptions', 'is_test')) {
                $table->boolean('is_test')->default(false)->after('currency');
            }

            if (! Schema::hasColumn('subscriptions', 'confirmation_url')) {
                $table->text('confirmation_url')->nullable()->after('is_test');
            }

            if (! Schema::hasColumn('subscriptions', 'return_url')) {
                $table->text('return_url')->nullable()->after('confirmation_url');
            }

            if (! Schema::hasColumn('subscriptions', 'trial_days')) {
                $table->unsignedInteger('trial_days')->nullable()->after('return_url');
            }

            if (! Schema::hasColumn('subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('current_period_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            foreach ([
                'shopify_store_id',
                'provider',
                'external_id',
                'provider_plan_handle',
                'provider_line_item_id',
                'amount',
                'currency',
                'is_test',
                'confirmation_url',
                'return_url',
                'trial_days',
                'cancelled_at',
            ] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    if ($column === 'shopify_store_id') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
