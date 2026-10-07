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
        DB::table('workout_days')
            ->where('focus', 'back')
            ->update(['focus' => 'pull']);

        DB::table('workout_days')
            ->whereIn('focus', ['chest', 'shoulders', 'arms'])
            ->update(['focus' => 'upperBody']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This data normalization cannot be reversed without losing information.
    }
};
