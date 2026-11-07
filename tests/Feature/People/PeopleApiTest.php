<?php

namespace Tests\Feature\People;

use App\Models\Debt;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Tests\Feature\Academics\AcademicTestCase;

class PeopleApiTest extends AcademicTestCase
{
    private function parents(): array
    {
        return ['father_name' => 'Father', 'mother_name' => 'Mother', 'father_phone' => '+989123456789', 'mother_phone' => '+989123456788'];
    }

    private function veto(string $model, callable $test): void
    {
        $original = Model::getEventDispatcher();
        Model::setEventDispatcher(new Dispatcher($this->app));
        $model::saving(fn () => false);
        try { $test(); } finally { Model::setEventDispatcher($original); }
    }

    public function test_empty_people_lists_are_successful_arrays_including_an_empty_class(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $this->getJson('/api/v1/teachers')->assertOk()->assertJsonPath('status', true)->assertJsonPath('data', []);
        $this->getJson('/api/v1/students')->assertOk()->assertJsonPath('status', true)->assertJsonPath('data', []);
        $class = $this->classroom();
        $this->getJson('/api/v1/classes/'.$class->id.'/students')->assertOk()->assertJsonPath('status', true)->assertJsonPath('data', []);
    }

    public function test_class_roster_excludes_cancelled_and_deactivated_members_and_other_classes(): void
    {
        $class = $this->classroom();
        $active = $this->account('student');
        $cancelled = $this->account('student');
        $deleted = $this->account('student');
        $this->account('student');
        foreach ([$active, $cancelled, $deleted] as $user) Registration::create(['class_id' => $class->id, 'student_id' => $user->student->id]);
        Registration::where('student_id', $cancelled->student->id)->firstOrFail()->delete();
        $deleted->student->delete();
        $this->actingAs($class->teacher->user, 'api');
        $this->getJson('/api/v1/classes/'.$class->id.'/students')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->student->id);
        $this->getJson('/api/v1/classes/'.$class->id.'/students/'.$cancelled->student->id)->assertNotFound();
    }

    public function test_salary_zero_and_large_integer_round_trip_as_exact_strings(): void
    {
        $teacher = $this->account('teacher');
        $this->actingAs($this->account('manager'), 'api');
        foreach (['0', '9007199254740993'] as $salary) {
            $this->putJson('/api/v1/teachers/'.$teacher->teacher->id.'/admin-info', ['salary' => $salary, 'user_id' => 999, 'bio' => 'forged'])
                ->assertOk()->assertJsonPath('data.salary', $salary)->assertJsonPath('data.user.id', $teacher->id);
            $this->assertSame($salary, $teacher->teacher->fresh()->salary);
            $this->assertNull($teacher->teacher->fresh()->bio);
        }
    }

    public function test_salary_rejects_negative_fractional_and_missing_values_without_mutation(): void
    {
        $teacher = $this->account('teacher');
        $teacher->teacher->update(['salary' => '100']);
        $this->actingAs($this->account('manager'), 'api');
        foreach ([-1, '1.5', '', 'abc', null] as $salary) {
            $this->putJson('/api/v1/teachers/'.$teacher->teacher->id.'/admin-info', ['salary' => $salary])->assertUnprocessable()->assertJsonValidationErrors('salary');
        }
        $this->assertSame('100', $teacher->teacher->fresh()->salary);
    }

    public function test_teacher_personal_update_cannot_change_salary_or_owner_and_obeys_column_limits(): void
    {
        $teacher = $this->account('teacher');
        $teacher->teacher->update(['salary' => '25']);
        $this->actingAs($teacher, 'api');
        $this->putJson('/api/v1/teachers/'.$teacher->teacher->id, ['salary' => 900, 'user_id' => 999, 'bio' => 'A biography', 'degree' => null])
            ->assertOk()->assertJsonPath('data.salary', '25')->assertJsonPath('data.bio', 'A biography')->assertJsonPath('data.user.id', $teacher->id);
        $this->putJson('/api/v1/teachers/'.$teacher->teacher->id, ['bio' => str_repeat('x', 256)])->assertUnprocessable();
        $this->putJson('/api/v1/teachers/'.$teacher->teacher->id.'/admin-info', ['salary' => 0])->assertForbidden();
    }

    public function test_parent_update_returns_loaded_owner_and_ignores_account_fields(): void
    {
        $student = $this->account('student');
        $this->actingAs($student, 'api');
        $this->putJson('/api/v1/students/'.$student->student->id, $this->parents() + ['user_id' => 999, 'role' => 'manager'])
            ->assertOk()->assertJsonPath('data.user.id', $student->id)->assertJsonPath('data.father_name', 'Father');
        $this->assertTrue($student->fresh()->hasRole('student'));
        $this->assertFalse($student->fresh()->hasRole('manager'));
        $this->putJson('/api/v1/students/'.$student->student->id, array_merge($this->parents(), ['father_name' => str_repeat('x', 256)]))->assertUnprocessable();
    }

    public function test_missing_profile_and_user_actions_are_404_instead_of_resolved_failures(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        foreach (['teachers', 'students', 'users'] as $type) {
            $this->getJson('/api/v1/'.$type.'/999999')->assertNotFound();
            $this->deleteJson('/api/v1/'.$type.'/999999')->assertNotFound();
            $this->putJson('/api/v1/'.$type.'/999999', [])->assertNotFound();
        }
        $this->putJson('/api/v1/teachers/999999/admin-info', ['salary' => 1])->assertNotFound();
    }

    public function test_vetoed_teacher_salary_update_is_500_and_keeps_the_original_value(): void
    {
        $teacher = $this->account('teacher');
        $teacher->teacher->update(['salary' => '100']);
        $this->actingAs($this->account('manager'), 'api');
        $this->veto(Teacher::class, function () use ($teacher) {
            $response = $this->putJson('/api/v1/teachers/'.$teacher->teacher->id.'/admin-info', ['salary' => '200'])
                ->assertStatus(500)->assertJsonPath('status', false);
            $this->assertArrayNotHasKey('error', $response->json());
        });
        $this->assertSame('100', $teacher->teacher->fresh()->salary);
    }

    public function test_vetoed_parent_update_is_not_reported_as_a_success(): void
    {
        $student = $this->account('student');
        $this->actingAs($student, 'api');
        $this->veto(Student::class, fn () => $this->putJson('/api/v1/students/'.$student->student->id, $this->parents())->assertStatus(500)->assertJsonPath('status', false));
        $this->assertNull($student->student->fresh()->father_name);
    }

    public function test_vetoed_identity_update_keeps_account_and_roles(): void
    {
        $student = $this->account('student');
        $this->actingAs($student, 'api');
        $data = $student->only(['national_id', 'first_name', 'last_name', 'phone', 'email']);
        $data['first_name'] = 'Changed';
        $this->veto(User::class, fn () => $this->putJson('/api/v1/users/'.$student->id, $data)->assertStatus(500)->assertJsonPath('status', false));
        $this->assertSame('Academic', $student->fresh()->first_name);
        $this->assertTrue($student->fresh()->hasRole('student'));
    }

    public function test_teacher_profile_creation_veto_rolls_back_account_and_roles(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $data = ['national_id' => '9999999999', 'first_name' => 'New', 'last_name' => 'Teacher', 'email' => 'new-teacher@example.test',
            'phone' => '+989999999999', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->veto(Teacher::class, fn () => $this->postJson('/api/v1/users', $data)->assertStatus(500)->assertJsonPath('status', false));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teachers', 0);
        $this->assertDatabaseCount('model_has_roles', 1);
    }

    public function test_student_profile_deactivation_preserves_class_billing_grade_and_other_roles(): void
    {
        $class = $this->classroom();
        $student = $this->account('student');
        $student->assignRole('teacher');
        $registration = Registration::create(['class_id' => $class->id, 'student_id' => $student->student->id]);
        Debt::create(['registration_id' => $registration->id, 'amount' => '100', 'remaining' => '80']);
        Grade::create(['class_id' => $class->id, 'student_id' => $student->student->id, 'grade' => 18]);
        $this->actingAs($this->account('manager'), 'api');
        $this->deleteJson('/api/v1/students/'.$student->student->id)->assertOk()->assertJsonPath('status', true);
        $this->assertSoftDeleted('students', ['id' => $student->student->id]);
        $this->assertFalse($student->fresh()->hasRole('student'));
        $this->assertTrue($student->fresh()->hasRole('teacher'));
        $this->assertNull($registration->fresh()->cancelled_at);
        $this->assertSame('80.00', $registration->fresh()->debt->remaining);
        $this->assertDatabaseCount('grades', 1);
        $this->getJson('/api/v1/students/'.$student->student->id)->assertNotFound();
    }
}
