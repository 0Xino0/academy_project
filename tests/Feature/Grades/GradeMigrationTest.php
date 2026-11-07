<?php

namespace Tests\Feature\Grades;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\IsolatedSchema;
use Tests\TestCase;

class GradeMigrationTest extends TestCase
{
    use IsolatedSchema;

    public static function drivers(): array
    {
        return ['SQLite' => ['sqlite'], 'PostgreSQL (opt-in)' => ['pgsql']];
    }

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('class_id');
            $table->string('grade');
            $table->timestamps();
        });
        return require database_path('migrations/2026_11_07_000000_add_unique_student_class_to_grades.php');
    }

    #[DataProvider('drivers')]
    public function test_upgrade_preserves_values_adds_unique_and_rollback_preserves_all_rows(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            $migration = $this->migration();
            DB::table('grades')->insert(['student_id' => 1, 'class_id' => 2, 'grade' => '18.125']);
            $before = DB::table('grades')->first();
            $migration->up();
            $this->assertEquals($before, DB::table('grades')->first());
            try {
                DB::transaction(fn () => DB::table('grades')->insert(['student_id' => 1, 'class_id' => 2, 'grade' => '20']));
                $this->fail('Duplicate grades must be rejected.');
            } catch (QueryException $error) {
                $this->assertContains($error->errorInfo[0], ['23505', '23000']);
            }
            DB::table('grades')->insert(['student_id' => 1, 'class_id' => 3, 'grade' => '0']);
            $migration->down();
            $this->assertSame(2, DB::table('grades')->count());
            $this->assertEquals($before, DB::table('grades')->where('id', $before->id)->first());
            DB::table('grades')->insert(['student_id' => 1, 'class_id' => 2, 'grade' => '20']);
            $this->assertSame(3, DB::table('grades')->count());
        });
    }

    #[DataProvider('drivers')]
    public function test_duplicates_are_reported_without_merging_or_changing_existing_grades(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            $migration = $this->migration();
            DB::table('grades')->insert([
                ['student_id' => 1, 'class_id' => 2, 'grade' => '18'], ['student_id' => 1, 'class_id' => 2, 'grade' => '19'],
            ]);
            $before = DB::table('grades')->orderBy('id')->get();
            try { $migration->up(); $this->fail('Duplicates require review.'); }
            catch (RuntimeException $error) { $this->assertStringContainsString('Duplicate student/class grades', $error->getMessage()); }
            $this->assertEquals($before, DB::table('grades')->orderBy('id')->get());
        });
    }

    #[DataProvider('drivers')]
    public function test_invalid_legacy_values_stop_before_ddl_instead_of_becoming_zero(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            $migration = $this->migration();
            foreach (['not-a-grade', '-1', '20.01', 'NaN'] as $value) {
                DB::table('grades')->delete();
                DB::table('grades')->insert(['student_id' => 1, 'class_id' => 2, 'grade' => $value]);
                try { $migration->up(); $this->fail('Invalid grades require review.'); }
                catch (RuntimeException $error) { $this->assertStringContainsString('invalid historical grade', $error->getMessage()); }
                $this->assertSame($value, DB::table('grades')->value('grade'));
            }
        });
    }
}
