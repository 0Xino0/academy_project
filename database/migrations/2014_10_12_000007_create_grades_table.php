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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->string('grade');
            $table->date('entered_at')->nullable(); // "2025_05_18_132524_remove_entered_at_and_edited_at_filed_from_grades_table.php"
            // "2025_05_07_152404_add_edited_at_field_to_grades_table.php"
            // So edited_at was NOT there initially. (Wait, the migration name says ADDED)
            // So I should NOT adding it here.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
