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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            // term_id is added later? "2025_05_14_184343_add_term_id_fk_to_classes_table.php"
            // So NO term_id here.
            $table->date('start_date');
            $table->date('end_date');
            $table->date('startRegistration_date')->nullable(); // "2025_05_20_224152_add_start_and_end_registration_date_to_classes_table.php"
            // Wait, if it's added later, maybe I shouldn't add it here?
            // "add_start_and_end_registration_date..." 
            // So NO start/end registration here either.
            // name is added later? "2025_04_27_062810_add_name_field_to_classes_table.php"
            // So NO name here.
            
            $table->integer('capacity');
            $table->decimal('tuition_fee', 10, 2);
            
            // "2025_04_27_063750_remove_schedule_from_classes_table.php"
            // This implies there WAS a schedule column.
            $table->string('schedule')->nullable();
            
            // "SQL: alter table "classes" drop column "term""
            // This implies a term column existed.
            $table->string('term')->nullable();
            
            // Checking timestamps: "2025_04_27_185156_add_timestamp_to_classes_tabel.php"
            // So NO timestamps here.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
