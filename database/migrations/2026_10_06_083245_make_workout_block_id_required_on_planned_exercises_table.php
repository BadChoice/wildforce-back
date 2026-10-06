<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->moveOrphanExercisesIntoBlocks();

        Schema::table('planned_exercises', function (Blueprint $table) {
            $table->dropForeign(['workout_block_id']);
        });

        Schema::table('planned_exercises', function (Blueprint $table) {
            $table->uuid('workout_block_id')->nullable(false)->change();
            $table->foreign('workout_block_id')->references('id')->on('workout_blocks')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planned_exercises', function (Blueprint $table) {
            $table->dropForeign(['workout_block_id']);
        });

        Schema::table('planned_exercises', function (Blueprint $table) {
            $table->uuid('workout_block_id')->nullable()->change();
            $table->foreign('workout_block_id')->references('id')->on('workout_blocks')->nullOnDelete();
        });
    }

    /**
     * Exercises are always grouped into blocks now, so wrap any legacy exercise
     * without a block into a new block per workout day and section.
     */
    private function moveOrphanExercisesIntoBlocks(): void
    {
        DB::table('planned_exercises')
            ->whereNull('workout_block_id')
            ->select('workout_day_id', 'section')
            ->distinct()
            ->get()
            ->each(function (object $group): void {
                $now = now();
                $blockId = (string) Str::uuid();

                DB::table('workout_blocks')->insert([
                    'id' => $blockId,
                    'workout_day_id' => $group->workout_day_id,
                    'type' => match ($group->section) {
                        'warmup' => 'warmup',
                        'cooldown' => 'cooldown',
                        default => 'standard',
                    },
                    'order_index' => ((int) DB::table('workout_blocks')->where('workout_day_id', $group->workout_day_id)->max('order_index')) + 1,
                    'rounds' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('planned_exercises')
                    ->whereNull('workout_block_id')
                    ->where('workout_day_id', $group->workout_day_id)
                    ->when(
                        $group->section === null,
                        fn ($query) => $query->whereNull('section'),
                        fn ($query) => $query->where('section', $group->section),
                    )
                    ->update(['workout_block_id' => $blockId, 'updated_at' => $now]);
            });
    }
};
