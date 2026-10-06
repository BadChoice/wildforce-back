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
        Schema::create('coach_exercise_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coach_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('exercise');
            $table->string('image_path')->nullable();
            $table->string('youtube_video_id', 11)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['coach_user_id', 'exercise']);
            $table->index(['coach_user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_exercise_media');
    }
};
