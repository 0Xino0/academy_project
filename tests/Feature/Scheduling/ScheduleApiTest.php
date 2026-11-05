<?php

namespace Tests\Feature\Scheduling;

use App\Models\Schedule;
use App\Models\Term;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Academics\AcademicTestCase;

class ScheduleApiTest extends AcademicTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->account('manager'), 'api');
    }

    private function path($class, $schedule = null): string
    {
        return '/api/v1/terms/'.$class->term_id.'/classes/'.$class->id.'/schedules'
            .($schedule ? '/'.$schedule->id : '');
    }

    private function hours(string $start = '09:00', string $end = '10:00', string $day = 'monday'): array
    {
        return ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end];
    }

    public function test_create_update_and_read_use_canonical_times_and_return_the_class(): void
    {
        $class = $this->classroom();
        $id = $this->postJson($this->path($class), $this->hours('14:30', '15:45', 'Monday'))
            ->assertOk()->assertJsonPath('status', true)->assertJsonPath('schedule.start_time', '14:30:00')
            ->assertJsonPath('schedule.day_of_week', 'monday')->assertJsonPath('schedule.class.id', $class->id)->json('schedule.id');
        $schedule = Schedule::findOrFail($id);
        $this->putJson($this->path($class, $schedule), $this->hours('00:00', '00:45'))
            ->assertOk()->assertJsonPath('schedule.start_time', '00:00:00')->assertJsonPath('schedule.class.id', $class->id);
        $this->getJson('/api/v1/terms/'.$class->term_id.'/schedules')->assertOk()
            ->assertJsonPath('schedules.0.end_time', '00:45:00');
        $this->assertDatabaseHas('schedules', ['id' => $id, 'start_time' => '00:00:00', 'end_time' => '00:45:00']);
    }

    public function test_legacy_am_pm_input_is_normalized_before_storage(): void
    {
        $class = $this->classroom();
        $this->postJson($this->path($class), $this->hours('12:00 AM', '1:15 AM'))
            ->assertOk()->assertJsonPath('schedule.start_time', '00:00:00')->assertJsonPath('schedule.end_time', '01:15:00');
        $this->postJson($this->path($class), $this->hours('12:00 PM', '1:30 PM'))
            ->assertOk()->assertJsonPath('schedule.start_time', '12:00:00')->assertJsonPath('schedule.end_time', '13:30:00');
    }

    public static function invalidHours(): iterable
    {
        yield 'invalid hour' => ['24:30', '25:00', 'monday'];
        yield 'invalid minute' => ['09:65', '10:00', 'monday'];
        yield 'same time' => ['09:00', '09:00', 'monday'];
        yield 'reverse' => ['10:00', '09:00', 'monday'];
        yield 'overnight' => ['23:00', '01:00', 'monday'];
        yield 'invalid weekday' => ['09:00', '10:00', 'noday'];
    }

    /** @dataProvider invalidHours */
    public function test_invalid_ranges_are_rejected_without_writing(string $start, string $end, string $day): void
    {
        $class = $this->classroom();
        $this->postJson($this->path($class), $this->hours($start, $end, $day))->assertUnprocessable()
            ->assertJsonValidationErrors($day === 'noday' ? 'day_of_week' : 'start_time');
        $this->assertDatabaseCount('schedules', 0);
    }

    public static function overlaps(): iterable
    {
        yield 'same' => ['09:00', '10:00'];
        yield 'starts inside' => ['09:30', '10:30'];
        yield 'ends inside' => ['08:30', '09:30'];
        yield 'encloses' => ['08:00', '11:00'];
        yield 'inside' => ['09:15', '09:45'];
    }

    /** @dataProvider overlaps */
    public function test_overlapping_ranges_of_the_same_class_are_rejected(string $start, string $end): void
    {
        $class = $this->classroom();
        Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $this->postJson($this->path($class), $this->hours($start, $end))->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_back_to_back_ranges_and_different_weekdays_are_allowed(): void
    {
        $class = $this->classroom();
        Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $this->postJson($this->path($class), $this->hours('10:00', '11:00'))->assertOk();
        $this->postJson($this->path($class), $this->hours('08:00', '09:00'))->assertOk();
        $this->postJson($this->path($class), $this->hours('09:00', '10:00', 'tuesday'))->assertOk();
        $this->assertDatabaseCount('schedules', 4);
    }

    public function test_different_teachers_can_teach_simultaneously_but_the_same_teacher_cannot(): void
    {
        $class = $this->classroom();
        Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $otherTeacher = $this->classroom();
        $sameTeacher = $this->classroom(['teacher_id' => $class->teacher_id]);
        $this->postJson($this->path($otherTeacher), $this->hours())->assertOk();
        $this->postJson($this->path($sameTeacher), $this->hours())->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_teacher_conflicts_cross_terms_but_not_disjoint_class_dates(): void
    {
        $class = $this->classroom();
        Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $term = Term::create(['year' => 1406, 'season' => 'spring', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString()]);
        $other = $this->classroom(['teacher_id' => $class->teacher_id, 'term_id' => $term->id]);
        $this->postJson($this->path($other), $this->hours())->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $other->update(['start_date' => now()->addMonths(3)->toDateString(), 'end_date' => now()->addMonths(4)->toDateString()]);
        $this->postJson($this->path($other), $this->hours())->assertOk();
    }

    public function test_update_ignores_itself_but_rejects_a_conflicting_change(): void
    {
        $class = $this->classroom();
        $first = Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $second = Schedule::create(['class_id' => $class->id] + $this->hours('11:00:00', '12:00:00'));
        $this->putJson($this->path($class, $first), $this->hours())->assertOk();
        $this->putJson($this->path($class, $second), $this->hours('09:30', '11:30'))
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertSame('11:00:00', $second->fresh()->start_time);
    }

    public static function sharedWeekdays(): iterable
    {
        yield 'date ranges meet on Friday without a shared Monday' => ['2026-11-06', false];
        yield 'a Monday exists in the common date range' => ['2026-11-09', true];
    }

    /** @dataProvider sharedWeekdays */
    public function test_a_weekly_conflict_requires_a_real_shared_occurrence(string $end, bool $conflict): void
    {
        $class = $this->classroom(['start_date' => '2026-11-01', 'end_date' => $end]);
        Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $other = $this->classroom(['teacher_id' => $class->teacher_id, 'start_date' => '2026-11-06', 'end_date' => '2026-11-12']);
        $response = $this->postJson($this->path($other), $this->hours());
        if ($conflict) {
            $response->assertUnprocessable()->assertJsonValidationErrors('schedule');
        } else {
            $response->assertOk();
        }
    }

    public function test_existing_am_pm_rows_are_read_and_compared_without_a_destructive_migration(): void
    {
        $class = $this->classroom();
        DB::table('schedules')->insert(['class_id' => $class->id] + $this->hours('2:30 PM', '3:30 PM', 'Monday'));
        $this->getJson('/api/v1/terms/'.$class->term_id.'/schedules')->assertOk()
            ->assertJsonPath('schedules.0.start_time', '14:30:00')->assertJsonPath('schedules.0.day_of_week', 'monday');
        $this->postJson($this->path($class), $this->hours('14:45', '15:45'))
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
    }

    public function test_delete_and_update_are_scoped_to_the_row_class_and_term(): void
    {
        $class = $this->classroom();
        $other = $this->classroom();
        $schedule = Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $this->deleteJson($this->path($other, $schedule))->assertNotFound();
        $this->putJson($this->path($other, $schedule), $this->hours())->assertNotFound();
        $this->assertDatabaseCount('schedules', 1);
        $this->deleteJson($this->path($class, $schedule))->assertOk()->assertJsonPath('schedule', null);
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_empty_lists_are_successful_for_manager_teacher_and_student(): void
    {
        $class = $this->classroom();
        $this->getJson('/api/v1/terms/'.$class->term_id.'/schedules')->assertOk()->assertJsonPath('schedules', []);
        $this->actingAs($class->teacher->user, 'api');
        $this->getJson('/api/v1/me/teaching-terms/'.$class->term_id.'/schedules')->assertOk()->assertJsonPath('schedules', []);
        $this->actingAs($this->account('student'), 'api');
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/schedules')->assertOk()->assertJsonPath('schedules', []);
    }

    public function test_personal_lists_keep_teacher_and_student_ownership_filters(): void
    {
        $class = $this->classroom();
        $other = $this->classroom();
        $student = $this->account('student');
        Registration::create(['class_id' => $class->id, 'student_id' => $student->student->id]);
        foreach ([$class, $other] as $item) {
            Schedule::create(['class_id' => $item->id] + $this->hours('09:00:00', '10:00:00'));
        }
        $this->actingAs($class->teacher->user, 'api');
        $this->getJson('/api/v1/me/teaching-terms/'.$class->term_id.'/schedules')->assertOk()
            ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.class_id', $class->id);
        $this->actingAs($student, 'api');
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/schedules')->assertOk()
            ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.class_id', $class->id);
    }

    public function test_a_wrong_term_or_missing_term_returns_404_without_modifying_a_schedule(): void
    {
        $class = $this->classroom();
        $schedule = Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $path = '/api/v1/terms/999999/classes/'.$class->id.'/schedules';
        $this->postJson($path, $this->hours())->assertNotFound();
        $this->putJson($path.'/'.$schedule->id, $this->hours())->assertNotFound();
        $this->deleteJson($path.'/'.$schedule->id)->assertNotFound();
        $this->getJson('/api/v1/terms/999999/schedules')->assertNotFound();
        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_an_invalid_legacy_row_does_not_silently_allow_a_conflict(): void
    {
        $class = $this->classroom();
        DB::table('schedules')->insert(['class_id' => $class->id] + $this->hours('invalid', '10:00:00'));
        $this->postJson($this->path($class), $this->hours())->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_a_vetoed_delete_is_not_reported_as_success_and_preserves_the_row(): void
    {
        $class = $this->classroom();
        $schedule = Schedule::create(['class_id' => $class->id] + $this->hours('09:00:00', '10:00:00'));
        $dispatcher = Schedule::getEventDispatcher();
        Schedule::setEventDispatcher(clone $dispatcher);
        try {
            Schedule::deleting(fn () => false);
            $this->deleteJson($this->path($class, $schedule))->assertStatus(500)->assertExactJson([
                'status' => false, 'message' => 'Unable to process the schedule request',
            ]);
            $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
        } finally {
            Schedule::setEventDispatcher($dispatcher);
        }
    }
}
