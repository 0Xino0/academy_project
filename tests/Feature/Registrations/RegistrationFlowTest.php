<?php

namespace Tests\Feature\Registrations;

use App\Models\Debt;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationSetting;
use App\Models\Schedule;
use Illuminate\Database\QueryException;
use Tests\Feature\Academics\AcademicTestCase;

class RegistrationFlowTest extends AcademicTestCase
{
    private function available(array $changes = [], string $start = '09:00:00', string $end = '10:00:00')
    {
        $class = $this->classroom($changes);
        Schedule::create(['class_id' => $class->id, 'day_of_week' => 'monday', 'start_time' => $start, 'end_time' => $end]);
        return $class;
    }

    private function path($class, $registration = null): string
    {
        return '/api/v1/terms/'.$class->term_id.'/classes/'.$class->id.'/registrations'.($registration ? '/'.$registration->id : '');
    }

    private function enroll($class): Registration
    {
        $id = $this->postJson($this->path($class))->assertCreated()->json('registration.id');
        return Registration::findOrFail($id);
    }

    public function test_enrollment_ignores_a_forged_student_id_and_creates_one_correct_debt(): void
    {
        $class = $this->available();
        $student = $this->account('student');
        $other = $this->account('student');
        $this->actingAs($student, 'api');
        $response = $this->postJson($this->path($class), ['student_id' => $other->student->id])->assertCreated()
            ->assertJsonPath('registration.student_id', $student->student->id)
            ->assertJsonPath('registration.debt.amount', '123.45')->assertJsonPath('registration.debt.remaining', '123.45')
            ->assertJsonPath('registration.debt.waived_amount', '0.00');
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/classes')->assertOk()->assertJsonCount(1, 'classes');
        $this->postJson($this->path($class))->assertConflict()->assertJsonPath('code', 'already_registered');
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('debts', 1);
        $this->assertNotNull($response->json('registration.id'));
    }

    public function test_any_previous_positive_balance_blocks_a_new_enrollment_until_full_settlement(): void
    {
        $first = $this->available();
        $second = $this->available([], '11:00:00', '12:00:00');
        $this->actingAs($this->account('student'), 'api');
        $registration = $this->enroll($first);
        $this->postJson($this->path($second))->assertConflict()->assertJsonPath('code', 'outstanding_debt');
        $this->postJson('/api/v1/debts/'.$registration->debt->id.'/payments', ['amount' => '23.45'])->assertOk();
        $this->postJson($this->path($second))->assertConflict()->assertJsonPath('code', 'outstanding_debt');
        $this->postJson('/api/v1/debts/'.$registration->debt->id.'/payments', ['amount' => '100.00'])->assertOk();
        $this->enroll($second);
        $this->assertDatabaseCount('registrations', 2);
    }

    public function test_catalogue_and_store_share_the_registration_window_and_capacity_rules(): void
    {
        $open = $this->available(['endRegistration_Date' => now()->toDateString()]);
        $future = $this->available(['startRegistration_Date' => now()->addDay()->toDateString()]);
        $full = $this->available(['capacity' => 1]);
        Registration::create(['student_id' => $this->account('student')->student->id, 'class_id' => $full->id]);
        $this->actingAs($this->account('student'), 'api');
        $this->getJson('/api/v1/courses/active')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $open->id);
        $this->postJson($this->path($future))->assertConflict()->assertJsonPath('code', 'registration_closed');
        $this->postJson($this->path($full))->assertConflict()->assertJsonPath('code', 'class_full');
        $this->enroll($open);
    }

    public function test_student_overlap_uses_weekday_time_and_dates_including_containment_and_shared_boundaries(): void
    {
        $first = $this->available(['tuition_fee' => 0]);
        $overlap = $this->available(['tuition_fee' => 0], '09:30:00', '10:30:00');
        $boundary = $this->available(['tuition_fee' => 0], '10:00:00', '11:00:00');
        $otherDay = $this->available(['tuition_fee' => 0]);
        $otherDay->schedules()->update(['day_of_week' => 'tuesday']);
        $this->actingAs($this->account('student'), 'api');
        $this->enroll($first);
        $this->postJson($this->path($overlap))->assertConflict()->assertJsonPath('code', 'schedule_conflict');
        $this->enroll($boundary);
        $this->enroll($otherDay);
        $this->assertDatabaseCount('registrations', 3);
    }

    public function test_strict_cancellation_requires_full_payment_and_cannot_be_overridden_by_the_student(): void
    {
        $class = $this->available();
        $this->actingAs($this->account('student'), 'api');
        $registration = $this->enroll($class);
        $this->deleteJson($this->path($class, $registration), ['cancellation_requires_settlement' => false])
            ->assertConflict()->assertJsonPath('code', 'settlement_required');
        $this->assertFalse($registration->fresh()->trashed());
        $this->postJson('/api/v1/debts/'.$registration->debt->id.'/payments', ['amount' => '123.45'])->assertOk();
        $this->deleteJson($this->path($class, $registration))->assertOk();
        $this->assertSoftDeleted('registrations', ['id' => $registration->id], null, 'cancelled_at');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_allowed_cancellation_waives_only_the_balance_and_preserves_payments_grades_and_history(): void
    {
        RegistrationSetting::findOrFail(1)->update(['cancellation_requires_settlement' => false]);
        $class = $this->available();
        $student = $this->account('student');
        $this->actingAs($student, 'api');
        $registration = $this->enroll($class);
        $this->postJson('/api/v1/debts/'.$registration->debt->id.'/payments', ['amount' => '23.45'])->assertOk();
        Grade::create(['class_id' => $class->id, 'student_id' => $student->student->id, 'grade' => '18']);
        $this->deleteJson($this->path($class, $registration))->assertOk()
            ->assertJsonPath('registration.debt.amount', '123.45')->assertJsonPath('registration.debt.paid_amount', '23.45')
            ->assertJsonPath('registration.debt.waived_amount', '100.00')->assertJsonPath('registration.debt.remaining', '0.00')
            ->assertJsonPath('registration.debt.is_paid', false)->assertJsonPath('registration.debt.is_settled', true);
        $this->deleteJson($this->path($class, $registration))->assertOk()->assertJsonPath('registration.debt.waived_amount', '100.00');
        $this->getJson($this->path($class, $registration))->assertOk()->assertJsonPath('data.id', $registration->id);
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/classes')->assertOk()->assertJsonCount(0, 'classes');
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('grades', 1);
        $this->assertCount(0, $class->fresh()->registeredStudents);
        $this->getJson('/api/v1/me/students/debts')->assertOk()->assertJsonPath('remaining_debt', '0.00')
            ->assertJsonPath('paid_debt', '23.45')->assertJsonPath('waived_debt', '100.00');
        $new = $this->enroll($class);
        $this->assertNotSame($registration->id, $new->id);
        $this->assertDatabaseCount('registrations', 2);
        $this->assertDatabaseCount('debts', 2);
    }

    public function test_policy_is_readable_but_only_a_manager_can_change_it(): void
    {
        foreach (['student', 'teacher'] as $role) {
            $this->actingAs($this->account($role), 'api');
            $this->getJson('/api/v1/registration-policy')->assertOk()->assertJsonPath('policy.cancellation_requires_settlement', true);
            $this->putJson('/api/v1/registration-policy', ['cancellation_requires_settlement' => false])->assertForbidden();
        }
        $this->actingAs($this->account('manager'), 'api');
        $this->putJson('/api/v1/registration-policy', ['cancellation_requires_settlement' => false])
            ->assertOk()->assertJsonPath('policy.cancellation_requires_settlement', false);
    }

    public function test_cancellation_keeps_ownership_and_url_scopes(): void
    {
        RegistrationSetting::findOrFail(1)->update(['cancellation_requires_settlement' => false]);
        $class = $this->available();
        $owner = $this->account('student');
        $this->actingAs($owner, 'api');
        $registration = $this->enroll($class);
        $this->actingAs($this->account('student'), 'api');
        $this->deleteJson($this->path($class, $registration))->assertForbidden();
        $this->actingAs($owner, 'api');
        $this->deleteJson('/api/v1/terms/999999/classes/'.$class->id.'/registrations/'.$registration->id)->assertNotFound();
        $this->assertFalse($registration->fresh()->trashed());
    }

    public function test_parent_deletion_cannot_erase_cancelled_registration_or_payment_history(): void
    {
        RegistrationSetting::findOrFail(1)->update(['cancellation_requires_settlement' => false]);
        $class = $this->available();
        $owner = $this->account('student');
        $this->actingAs($owner, 'api');
        $registration = $this->enroll($class);
        $this->deleteJson($this->path($class, $registration))->assertOk();
        $this->actingAs($this->account('manager'), 'api');
        foreach (['/terms/'.$class->term_id.'/classes/'.$class->id, '/terms/'.$class->term_id,
            '/courses/'.$class->course_id, '/users/'.$owner->id, '/users/'.$class->teacher->user_id] as $path) {
            $this->deleteJson('/api/v1'.$path)->assertOk();
        }
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('debts', 1);
        $this->assertSoftDeleted('classes', ['id' => $class->id]);
        $this->assertSoftDeleted('users', ['id' => $owner->id]);
    }

    public function test_cancelled_membership_cannot_be_used_to_write_a_grade(): void
    {
        RegistrationSetting::findOrFail(1)->update(['cancellation_requires_settlement' => false]);
        $class = $this->available();
        $student = $this->account('student');
        $this->actingAs($student, 'api');
        $registration = $this->enroll($class);
        $this->deleteJson($this->path($class, $registration))->assertOk();
        $this->actingAs($class->teacher->user, 'api');
        $this->postJson('/api/v1/classes/'.$class->id.'/grades', ['student_id' => $student->student->id, 'grade' => 19])
            ->assertUnprocessable();
    }

    public function test_debt_creation_failure_rolls_back_the_entire_enrollment(): void
    {
        $class = $this->available();
        $this->actingAs($this->account('student'), 'api');
        $dispatcher = Debt::getEventDispatcher();
        Debt::setEventDispatcher(clone $dispatcher);
        try {
            Debt::creating(fn () => false);
            $this->postJson($this->path($class))->assertStatus(500)->assertJsonPath('status', false)->assertJsonMissingPath('error');
            $this->assertDatabaseCount('registrations', 0);
            $this->assertDatabaseCount('debts', 0);
        } finally { Debt::setEventDispatcher($dispatcher); }
    }

    public function test_an_active_duplicate_is_rejected_by_the_database_constraint(): void
    {
        $class = $this->available();
        $this->actingAs($this->account('student'), 'api');
        $registration = $this->enroll($class);
        $this->expectException(QueryException::class);
        Registration::create(['student_id' => $registration->student_id, 'class_id' => $class->id]);
    }

    public function test_a_failed_cancellation_rolls_back_the_waiver_and_keeps_the_enrollment_active(): void
    {
        RegistrationSetting::findOrFail(1)->update(['cancellation_requires_settlement' => false]);
        $class = $this->available();
        $this->actingAs($this->account('student'), 'api');
        $registration = $this->enroll($class);
        $dispatcher = Registration::getEventDispatcher();
        Registration::setEventDispatcher(clone $dispatcher);
        try {
            Registration::deleting(fn () => false);
            $this->deleteJson($this->path($class, $registration))->assertStatus(500);
            $this->assertFalse($registration->fresh()->trashed());
            $this->assertSame('123.45', $registration->debt->fresh()->remaining);
            $this->assertSame('0.00', $registration->debt->fresh()->waived_amount);
        } finally { Registration::setEventDispatcher($dispatcher); }
    }

    public function test_missing_historical_billing_blocks_enrollment_and_cancellation_without_inventing_a_debt(): void
    {
        $first = $this->available();
        $second = $this->available([], '11:00:00', '12:00:00');
        $student = $this->account('student');
        $registration = Registration::create(['student_id' => $student->student->id, 'class_id' => $first->id]);
        $this->actingAs($student, 'api');
        $this->postJson($this->path($second))->assertConflict()->assertJsonPath('code', 'missing_billing_record');
        $this->deleteJson($this->path($first, $registration))->assertConflict()->assertJsonPath('code', 'missing_billing_record');
        $this->assertDatabaseCount('debts', 0);
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_contained_class_dates_still_conflict_when_weekday_and_time_match(): void
    {
        $first = $this->available(['tuition_fee' => 0, 'end_date' => now()->addMonths(3)->toDateString()]);
        $second = $this->available(['tuition_fee' => 0, 'start_date' => now()->addMonth()->addWeek()->toDateString()]);
        $this->actingAs($this->account('student'), 'api');
        $this->enroll($first);
        $this->postJson($this->path($second))->assertConflict()->assertJsonPath('code', 'schedule_conflict');
    }
}
