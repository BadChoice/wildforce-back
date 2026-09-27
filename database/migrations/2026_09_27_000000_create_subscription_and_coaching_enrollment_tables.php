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
        Schema::create('coaching_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('coach_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['client_user_id', 'status']);
            $table->index(['coach_user_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('provider');
            $table->string('status')->default('pending');
            $table->boolean('auto_renews')->default(false);
            $table->string('provider_reference')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
            $table->unique(['provider', 'provider_reference']);
            $table->index(['plan', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('coaching_enrollments');
    }
};
