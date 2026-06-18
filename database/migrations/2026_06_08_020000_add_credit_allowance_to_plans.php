<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('monthly_credit_allowance')->default(1000)->after('monthly_ai_token_limit');
        });

        DB::table('plans')->where('key', 'starter')->update([
            'store_limit' => 1,
            'monthly_credit_allowance' => 1000,
        ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('monthly_credit_allowance');
        });
    }
};
