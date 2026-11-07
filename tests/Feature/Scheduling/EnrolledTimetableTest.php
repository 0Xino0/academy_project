<?php

namespace Tests\Feature\Scheduling;

use App\Models\Debt;
use App\Models\Registration;
use App\Models\Schedule;
use App\Models\Term;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Academics\AcademicTestCase;

class EnrolledTimetableTest extends AcademicTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-07 10:00:00');
        $this->actingAs($this->account('manager'), 'api');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function classWithSlot(array $attributes = [], string $start = '08:00:00', string $end = '09:00:00')
    {
        $class = $this->classroom(array_merge(['start_date' => '2027-01-04', 'end_date' => '2027-01-31'], $attributes));
        $slot = Schedule::create(['class_id' => $class->id, 'day_of_week' => 'monday', 'start_time' => $start, 'end_time' => $end]);
        return [$class, $slot];
    }

    private function member($class, $student): Registration
    {
        return Registration::create(['class_id' => $class->id, 'student_id' => $student->id]);
    }

    private function path($class, $slot = null): string
    {
        return '/api/v1/terms/'.$class->term_id.'/classes/'.$class->id.'/schedules'.($slot ? '/'.$slot->id : '');
    }

    private function classData($class, array $changes = []): array
    {
        return array_merge($class->only(['name', 'teacher_id', 'course_id', 'term_id', 'capacity', 'tuition_fee', 'start_date',
            'end_date', 'startRegistration_Date', 'endRegistration_Date']), $changes);
    }

    public function test_edit_rejects_overlap_for_a_shared_student_with_different_teachers_and_rolls_back(): void
    {
        [$class, $slot] = $this->classWithSlot();
        [$other] = $this->classWithSlot([], '09:00:00', '10:00:00');
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:30', 'end_time' => '10:30'])
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertSame('08:00:00', $slot->fresh()->start_time);
        $this->assertDatabaseCount('schedules', 2);
    }

    public function test_adding_another_slot_cannot_create_a_student_conflict(): void
    {
        [$class] = $this->classWithSlot();
        [$other] = $this->classWithSlot([], '09:00:00', '10:00:00');
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $this->postJson($this->path($class), ['day_of_week' => 'monday', 'start_time' => '09:15', 'end_time' => '09:45'])
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertDatabaseCount('schedules', 2);
    }

    public static function changedDates(): array
    {
        return [
            'extend the end date' => [['start_date' => '2027-01-04', 'end_date' => '2027-01-10'],
                ['start_date' => '2027-01-18', 'end_date' => '2027-01-31'], ['end_date' => '2027-01-20']],
            'move the start date' => [['start_date' => '2027-01-25', 'end_date' => '2027-02-10'],
                ['start_date' => '2027-01-04', 'end_date' => '2027-01-18'], ['start_date' => '2027-01-11']],
            'contain the other range' => [['start_date' => '2027-01-04', 'end_date' => '2027-01-10'],
                ['start_date' => '2027-01-18', 'end_date' => '2027-01-24'], ['end_date' => '2027-02-01']],
        ];
    }

    #[DataProvider('changedDates')]
    public function test_date_changes_recheck_students_and_roll_back_every_class_field(array $dates, array $otherDates, array $changes): void
    {
        [$class] = $this->classWithSlot($dates, '09:00:00', '10:00:00');
        [$other] = $this->classWithSlot($otherDates, '09:00:00', '10:00:00');
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $this->putJson('/api/v1/terms/'.$class->term_id.'/classes/'.$class->id,
            $this->classData($class, $changes + ['name' => 'Must roll back', 'tuition_fee' => '999.00']))
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $fresh = $class->fresh();
        $this->assertSame($class->name, $fresh->name);
        $this->assertSame($class->tuition_fee, $fresh->tuition_fee);
        $this->assertSame($dates['start_date'], $fresh->start_date);
        $this->assertSame($dates['end_date'], $fresh->end_date);
    }

    public function test_students_are_checked_across_terms_even_when_the_other_term_is_inactive(): void
    {
        [$class, $slot] = $this->classWithSlot();
        $term = Term::create(['year' => 1406, 'season' => 'spring', 'is_active' => false, 'start_date' => '2026-12-01', 'end_date' => '2027-04-30']);
        [$other] = $this->classWithSlot(['term_id' => $term->id], '09:00:00', '10:00:00');
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
    }

    public function test_different_students_and_teachers_can_have_simultaneous_classes(): void
    {
        [$class, $slot] = $this->classWithSlot();
        [$other] = $this->classWithSlot([], '09:00:00', '10:00:00');
        $this->member($class, $this->account('student')->student);
        $this->member($other, $this->account('student')->student);
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertOk();
    }

    public static function allowedChanges(): array
    {
        return [
            'back to back' => [[], [], 'monday', '10:00', '11:00'],
            'different weekday' => [[], [], 'tuesday', '09:00', '10:00'],
            'disjoint dates' => [['start_date' => '2027-02-01', 'end_date' => '2027-02-28'], [], 'monday', '09:00', '10:00'],
            'shared date has no Monday' => [['start_date' => '2027-01-05', 'end_date' => '2027-01-06'],
                ['start_date' => '2027-01-06', 'end_date' => '2027-01-07'], 'monday', '09:00', '10:00'],
        ];
    }

    #[DataProvider('allowedChanges')]
    public function test_only_real_weekly_occurrences_conflict(array $dates, array $otherDates, string $day, string $start, string $end): void
    {
        [$class, $slot] = $this->classWithSlot($dates);
        [$other] = $this->classWithSlot($otherDates, '09:00:00', '10:00:00');
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $this->putJson($this->path($class, $slot), ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end])->assertOk();
    }

    public static function cancelledMemberships(): array
    {
        return ['target cancelled' => [true], 'other cancelled' => [false]];
    }

    #[DataProvider('cancelledMemberships')]
    public function test_cancelled_enrollments_do_not_block_timetable_changes(bool $target): void
    {
        [$class, $slot] = $this->classWithSlot();
        [$other] = $this->classWithSlot([], '09:00:00', '10:00:00');
        $student = $this->account('student')->student;
        $first = $this->member($class, $student); $second = $this->member($other, $student);
        ($target ? $first : $second)->delete();
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertOk();
    }

    public function test_active_enrollment_is_protected_even_if_student_profile_is_deactivated(): void
    {
        [$class, $slot] = $this->classWithSlot();
        [$other] = $this->classWithSlot([], '09:00:00', '10:00:00');
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $student->delete();
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertUnprocessable();
    }

    public function test_all_participants_are_checked_including_the_last_student(): void
    {
        [$class, $slot] = $this->classWithSlot();
        [$other] = $this->classWithSlot([], '09:00:00', '10:00:00');
        $this->member($class, $this->account('student')->student);
        $last = $this->account('student')->student;
        $this->member($class, $last); $this->member($other, $last);
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertUnprocessable();
    }

    public function test_legacy_day_case_whitespace_and_am_pm_still_detect_conflicts(): void
    {
        [$class, $slot] = $this->classWithSlot();
        [$other, $old] = $this->classWithSlot([], '9:00 AM', '10:00 AM');
        $old->update(['day_of_week' => ' Monday ']);
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertUnprocessable();
        $this->assertSame(' Monday ', $old->fresh()->getRawOriginal('day_of_week'));
    }

    public function test_invalid_other_timetable_is_a_field_error_and_not_a_silent_success(): void
    {
        [$class, $slot] = $this->classWithSlot();
        [$other, $old] = $this->classWithSlot([], 'invalid', '10:00:00');
        $student = $this->account('student')->student;
        $this->member($class, $student); $this->member($other, $student);
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '11:00', 'end_time' => '12:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertSame('08:00:00', $slot->fresh()->start_time);
        $this->assertSame('invalid', $old->fresh()->start_time);
    }

    public function test_date_edit_accepts_valid_legacy_slots_without_rewriting_them(): void
    {
        [$class, $slot] = $this->classWithSlot([], '9:00 AM', '10:00 AM');
        $slot->update(['day_of_week' => ' Monday ']);
        $this->member($class, $this->account('student')->student);
        $this->putJson('/api/v1/terms/'.$class->term_id.'/classes/'.$class->id,
            $this->classData($class, ['end_date' => '2027-02-01']))->assertOk();
        $this->assertSame(' Monday ', $slot->fresh()->getRawOriginal('day_of_week'));
        $this->assertSame('9:00 AM', $slot->fresh()->getRawOriginal('start_time'));
    }

    public function test_enrollment_and_later_edits_use_the_same_rule(): void
    {
        [$class, $slot] = $this->classWithSlot([], '09:00:00', '10:00:00');
        [$other] = $this->classWithSlot([], '09:00:00', '10:00:00');
        $user = $this->account('student');
        $registration = $this->member($other, $user->student);
        Debt::create(['registration_id' => $registration->id, 'amount' => '0.00', 'remaining' => '0.00']);
        $this->actingAs($user, 'api');
        $path = '/api/v1/terms/'.$class->term_id.'/classes/'.$class->id.'/registrations';
        $this->postJson($path)->assertConflict()->assertJsonPath('code', 'schedule_conflict');
        $this->actingAs($this->account('manager'), 'api');
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '11:00', 'end_time' => '12:00'])->assertOk();
        $this->actingAs($user, 'api');
        $this->postJson($path)->assertCreated();
        $this->actingAs($this->account('manager'), 'api');
        $this->putJson($this->path($class, $slot), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertUnprocessable();
    }

    public function test_missing_existing_timetables_can_be_repaired_sequentially_without_creating_a_conflict(): void
    {
        $first = $this->classroom(); $second = $this->classroom();
        $student = $this->account('student')->student;
        $this->member($first, $student); $this->member($second, $student);
        $this->postJson($this->path($first), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertOk();
        $this->postJson($this->path($second), ['day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00'])->assertUnprocessable();
        $this->postJson($this->path($second), ['day_of_week' => 'monday', 'start_time' => '10:00', 'end_time' => '11:00'])->assertOk();
    }
}
