<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('workout_plans')->where('phase', 'build')->update(['phase' => 'accumulation']);
        DB::table('exercise_results')->where('feedback', 'tooEasy')->update(['feedback' => 'easy']);
        DB::table('exercise_results')->where('feedback', 'tooHard')->update(['feedback' => 'hard']);
    }

    public function down(): void
    {
        // The normalized value is valid and does not need to be reverted.
    }
};
