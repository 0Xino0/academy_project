<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('grades')->select('student_id', 'class_id')->groupBy('student_id', 'class_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate student/class grades need review. No grades were merged or removed.');
        }
        // Preserve legacy values; do not silently cast invalid text to a zero grade.
        foreach (DB::table('grades')->orderBy('id')->cursor() as $grade) {
            if (! is_numeric($grade->grade) || ! is_finite((float) $grade->grade)
                || (float) $grade->grade < 0 || (float) $grade->grade > 20) {
                throw new RuntimeException('An invalid historical grade needs review. No grade values were changed.');
            }
        }
        Schema::table('grades', fn (Blueprint $table) => $table->unique(['student_id', 'class_id'], 'grades_student_class_unique'));
    }

    public function down(): void
    {
        Schema::table('grades', fn (Blueprint $table) => $table->dropUnique('grades_student_class_unique'));
    }
};
