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
        Schema::create('nutrition_log_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source')->default('localPhoto');
            $table->string('local_relative_path')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nutrition_log_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('nutrition_log_media_id')->nullable();
            $table->string('title');
            $table->timestamp('logged_at');
            $table->string('meal_type')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('nutrition_log_media_id')->references('id')->on('nutrition_log_media')->nullOnDelete();
            $table->index(['user_id', 'logged_at']);
        });

        Schema::create('nutrition_log_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('nutrition_log_entry_id');
            $table->string('name');
            $table->string('brand')->nullable();
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit')->nullable();
            $table->decimal('amount_grams', 10, 3)->nullable();
            $table->decimal('calories', 10, 2)->default(0);
            $table->decimal('protein_grams', 10, 2)->default(0);
            $table->decimal('carbs_grams', 10, 2)->default(0);
            $table->decimal('fat_grams', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('nutrition_log_entry_id')->references('id')->on('nutrition_log_entries')->cascadeOnDelete();
            $table->index(['nutrition_log_entry_id', 'order_index']);
        });

        Schema::create('nutrition_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('source_workout_plan_id')->nullable();
            $table->timestamp('starts_on');
            $table->string('goal')->default('generalFitness');
            $table->string('body_composition_phase')->nullable();
            $table->decimal('daily_calorie_average', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('source_workout_plan_id')->references('id')->on('workout_plans')->nullOnDelete();
            $table->index(['user_id', 'starts_on']);
        });

        Schema::create('nutrition_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('nutrition_plan_id');
            $table->date('date');
            $table->string('weekday');
            $table->string('day_type')->default('rest');
            $table->decimal('target_calories', 10, 2)->default(0);
            $table->decimal('target_protein_grams', 10, 2)->default(0);
            $table->decimal('target_carbs_grams', 10, 2)->default(0);
            $table->decimal('target_fat_grams', 10, 2)->default(0);
            $table->string('planned_workout_title')->nullable();
            $table->string('planned_workout_focus')->nullable();
            $table->string('energy_demand')->default('low');
            $table->text('notes')->nullable();
            $table->text('pre_workout_guidance')->nullable();
            $table->text('post_workout_guidance')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('nutrition_plan_id')->references('id')->on('nutrition_plans')->cascadeOnDelete();
            $table->index(['nutrition_plan_id', 'date']);
        });

        Schema::create('nutrition_meals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('nutrition_day_id');
            $table->string('title');
            $table->unsignedSmallInteger('order_index');
            $table->string('meal_type');
            $table->decimal('target_calories', 10, 2)->default(0);
            $table->decimal('target_protein_grams', 10, 2)->default(0);
            $table->decimal('target_carbs_grams', 10, 2)->default(0);
            $table->decimal('target_fat_grams', 10, 2)->default(0);
            $table->text('guidance')->nullable();
            $table->json('example_foods')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('nutrition_day_id')->references('id')->on('nutrition_days')->cascadeOnDelete();
            $table->index(['nutrition_day_id', 'order_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nutrition_meals');
        Schema::dropIfExists('nutrition_days');
        Schema::dropIfExists('nutrition_plans');
        Schema::dropIfExists('nutrition_log_items');
        Schema::dropIfExists('nutrition_log_entries');
        Schema::dropIfExists('nutrition_log_media');
    }
};
