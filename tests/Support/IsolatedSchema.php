<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait IsolatedSchema
{
    private function withIsolatedSchema(string $driver, callable $test): void
    {
        if ($driver === 'pgsql' && getenv('ACADEMY_TEST_POSTGRES') !== '1') {
            $this->markTestSkipped('PostgreSQL integration is opt-in; use --no-configuration to preserve its connection settings.');
        }
        $original = DB::getDefaultConnection();
        $name = 'isolated_migration_test';
        $schema = 'academy_migration_test_'.bin2hex(random_bytes(8));
        $config = $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]
            : array_merge(config('database.connections.pgsql'), ['search_path' => $schema]);
        config(["database.connections.$name" => $config]);
        DB::setDefaultConnection($name);
        Schema::clearResolvedInstance('db.schema');
        $connection = DB::connection();
        try {
            if ($driver === 'pgsql') {
                $connection->beginTransaction();
                $connection->statement('CREATE SCHEMA "'.$schema.'"');
                $connection->statement('SET LOCAL search_path TO "'.$schema.'"');
            }
            $test();
        } finally {
            while ($connection->transactionLevel() > 0) $connection->rollBack();
            DB::purge($name);
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
        }
    }
}
