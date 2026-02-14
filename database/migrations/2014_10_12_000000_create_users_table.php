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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('national_id')->unique()->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->integer('role')->nullable(); // Added to support 2025_03_23 migration which drops this
            // password and remember_token are added in a later migration
            // but usually a base users table has them. 
            // The later migration 2025_02_24... adds them.
            // So we should NOT add them here to avoid duplication error later, 
            // OR we add them here and the later migration will need adjustment/skipping.
            // Given the later migration "add_password..." explicitly adds them, 
            // it implies they were missing before.
            // HOWEVER, the error say "add column password...".
            // So to be safe and compatible with the existing migration stream, 
            // I will create the table WITHOUT password/remember_token 
            // so the 2025 migration can successfully add them.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
