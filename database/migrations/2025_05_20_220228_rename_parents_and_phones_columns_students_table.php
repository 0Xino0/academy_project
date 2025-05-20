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
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('parent1_name', 'father_name');
            $table->renameColumn('parent1_phone', 'father_phone');
            $table->renameColumn('parent2_name', 'mother_name');
            $table->renameColumn('parent2_phone', 'mother_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('father_name', 'parent1_name');
            $table->renameColumn('father_phone', 'parent1_phone');
            $table->renameColumn('mother_name', 'parent2_name');
            $table->renameColumn('mother_phone', 'parent2_phone');
        });
    }
};
