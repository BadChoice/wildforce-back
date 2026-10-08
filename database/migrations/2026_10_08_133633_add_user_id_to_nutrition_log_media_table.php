<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nutrition_log_media', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('nutrition_log_media')
            ->whereNull('user_id')
            ->update([
                'user_id' => DB::table('nutrition_log_entries')
                    ->select('user_id')
                    ->whereColumn('nutrition_log_entries.nutrition_log_media_id', 'nutrition_log_media.id')
                    ->limit(1),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nutrition_log_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
