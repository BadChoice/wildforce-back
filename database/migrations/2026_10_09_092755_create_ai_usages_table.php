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
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invocation_id')->unique();
            $table->string('agent');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->unsignedInteger('cache_read_input_tokens')->nullable();
            $table->unsignedInteger('cache_write_input_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedBigInteger('cost_micros')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
