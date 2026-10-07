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
        Schema::table('nutrition_meals', function (Blueprint $table) {
            $table->timestamp('discarded_at')->nullable()->after('example_foods');
        });

        Schema::table('nutrition_log_entries', function (Blueprint $table) {
            $table->uuid('nutrition_meal_id')->nullable()->after('nutrition_log_media_id');
            $table->foreign('nutrition_meal_id')->references('id')->on('nutrition_meals')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nutrition_log_entries', function (Blueprint $table) {
            $table->dropForeign(['nutrition_meal_id']);
            $table->dropColumn('nutrition_meal_id');
        });

        Schema::table('nutrition_meals', function (Blueprint $table) {
            $table->dropColumn('discarded_at');
        });
    }
};
