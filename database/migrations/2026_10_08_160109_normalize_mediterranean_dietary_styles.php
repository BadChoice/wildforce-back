<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nutrition_profiles')
            ->where('dietary_style', 'mediterranean')
            ->update(['dietary_style' => 'standard']);
    }

    public function down(): void
    {
        // The normalized values cannot be distinguished from originally standard values.
    }
};
