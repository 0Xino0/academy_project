<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSchema;
use Tests\TestCase;

class LegacyMigrationRollbackTest extends TestCase
{
    use IsolatedSchema;

    public static function drivers(): array
    {
        return ['SQLite' => ['sqlite'], 'PostgreSQL (opt-in)' => ['pgsql']];
    }

    #[DataProvider('drivers')]
    public function test_password_rollback_restores_previous_columns_and_keeps_user_rows(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            $base = require database_path('migrations/2014_10_12_000000_create_users_table.php');
            $migration = require database_path('migrations/2025_02_24_234904_add_password_and_remember_token_to_users_table.php');
            $base->up();
            $columns = Schema::getColumnListing('users');
            $migration->up();
            $this->assertTrue(Schema::hasColumns('users', ['password', 'remember_token']));
            DB::table('users')->insert(['email' => 'rollback@example.test', 'password' => 'fixture', 'remember_token' => 'fixture']);

            $migration->down();

            $this->assertSame($columns, Schema::getColumnListing('users'));
            $this->assertSame('rollback@example.test', DB::table('users')->value('email'));
        });
    }

    #[DataProvider('drivers')]
    public function test_payment_rollback_restores_nullable_date_and_keeps_payment_rows(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            Schema::create('debts', fn (Blueprint $table) => $table->id());
            $base = require database_path('migrations/2014_10_12_000009_create_payments_table.php');
            $migration = require database_path('migrations/2025_05_19_230805_add_timestamps_to_payments_table.php');
            $base->up();
            $columns = Schema::getColumnListing('payments');
            DB::table('debts')->insert(['id' => 1]);
            DB::table('payments')->insert([
                'debt_id' => 1, 'amount' => '20.50',
                'paid_at' => '2026-11-09', 'payment_date' => '2026-11-08',
            ]);
            $migration->up();
            $this->assertFalse(Schema::hasColumn('payments', 'payment_date'));
            $this->assertTrue(Schema::hasColumns('payments', ['created_at', 'updated_at']));

            $migration->down();

            $this->assertSame($columns, Schema::getColumnListing('payments'));
            $this->assertDatabaseHas('payments', [
                'id' => 1, 'debt_id' => 1, 'amount' => '20.50', 'paid_at' => '2026-11-09', 'payment_date' => null,
            ]);
            DB::table('payments')->insert(['debt_id' => 1, 'amount' => '10.25', 'paid_at' => '2026-11-10']);
            $this->assertDatabaseHas('payments', ['amount' => '10.25', 'payment_date' => null]);
        });
    }
}
