<?php

namespace Tests\Feature\Academics;

use App\Models\ClassModel;
use App\Models\Debt;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Registration;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class LogicalDeletionTest extends AcademicTestCase
{
    private function enrolled($class, $owner, string $amount = '100.00', string $remaining = '80.00')
    {
        $registration = Registration::create(['class_id' => $class->id, 'student_id' => $owner->student->id]);
        $debt = Debt::create(['registration_id' => $registration->id, 'amount' => $amount, 'remaining' => $remaining]);
        return [$registration, $debt];
    }

    private function path($class): string
    {
        return '/api/v1/terms/'.$class->term_id.'/classes/'.$class->id;
    }

    public function test_class_archive_closes_all_enrollments_and_schedules_waives_balances_and_keeps_history(): void
    {
        $class = $this->classroom();
        $owner = $this->account('student');
        [$registration, $debt] = $this->enrolled($class, $owner);
        [$second] = $this->enrolled($class, $this->account('student'), '100.00', '100.00');
        $schedule = Schedule::create(['class_id' => $class->id, 'day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00']);
        $payment = Payment::create(['debt_id' => $debt->id, 'amount' => '20.00', 'paid_at' => '2026-11-07']);
        Grade::create(['class_id' => $class->id, 'student_id' => $owner->student->id, 'grade' => 18]);
        $this->actingAs($this->account('manager'), 'api');
        $this->deleteJson($this->path($class))->assertOk();
        $this->assertSoftDeleted('classes', ['id' => $class->id]);
        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
        foreach ([$registration, $second] as $row) $this->assertSoftDeleted('registrations', ['id' => $row->id], null, 'cancelled_at');
        $this->assertSame('100.00', $debt->fresh()->amount);
        $this->assertSame('20.00', $debt->fresh()->paid_amount);
        $this->assertSame('80.00', $debt->fresh()->waived_amount);
        $this->assertSame('0.00', $debt->fresh()->remaining);
        $this->assertDatabaseCount('payments', 1); $this->assertDatabaseCount('grades', 1);
        $this->getJson($this->path($class))->assertNotFound();
        $this->getJson('/api/v1/payments/'.$payment->id)->assertOk()->assertJsonPath('data.debt.registration.class.id', $class->id);
        $this->actingAs($owner, 'api');
        $this->getJson('/api/v1/me/classes/'.$class->id.'/grades')->assertOk()->assertJsonPath('grade.student_id', $owner->student->id);
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/classes')->assertOk()->assertJsonCount(0, 'classes');
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/classes?include_cancelled=1')->assertOk()->assertJsonCount(1, 'classes');
    }

    public function test_class_archive_waives_old_cancelled_balances_but_never_another_class_debt(): void
    {
        $class = $this->classroom(); $other = $this->classroom();
        $owner = $this->account('student');
        [$old, $debt] = $this->enrolled($class, $owner); $old->delete();
        [, $otherDebt] = $this->enrolled($other, $owner, '50.00', '50.00');
        $cancelledAt = $old->fresh()->cancelled_at;
        $this->actingAs($this->account('manager'), 'api');
        $this->deleteJson($this->path($class))->assertOk();
        $this->assertSame('80.00', $debt->fresh()->waived_amount);
        $this->assertSame('50.00', $otherDebt->fresh()->remaining);
        $this->assertEquals($cancelledAt, $old->fresh()->cancelled_at);
    }

    public function test_term_and_course_archives_close_children_without_erasing_financial_records(): void
    {
        $class = $this->classroom();
        $second = $this->classroom(['course_id' => $class->course_id]);
        $owner = $this->account('student');
        $this->enrolled($class, $owner); $this->enrolled($second, $owner);
        $this->actingAs($this->account('manager'), 'api');
        $this->deleteJson('/api/v1/courses/'.$class->course_id)->assertOk();
        $this->assertSoftDeleted('classes', ['id' => $class->id]);
        $this->assertSoftDeleted('classes', ['id' => $second->id]);
        $this->assertDatabaseCount('debts', 2);
        $this->deleteJson('/api/v1/terms/'.$class->term_id)->assertOk();
        $this->assertSoftDeleted('terms', ['id' => $class->term_id]);
        $this->actingAs($owner, 'api');
        $this->getJson('/api/v1/terms')->assertOk()->assertJsonCount(0, 'terms');
        $this->getJson('/api/v1/terms?include_archived=1')->assertOk()->assertJsonCount(1, 'terms');
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/classes?include_cancelled=1')->assertOk()->assertJsonCount(2, 'classes');
    }

    public function test_missing_historical_bill_of_an_archived_class_does_not_block_a_new_enrollment(): void
    {
        $class = $this->classroom();
        $owner = $this->account('student');
        Registration::create(['class_id' => $class->id, 'student_id' => $owner->student->id]);
        $this->actingAs($this->account('manager'), 'api');
        $this->deleteJson($this->path($class))->assertOk();
        $other = $this->classroom();
        Schedule::create(['class_id' => $other->id, 'day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->actingAs($owner, 'api');
        $this->postJson($this->path($other).'/registrations')->assertCreated();
        $this->assertDatabaseCount('debts', 1);
    }

    public static function vetoStages(): array
    {
        return ['class delete veto' => [ClassModel::class], 'schedule delete veto' => [Schedule::class]];
    }

    #[DataProvider('vetoStages')]
    public function test_class_archive_failure_rolls_back_waiver_and_every_child_change(string $model): void
    {
        $class = $this->classroom();
        [$registration, $debt] = $this->enrolled($class, $this->account('student'));
        $schedule = Schedule::create(['class_id' => $class->id, 'day_of_week' => 'monday', 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->actingAs($this->account('manager'), 'api');
        $original = Model::getEventDispatcher(); Model::setEventDispatcher(new Dispatcher($this->app));
        $model::deleting(fn () => false);
        try { $this->deleteJson($this->path($class))->assertStatus(500); }
        finally { Model::setEventDispatcher($original); }
        $this->assertNull($class->fresh()->deleted_at); $this->assertNull($schedule->fresh()->deleted_at);
        $this->assertNull($registration->fresh()->cancelled_at);
        $this->assertSame('80.00', $debt->fresh()->remaining); $this->assertSame('0.00', $debt->fresh()->waived_amount);
    }

    public function test_archived_course_cannot_receive_a_new_class(): void
    {
        $class = $this->classroom(); $class->course->delete();
        $this->actingAs($this->account('manager'), 'api');
        $data = $class->only(['name', 'teacher_id', 'course_id', 'term_id', 'start_date', 'end_date', 'capacity', 'tuition_fee', 'startRegistration_Date', 'endRegistration_Date']);
        $this->postJson('/api/v1/terms/'.$class->term_id.'/classes', $data)->assertUnprocessable()->assertJsonValidationErrors('course_id');
    }

    public function test_user_delete_archives_profiles_and_preserves_identity_for_financial_history(): void
    {
        $class = $this->classroom(); $owner = $this->account('student');
        [, $debt] = $this->enrolled($class, $owner);
        $payment = Payment::create(['debt_id' => $debt->id, 'amount' => '20.00', 'paid_at' => '2026-11-07']);
        $this->actingAs($this->account('manager'), 'api');
        $this->deleteJson('/api/v1/users/'.$owner->id)->assertOk();
        $this->assertSoftDeleted('users', ['id' => $owner->id]); $this->assertSoftDeleted('students', ['id' => $owner->student->id]);
        $this->assertNotNull(User::withTrashed()->find($owner->id));
        $this->getJson('/api/v1/payments/'.$payment->id)->assertOk()->assertJsonPath('data.debt.registration.student.user.id', $owner->id);
        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'academic-test-password'])->assertUnauthorized();
    }

    public function test_custom_role_and_permission_deletion_is_logical_and_removed_from_active_authorization(): void
    {
        $role = Role::create(['name' => 'archive-test', 'guard_name' => 'api']);
        $permission = Permission::create(['name' => 'archive-test-action', 'guard_name' => 'api']);
        $role->givePermissionTo($permission);
        $owner = $this->account('student'); $owner->assignRole($role);
        $this->assertTrue($owner->fresh()->can('archive-test-action'));
        $pivots = DB::table('role_has_permissions')->count();
        $this->actingAs($this->account('manager'), 'api');
        $this->deleteJson('/api/v1/permissions/'.$permission->id)->assertOk();
        $this->assertSoftDeleted('permissions', ['id' => $permission->id]);
        $this->assertFalse($owner->fresh()->can('archive-test-action'));
        $this->assertSame($pivots, DB::table('role_has_permissions')->count());
        $this->deleteJson('/api/v1/roles/'.$role->id)->assertOk();
        $this->assertSoftDeleted('roles', ['id' => $role->id]);
        $this->assertFalse($owner->fresh()->hasRole('archive-test'));
    }

    public function test_core_role_names_required_by_the_application_cannot_be_deleted_or_renamed(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        foreach (['manager', 'teacher', 'student'] as $name) {
            $role = Role::where('name', $name)->firstOrFail();
            $this->deleteJson('/api/v1/roles/'.$role->id)->assertUnprocessable();
            $this->putJson('/api/v1/roles/'.$role->id, ['name' => $name.'-renamed'])->assertUnprocessable();
            $this->assertNull($role->fresh()->deleted_at);
            $this->assertSame($name, $role->fresh()->name);
        }
    }
}
