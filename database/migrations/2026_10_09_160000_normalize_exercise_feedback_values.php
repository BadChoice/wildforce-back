<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exercise_results')) {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE exercise_results
            SET feedback = CASE
                WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(feedback), '_', ''), '-', ''), ' ', '')) IN ('veryeasy', 'mufacil', 'muyfacil') THEN 'veryEasy'
                WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(feedback), '_', ''), '-', ''), ' ', '')) IN ('easy', 'facil') THEN 'easy'
                WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(feedback), '_', ''), '-', ''), ' ', '')) IN ('justright', 'normal', 'justo') THEN 'justRight'
                WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(feedback), '_', ''), '-', ''), ' ', '')) IN ('hard', 'dificil') THEN 'hard'
                WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(feedback), '_', ''), '-', ''), ' ', '')) IN ('veryhard', 'muydificil') THEN 'veryHard'
                ELSE feedback
            END
            WHERE feedback IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        // The migration is intentionally not reversible: restoring invalid
        // enum values would make sync fail again.
    }
};
