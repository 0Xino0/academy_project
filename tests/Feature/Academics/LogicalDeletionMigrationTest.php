<?php

namespace Tests\Feature\Academics;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\IsolatedSchema;
use Tests\TestCase;

class LogicalDeletionMigrationTest extends TestCase
{
    use IsolatedSchema;
    private const TABLES = ['users', 'courses', 'terms', 'classes', 'schedules', 'roles', 'permissions'];
    public static function drivers(): array { return ['SQLite' => ['sqlite'], 'PostgreSQL (opt-in)' => ['pgsql']]; }

    #[DataProvider('drivers')]
    public function test_migration_preserves_existing_rows_and_can_roll_back_before_archiving(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            foreach (self::TABLES as $name) {
                Schema::create($name, function (Blueprint $table) { $table->id(); $table->string('name'); });
                DB::table($name)->insert(['id' => 1, 'name' => 'Retained']);
            }
            $migration = require database_path('migrations/2026_11_07_000001_add_logical_deletion_to_existing_delete_paths.php');
            $migration->up();
            foreach (self::TABLES as $name) {
                $this->assertTrue(Schema::hasColumn($name, 'deleted_at'));
                $this->assertSame('Retained', DB::table($name)->value('name'));
            }
            DB::table('classes')->where('id', 1)->update(['deleted_at' => '2026-11-07 10:00:00']);
            try { $migration->down(); $this->fail('Rollback must not reactivate archived rows.'); }
            catch (RuntimeException $error) { $this->assertStringContainsString('reactivate', $error->getMessage()); }
            DB::table('classes')->where('id', 1)->update(['deleted_at' => null]);
            $migration->down();
            foreach (self::TABLES as $name) {
                $this->assertFalse(Schema::hasColumn($name, 'deleted_at'));
                $this->assertSame(1, DB::table($name)->count());
            }
        });
    }
}
