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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('height_cm')->nullable()->after('name');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('height_cm');
            $table->date('birth_date')->nullable()->after('weight_kg');
            $table->string('gender')->nullable()->after('birth_date');
            $table->string('language')->nullable()->after('gender');
            $table->string('metric_system')->default('metric')->after('language');
            $table->unsignedInteger('current_streak')->default(0)->after('metric_system');
            $table->unsignedInteger('longest_streak')->default(0)->after('current_streak');
            $table->timestamp('last_completed_workout_at')->nullable()->after('longest_streak');
            $table->unsignedInteger('xp')->default(25)->after('last_completed_workout_at');
            $table->unsignedInteger('xp_level')->default(1)->after('xp');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['height_cm', 'weight_kg', 'birth_date', 'gender', 'language', 'metric_system', 'current_streak', 'longest_streak', 'last_completed_workout_at', 'xp', 'xp_level']);
        });
    }
};
