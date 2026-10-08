<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('coach_exercise_media', 'coach_exercise_content');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('coach_exercise_content', 'coach_exercise_media');
    }
};
