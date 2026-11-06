<?php

namespace Tests\Feature\Registrations;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RegistrationMigrationCompatibilityTest extends TestCase
{
    public static function drivers(): array
    {
        return ['SQLite' => ['sqlite'], 'PostgreSQL (opt-in)' => ['pgsql']];
    }

    private function isolated(string $driver, callable $test): void
    {
        if ($driver === 'pgsql' && getenv('ACADEMY_TEST_POSTGRES') !== '1') {
            $this->markTestSkipped('PostgreSQL integration is opt-in; run without phpunit.xml database overrides.');
        }
        $original = DB::getDefaultConnection();
        $name = 'registration_migration_check';
        $config = $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]
            : config('database.connections.pgsql');
        $schema = 'academy_migration_test_'.bin2hex(random_bytes(8));
        if ($driver === 'pgsql') $config['search_path'] = $schema;
        config(["database.connections.$name" => $config]);
        DB::setDefaultConnection($name);
        Schema::clearResolvedInstance('db.schema');
        $connection = DB::connection();
        try {
            if ($driver === 'pgsql') {
                // All test DDL and rows disappear on rollback, including the schema.
                $connection->beginTransaction();
                $connection->statement('CREATE SCHEMA "'.$schema.'"');
                $connection->statement('SET LOCAL search_path TO "'.$schema.'"');
            }
            Schema::create('registrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('class_id');
                $table->timestamps();
            });
            Schema::create('debts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registration_id');
                $table->decimal('amount', 10, 2);
                $table->decimal('remaining', 10, 2);
            });
            $test();
        } finally {
            while ($connection->transactionLevel() > 0) $connection->rollBack();
            DB::purge($name);
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
        }
    }

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_11_06_000000_add_registration_cancellation_and_policy.php');
    }

    private function assertDuplicateActiveRejected(): void
    {
        try {
            // A savepoint keeps PostgreSQL usable after an expected constraint error.
            DB::transaction(fn () => DB::table('registrations')->insert(['student_id' => 1, 'class_id' => 1]));
            $this->fail('An active duplicate must be rejected by the database.');
        } catch (QueryException $error) {
            $this->assertContains($error->errorInfo[0], ['23505', '23000']);
        }
    }

    #[DataProvider('drivers')]
    public function test_upgrade_preserves_data_and_allows_history_but_only_one_active_enrollment(string $driver): void
    {
        $this->isolated($driver, function () {
            $id = DB::table('registrations')->insertGetId(['student_id' => 1, 'class_id' => 1]);
            DB::table('debts')->insert(['registration_id' => $id, 'amount' => '100.00', 'remaining' => '80.00']);
            $migration = $this->migration();
            $migration->up();
            $this->assertSame(1, DB::table('registrations')->count());
            $this->assertEquals(1, DB::table('registrations')->where('id', $id)->value('active_slot'));
            $this->assertEquals(100, DB::table('debts')->value('amount'));
            $this->assertEquals(80, DB::table('debts')->value('remaining'));
            $this->assertEquals(0, DB::table('debts')->value('waived_amount'));
            $this->assertEquals(1, DB::table('registration_settings')->value('cancellation_requires_settlement'));
            $this->assertDuplicateActiveRejected();
            DB::table('registrations')->where('id', $id)->update(['cancelled_at' => '2026-11-06 10:00:00']);
            $this->assertNull(DB::table('registrations')->where('id', $id)->value('active_slot'));
            DB::table('registrations')->insert(['student_id' => 1, 'class_id' => 1, 'cancelled_at' => '2026-11-06 11:00:00']);
            DB::table('registrations')->insert(['student_id' => 1, 'class_id' => 1]);
            $this->assertDuplicateActiveRejected();
            $this->assertSame(3, DB::table('registrations')->count());
            try {
                $migration->down();
                $this->fail('Rollback must not discard cancellation history.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('discard cancellation or waiver history', $error->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('registrations', 'cancelled_at'));
            $this->assertSame(3, DB::table('registrations')->count());
        });
    }

    #[DataProvider('drivers')]
    public function test_duplicate_legacy_records_stop_the_upgrade_before_schema_or_data_changes(string $driver): void
    {
        $this->isolated($driver, function () {
            DB::table('registrations')->insert([
                ['student_id' => 1, 'class_id' => 1], ['student_id' => 1, 'class_id' => 1],
            ]);
            try {
                $this->migration()->up();
                $this->fail('Historical duplicates need review.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Duplicate registrations', $error->getMessage());
            }
            $this->assertFalse(Schema::hasColumn('registrations', 'cancelled_at'));
            $this->assertFalse(Schema::hasColumn('debts', 'waived_amount'));
            $this->assertFalse(Schema::hasTable('registration_settings'));
            $this->assertSame(2, DB::table('registrations')->count());
        });
    }
}
