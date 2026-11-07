<?php

namespace Tests\Feature\Scheduling;

use App\Services\ScheduleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSchema;
use Tests\TestCase;

class TimetableDatabaseTest extends TestCase
{
    use IsolatedSchema;

    public static function drivers(): array
    {
        return ['SQLite' => ['sqlite'], 'PostgreSQL (opt-in)' => ['pgsql']];
    }

    #[DataProvider('drivers')]
    public function test_student_locks_and_peer_queries_reject_conflicts_without_partial_writes(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            Schema::create('terms', function (Blueprint $table) { $table->id(); $table->softDeletes(); });
            Schema::create('teachers', function (Blueprint $table) { $table->id(); $table->softDeletes(); });
            Schema::create('students', function (Blueprint $table) { $table->id(); $table->softDeletes(); });
            Schema::create('classes', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('term_id'); $table->unsignedBigInteger('teacher_id');
                $table->date('start_date'); $table->date('end_date');
                $table->softDeletes();
            });
            Schema::create('registrations', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('student_id'); $table->unsignedBigInteger('class_id');
                $table->timestamp('cancelled_at')->nullable();
            });
            Schema::create('schedules', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('class_id'); $table->string('day_of_week');
                $table->time('start_time'); $table->time('end_time'); $table->timestamps();
                $table->softDeletes();
            });
            DB::table('terms')->insert([['id' => 1], ['id' => 2]]);
            DB::table('teachers')->insert([['id' => 1], ['id' => 2]]);
            DB::table('students')->insert(['id' => 1]);
            DB::table('classes')->insert([
                ['id' => 1, 'term_id' => 1, 'teacher_id' => 1, 'start_date' => '2027-01-04', 'end_date' => '2027-01-31'],
                ['id' => 2, 'term_id' => 2, 'teacher_id' => 2, 'start_date' => '2027-01-04', 'end_date' => '2027-01-31'],
            ]);
            DB::table('registrations')->insert([
                ['id' => 1, 'student_id' => 1, 'class_id' => 1], ['id' => 2, 'student_id' => 1, 'class_id' => 2],
            ]);
            DB::table('schedules')->insert([
                ['id' => 1, 'class_id' => 1, 'day_of_week' => 'monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00'],
                ['id' => 2, 'class_id' => 2, 'day_of_week' => 'monday', 'start_time' => '09:00:00', 'end_time' => '10:00:00'],
            ]);
            try {
                app(ScheduleService::class)->save('1', '1', ['day_of_week' => 'monday', 'start_time' => '09:30:00', 'end_time' => '10:30:00'], '1');
                $this->fail('Cross-term student conflicts must roll back.');
            } catch (ValidationException $error) { $this->assertArrayHasKey('schedule', $error->errors()); }
            $this->assertSame('08:00:00', DB::table('schedules')->where('id', 1)->value('start_time'));
            $this->assertSame(2, DB::table('schedules')->count());
            $saved = app(ScheduleService::class)->save('1', '1', ['day_of_week' => 'monday', 'start_time' => '10:00:00', 'end_time' => '11:00:00'], '1');
            $this->assertSame('10:00:00', $saved->start_time);
            $this->assertSame(1, $saved->class->id);
        });
    }
}
