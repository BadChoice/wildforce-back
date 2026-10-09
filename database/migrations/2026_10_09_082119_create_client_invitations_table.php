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
        Schema::create('client_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coach_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->text('message')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['coach_user_id', 'email']);
            $table->index(['coach_user_id', 'accepted_at', 'revoked_at', 'expires_at'], 'client_invitations_pending_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_invitations');
    }
};
