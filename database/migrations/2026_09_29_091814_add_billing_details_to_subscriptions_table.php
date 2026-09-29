<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('billing_amount', 12, 3)->nullable()->after('auto_renews');
            $table->char('billing_currency', 3)->nullable()->after('billing_amount');
            $table->string('billing_interval')->nullable()->after('billing_currency');
            $table->unsignedInteger('billing_interval_count')->nullable()->after('billing_interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'billing_amount',
                'billing_currency',
                'billing_interval',
                'billing_interval_count',
            ]);
        });
    }
};
