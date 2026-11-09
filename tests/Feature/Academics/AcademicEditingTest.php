<?php

namespace Tests\Feature\Academics;

use App\Models\ClassModel;
use App\Models\Registration;
use App\Models\Schedule;
use App\Models\Term;
use Illuminate\Support\Arr;

class AcademicEditingTest extends AcademicTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->account('manager'), 'api');
    }

    private function path(ClassModel $class): string
    {
        return '/api/v1/terms/'.$class->term_id.'/classes/'.$class->id;
    }

    private function classData(ClassModel $class, array $changes = []): array
    {
        return array_merge(Arr::only($class->toArray(), [
            'name', 'course_id', 'teacher_id', 'term_id', 'start_date', 'end_date',
            'startRegistration_Date', 'endRegistration_Date', 'capacity', 'tuition_fee',
        ]), $changes);
    }

    private function termData(Term $term, array $changes = []): array
    {
        return array_merge(Arr::only($term->toArray(), [
            'year', 'season', 'start_date', 'end_date', 'is_active',
        ]), $changes);
    }

    private function schedule(ClassModel $class): Schedule
    {
        return Schedule::create(['class_id' => $class->id, 'day_of_week' => 'monday',
            'start_time' => '09:00:00', 'end_time' => '10:00:00']);
    }

    public function test_capacity_cannot_drop_below_existing_registrations_and_rejection_keeps_the_class(): void
    {
        $class = $this->classroom();
        foreach ([1, 2] as $_) {
            Registration::create(['class_id' => $class->id, 'student_id' => $this->account('student')->student->id]);
        }
        $this->putJson($this->path($class), $this->classData($class, ['capacity' => 1, 'name' => 'Must not persist']))
            ->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $this->assertSame(10, $class->fresh()->capacity);
        $this->assertSame('Academic class', $class->fresh()->name);
        $this->putJson($this->path($class), $this->classData($class, ['capacity' => 2]))
            ->assertOk()->assertJsonPath('class.capacity', 2)->assertJsonPath('class.registrations_count', 2);
    }

    public function test_class_update_returns_fresh_teacher_and_course_relations(): void
    {
        $class = $this->classroom();
        $other = $this->classroom();
        $this->putJson($this->path($class), $this->classData($class, [
            'teacher_id' => $other->teacher_id, 'course_id' => $other->course_id,
        ]))->assertOk()->assertJsonPath('class.teacher.id', $other->teacher_id)
            ->assertJsonPath('class.course.id', $other->course_id)->assertJsonPath('class.registrations_count', 0);
    }

    public static function invalidClassNumbers(): iterable
    {
        yield 'capacity overflow' => ['capacity', 2147483648];
        yield 'fractional capacity' => ['capacity', 2.5];
        yield 'zero capacity' => ['capacity', 0];
        yield 'tuition overflow' => ['tuition_fee', '100000000.00'];
        yield 'excess precision' => ['tuition_fee', '12.345'];
        yield 'scientific notation string' => ['tuition_fee', '1e-3'];
        yield 'negative tuition' => ['tuition_fee', '-0.01'];
    }

    /** @dataProvider invalidClassNumbers */
    public function test_create_and_update_reject_numbers_outside_class_column_limits(string $field, int|float|string $value): void
    {
        $class = $this->classroom()->refresh();
        $data = $this->classData($class, ['name' => 'Must not persist', $field => $value]);

        $this->postJson('/api/v1/terms/'.$class->term_id.'/classes', $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->putJson($this->path($class), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('classes', 1);
        $this->assertSame($class->getAttributes(), $class->fresh()->getAttributes());
    }

    public function test_class_numbers_accept_column_maxima_and_free_tuition(): void
    {
        $class = $this->classroom();
        $created = $this->postJson('/api/v1/terms/'.$class->term_id.'/classes', $this->classData($class, [
            'capacity' => 2147483647, 'tuition_fee' => '99999999.99',
        ]))->assertOk()->assertJsonPath('class.capacity', 2147483647)
            ->assertJsonPath('class.tuition_fee', '99999999.99');

        $this->putJson($this->path($class), $this->classData($class, ['capacity' => 1, 'tuition_fee' => 0]))
            ->assertOk()->assertJsonPath('class.tuition_fee', '0.00');
        $this->assertSame('99999999.99', ClassModel::findOrFail($created->json('class.id'))->tuition_fee);
        $this->assertSame('0.00', $class->fresh()->tuition_fee);
    }

    public function test_changing_a_teacher_cannot_create_a_schedule_conflict(): void
    {
        $class = $this->classroom();
        $other = $this->classroom();
        $this->schedule($class);
        $this->schedule($other);
        $this->putJson($this->path($class), $this->classData($class, ['teacher_id' => $other->teacher_id]))
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertSame($class->teacher_id, $class->fresh()->teacher_id);
        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_changing_class_dates_rechecks_its_existing_schedule_and_rolls_back_on_conflict(): void
    {
        $other = $this->classroom();
        $class = $this->classroom(['teacher_id' => $other->teacher_id,
            'start_date' => now()->addMonths(3)->toDateString(), 'end_date' => now()->addMonths(4)->toDateString()]);
        $this->schedule($class);
        $this->schedule($other);
        $this->putJson($this->path($class), $this->classData($class, [
            'start_date' => $other->start_date, 'end_date' => $other->end_date,
        ]))->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertSame($class->start_date, $class->fresh()->start_date);
        $this->putJson($this->path($class), $this->classData($class, ['name' => 'Still separate']))->assertOk();
    }

    public static function termBoundaries(): iterable
    {
        yield 'start excludes a class' => ['start_date'];
        yield 'end excludes a class' => ['end_date'];
    }

    /** @dataProvider termBoundaries */
    public function test_term_dates_cannot_exclude_existing_classes(string $field): void
    {
        $class = $this->classroom();
        $term = $class->term;
        $date = $field === 'start_date' ? $class->start_date : $class->end_date;
        $date = \Carbon\CarbonImmutable::parse($date)->addDays($field === 'start_date' ? 1 : -1)->toDateString();
        $this->putJson('/api/v1/terms/'.$term->id, $this->termData($term, [$field => $date]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($term->{$field}, $term->fresh()->{$field});
    }

    public function test_term_boundaries_can_equal_existing_class_dates_and_term_can_be_closed(): void
    {
        $class = $this->classroom();
        $this->putJson('/api/v1/terms/'.$class->term_id, $this->termData($class->term, [
            'start_date' => $class->start_date, 'end_date' => $class->end_date, 'is_active' => false,
        ]))->assertOk()->assertJsonPath('term.is_active', false);
        $this->assertDatabaseCount('classes', 1);
    }

    public function test_duplicate_term_year_and_season_returns_422_on_create_and_update_but_not_self_update(): void
    {
        $class = $this->classroom();
        $term = $class->term;
        $other = Term::create($this->termData($term, ['season' => 'summer']));
        $this->postJson('/api/v1/terms', $this->termData($term))->assertUnprocessable()->assertJsonValidationErrors('season');
        $this->putJson('/api/v1/terms/'.$other->id, $this->termData($term))
            ->assertUnprocessable()->assertJsonValidationErrors('season');
        $this->putJson('/api/v1/terms/'.$term->id, $this->termData($term))->assertOk();
        $this->assertSame('summer', $other->fresh()->season);
        $this->assertDatabaseCount('terms', 2);
    }

    public function test_class_cannot_be_moved_using_a_different_payload_or_url_term(): void
    {
        $class = $this->classroom();
        $other = Term::create($this->termData($class->term, ['season' => 'summer']));
        $this->putJson($this->path($class), $this->classData($class, ['term_id' => $other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('term_id');
        $this->putJson('/api/v1/terms/'.$other->id.'/classes/'.$class->id, $this->classData($class, ['term_id' => $other->id]))
            ->assertNotFound();
        $this->assertSame($class->term_id, $class->fresh()->term_id);
    }

    public function test_inactive_terms_and_deleted_teachers_cannot_be_used_for_class_writes(): void
    {
        $class = $this->classroom();
        $class->teacher->delete();
        $this->putJson($this->path($class), $this->classData($class))->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $other = $this->classroom();
        $other->term->update(['is_active' => false]);
        $this->putJson($this->path($other), $this->classData($other))->assertUnprocessable()->assertJsonValidationErrors('term_id');
        $this->postJson('/api/v1/terms/'.$other->term_id.'/classes', $this->classData($other))
            ->assertUnprocessable()->assertJsonValidationErrors('term_id');
    }

    public function test_class_dates_outside_the_term_and_non_iso_dates_are_field_errors(): void
    {
        $class = $this->classroom();
        $this->putJson($this->path($class), $this->classData($class, ['end_date' => now()->addYear()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->putJson($this->path($class), $this->classData($class, ['start_date' => '1405/01/01']))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
    }

    public function test_missing_class_and_term_reads_return_404_instead_of_success_with_null_or_500(): void
    {
        $class = $this->classroom();
        $this->getJson('/api/v1/terms/'.$class->term_id.'/classes/999999')->assertNotFound();
        $this->putJson('/api/v1/terms/'.$class->term_id.'/classes/999999', $this->classData($class))->assertNotFound();
        $this->getJson('/api/v1/terms/999999')->assertNotFound();
    }

    public function test_a_vetoed_class_update_is_500_and_preserves_the_original_data(): void
    {
        $class = $this->classroom();
        $dispatcher = ClassModel::getEventDispatcher();
        ClassModel::setEventDispatcher(clone $dispatcher);
        try {
            ClassModel::updating(fn () => false);
            $this->putJson($this->path($class), $this->classData($class, ['name' => 'Vetoed']))
                ->assertStatus(500)->assertJsonPath('status', false)->assertJsonMissingPath('error');
            $this->assertSame('Academic class', $class->fresh()->name);
        } finally {
            ClassModel::setEventDispatcher($dispatcher);
        }
    }
}
