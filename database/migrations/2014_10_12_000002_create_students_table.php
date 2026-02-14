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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // Using original names based on "rename" migration 2025_05_20
            $table->string('parent1_name')->nullable();
            $table->string('parent1_phone')->nullable();
            $table->string('parent2_name')->nullable();
            $table->string('parent2_phone')->nullable();
            // Assuming timestamps were there initially or added? 
            // 2025_04_20_185935_add_timestamp_to_students_table.php adds timestamps.
            // So NO timestamps here.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
