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
            if (! Schema::hasColumn('plans', 'trial_days')) {
                $table->unsignedInteger('trial_days')->default(14)->after('monthly_price');
            }
        });

        DB::table('plans')
            ->whereNull('trial_days')
            ->update(['trial_days' => 14]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'trial_days')) {
                $table->dropColumn('trial_days');
            }
        });
    }
};
