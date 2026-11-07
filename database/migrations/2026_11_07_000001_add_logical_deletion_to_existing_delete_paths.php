<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['users', 'courses', 'terms', 'classes', 'schedules', 'roles', 'permissions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (DB::table($name)->whereNotNull('deleted_at')->exists()) {
                throw new RuntimeException('Rollback would reactivate archived records. Review the retained history first.');
            }
        }
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
