<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('subscriptions')
            ->where('provider', 'stripe')
            ->whereIn('plan', ['member_monthly', 'member_yearly'])
            ->update(['plan' => 'friends']);

        DB::table('subscriptions')
            ->whereIn('plan', ['member_monthly', 'member_yearly'])
            ->update(['plan' => 'premium']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('subscriptions')
            ->whereIn('plan', ['friends', 'premium'])
            ->where('billing_interval', 'month')
            ->update(['plan' => 'member_monthly']);

        DB::table('subscriptions')
            ->whereIn('plan', ['friends', 'premium'])
            ->where('billing_interval', 'year')
            ->update(['plan' => 'member_yearly']);
    }
};
