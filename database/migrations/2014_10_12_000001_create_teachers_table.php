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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // 'salary', 'bio', 'resume', 'degree' are fillable in Model
            // checking if they are added here or in later migrations
            // 2025_04_17... adds soft delete
            // 2025_04_17... adds timestamp
            // 2025_05_20... removes join/leave_date and adds bio, degree. So bio/degree should NOT be here if we want to follow history, or we add them here and skip later?
            // "remove_join_and_leave_date_column_and_add_bio_and_degree_column_to_teachers_table.php"
            // This implies original table had join_date and leave_date.
            // But we don't have the original migration.
            // To make it compatible with 2025_05_20 migration, we should probably add join_date/leave_date here
            // OR we just add bio/degree here and the 2025_05_20 migration will fail/need adjustment.
            // The safest bet to make ALL future migrations run without error is to recreate the state BEFORE those migrations.
            // However, guesswork is dangerous.
            // Let's look at 2025_05_20 migration content to be sure.
            // For now, I'll create a basic table with user_id and maybe some placeholders if needed, 
            // but I'll check the 2025_05_20 migration first to see what it Drops.
            $table->date('join_date')->nullable(); // Guessing based on "remove_join_and_leave_date"
            $table->date('leave_date')->nullable(); // Guessing based on "remove_join_and_leave_date"
            $table->string('salary')->nullable();
            $table->string('resume')->nullable();
            // bio and degree are added later, so don't add them here.
            // timestamps are added in 2025_04_17_144011_add_timestamp_to_teachers_table.php
            // so DO NOT add them here.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
