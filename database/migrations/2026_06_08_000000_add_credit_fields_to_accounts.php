<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->unsignedInteger('credit_balance')->default(1000)->after('plan_key');
            $table->unsignedInteger('monthly_credit_allowance')->default(1000)->after('credit_balance');
            $table->timestamp('credits_reset_at')->nullable()->after('monthly_credit_allowance');
        });

        DB::table('accounts')->whereNull('credit_balance')->update([
            'credit_balance' => 1000,
            'monthly_credit_allowance' => 1000,
        ]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'credit_balance',
                'monthly_credit_allowance',
                'credits_reset_at',
            ]);
        });
    }
};
