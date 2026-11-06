<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('debts')->where('amount', '<', 0)->orWhere('remaining', '<', 0)
            ->orWhereColumn('remaining', '>', 'amount')->exists()) {
            throw new RuntimeException('Invalid historical debt balances must be reviewed before migration. No amounts were changed.');
        }
        if (DB::table('registrations')->select('student_id', 'class_id')->groupBy('student_id', 'class_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate registrations must be reviewed before adding the active-registration constraint. No records were removed.');
        }
        if (DB::table('debts')->select('registration_id')->groupBy('registration_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate debt records must be reviewed first. No records were removed.');
        }
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $activeSlot = $table->unsignedTinyInteger('active_slot')->nullable();
            $expression = 'case when cancelled_at is null then 1 else null end';
            // PostgreSQL 17 requires STORED; SQLite ALTER TABLE supports VIRTUAL.
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $activeSlot->storedAs($expression);
            } else {
                $activeSlot->virtualAs($expression);
            }
            $table->unique(['student_id', 'class_id', 'active_slot'], 'registrations_one_active_enrollment');
        });
        Schema::table('debts', function (Blueprint $table) {
            $table->decimal('waived_amount', 10, 2)->default(0);
            $table->unique('registration_id', 'debts_one_per_registration');
        });
        Schema::create('registration_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('cancellation_requires_settlement')->default(true);
            $table->timestamps();
        });
        DB::table('registration_settings')->insert([
            'id' => 1, 'cancellation_requires_settlement' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('registrations')->whereNotNull('cancelled_at')->exists()
            || DB::table('debts')->where('waived_amount', '>', 0)->exists()) {
            throw new RuntimeException('Rollback would discard cancellation or waiver history. Review and archive it first.');
        }
        Schema::dropIfExists('registration_settings');
        Schema::table('debts', function (Blueprint $table) {
            $table->dropUnique('debts_one_per_registration');
            $table->dropColumn('waived_amount');
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_one_active_enrollment');
            $table->dropColumn('active_slot');
            $table->dropColumn('cancelled_at');
        });
    }
};
