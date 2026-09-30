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
        Schema::table('workout_days', function (Blueprint $table) {
            $table->string('kind')->default('workout')->after('workout_plan_id');
            $table->uuid('source_workout_day_id')->nullable()->after('kind');
            $table->foreign('source_workout_day_id')->references('id')->on('workout_days')->nullOnDelete();
            $table->index(['user_id', 'kind', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_days', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'kind', 'updated_at']);
            $table->dropForeign(['source_workout_day_id']);
            $table->dropColumn(['kind', 'source_workout_day_id']);
        });
    }
};
