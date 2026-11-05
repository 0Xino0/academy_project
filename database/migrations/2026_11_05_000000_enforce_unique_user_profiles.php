<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check both tables before making any schema change. Never delete duplicates.
        foreach (['students', 'teachers'] as $table) {
            if (DB::table($table)->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Cannot add unique profiles: resolve duplicate user_id values in '.$table.' first. No duplicate records were deleted.');
            }
        }

        foreach (['students', 'teachers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unique('user_id', $table.'_user_id_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['students', 'teachers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($table.'_user_id_unique');
            });
        }
    }
};
