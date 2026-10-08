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
        Schema::table('exercise_profiles', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('typical_pace_seconds_per_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercise_profiles', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
