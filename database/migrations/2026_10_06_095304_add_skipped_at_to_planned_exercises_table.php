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
        Schema::table('planned_exercises', function (Blueprint $table) {
            $table->timestamp('skipped_at')->nullable()->after('set_style_configuration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planned_exercises', function (Blueprint $table) {
            $table->dropColumn('skipped_at');
        });
    }
};
