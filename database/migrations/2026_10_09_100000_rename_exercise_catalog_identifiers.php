<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RENAMES = [
        'inclineBenchPress' => 'dumbbellInclineBenchPress',
        'benchPress' => 'barbellBenchPress',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::RENAMES as $legacyId => $canonicalId) {
                // A profile or coach override may exist for both IDs. Keep the
                // canonical record in that case, then rename the remaining legacy
                // records without violating the per-user unique constraints.
                foreach ([
                    ['table' => 'exercise_profiles', 'ownerColumn' => 'user_id'],
                    ['table' => 'coach_exercise_content', 'ownerColumn' => 'coach_user_id'],
                ] as $definition) {
                    $existingOwners = DB::table($definition['table'])
                        ->where('exercise', $canonicalId)
                        ->pluck($definition['ownerColumn']);

                    DB::table($definition['table'])
                        ->where('exercise', $legacyId)
                        ->whereIn($definition['ownerColumn'], $existingOwners)
                        ->delete();

                    DB::table($definition['table'])
                        ->where('exercise', $legacyId)
                        ->update(['exercise' => $canonicalId]);
                }

                DB::table('planned_exercises')
                    ->where('exercise', $legacyId)
                    ->update(['exercise' => $canonicalId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (array_reverse(self::RENAMES, true) as $legacyId => $canonicalId) {
                foreach (['exercise_profiles', 'coach_exercise_content', 'planned_exercises'] as $table) {
                    DB::table($table)
                        ->where('exercise', $canonicalId)
                        ->update(['exercise' => $legacyId]);
                }
            }
        });
    }
};
