<?php

namespace Tests\Feature\Grades;

use App\Models\Grade;
use App\Models\Registration;
use App\Services\GradeService;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Academics\AcademicTestCase;

class GradeApiTest extends AcademicTestCase
{
    private function member($class)
    {
        $user = $this->account('student');
        Registration::create(['class_id' => $class->id, 'student_id' => $user->student->id]);
        return $user;
    }

    private function path($class): string
    {
        return '/api/v1/classes/'.$class->id.'/grades';
    }

    public function test_single_grade_is_numeric_and_updates_the_same_row_including_zero_and_twenty(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        $this->actingAs($class->teacher->user, 'api');
        $id = null;
        foreach ([0, 17.25, 20] as $value) {
            $response = $this->postJson($this->path($class), ['student_id' => $student->student->id, 'grade' => $value])->assertOk();
            $this->assertIsNumeric($response->json('grade.grade'));
            $this->assertIsNotString($response->json('grade.grade'));
            $this->assertEquals($value, $response->json('grade.grade'));
            if ($id !== null) $this->assertSame($id, $response->json('grade.id'));
            $id = $response->json('grade.id');
        }
        $this->assertDatabaseCount('grades', 1);
    }

    public function test_batch_can_save_a_subset_without_changing_other_grades(): void
    {
        $class = $this->classroom();
        $first = $this->member($class);
        $second = $this->member($class);
        Grade::create(['class_id' => $class->id, 'student_id' => $second->student->id, 'grade' => '12.5']);
        $this->actingAs($class->teacher->user, 'api');
        $this->postJson($this->path($class).'/batch', ['grades' => [
            ['student_id' => $first->student->id, 'grade' => 0],
        ]])->assertOk()->assertJsonCount(1, 'grades')->assertJsonPath('grades.0.student_id', $first->student->id);
        $this->assertDatabaseHas('grades', ['student_id' => $second->student->id, 'grade' => '12.5']);
        $this->assertDatabaseCount('grades', 2);
    }

    public function test_invalid_or_duplicate_batch_is_rejected_before_any_write(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        $other = $this->account('student');
        $this->actingAs($class->teacher->user, 'api');
        $valid = ['student_id' => $student->student->id, 'grade' => 18];
        foreach ([[], [$valid, $valid], [$valid, ['student_id' => $other->student->id, 'grade' => 10]],
            [$valid, ['student_id' => $other->student->id, 'grade' => 21]]] as $entries) {
            $this->postJson($this->path($class).'/batch', ['grades' => $entries])->assertUnprocessable();
            $this->assertDatabaseCount('grades', 0);
        }
    }

    public function test_single_grade_rejects_missing_non_numeric_and_out_of_range_values(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        $this->actingAs($class->teacher->user, 'api');
        foreach ([null, '', 'abc', -0.1, 20.01] as $value) {
            $this->postJson($this->path($class), ['student_id' => $student->student->id, 'grade' => $value])
                ->assertUnprocessable()->assertJsonValidationErrors('grade');
        }
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_list_contains_real_active_roster_and_numeric_grades_and_accepts_empty_lists(): void
    {
        $class = $this->classroom();
        $this->actingAs($class->teacher->user, 'api');
        $this->getJson($this->path($class))->assertOk()->assertJsonCount(0, 'grades')->assertJsonCount(0, 'students')
            ->assertJsonPath('class.id', $class->id)->assertJsonPath('class.registrations_count', 0);
        $student = $this->member($class);
        Grade::create(['class_id' => $class->id, 'student_id' => $student->student->id, 'grade' => '18.125']);
        $response = $this->getJson($this->path($class))->assertOk()->assertJsonCount(1, 'students')->assertJsonCount(1, 'grades');
        $this->assertEquals(18.125, $response->json('grades.0.grade'));
        $this->assertSame($student->first_name, $response->json('students.0.user.first_name'));
    }

    public function test_a_missing_grade_is_a_successful_unrecorded_state_but_not_a_missing_class(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        $this->actingAs($student, 'api');
        $this->getJson('/api/v1/me/classes/'.$class->id.'/grades')->assertOk()->assertJsonPath('grade', null);
        $this->getJson('/api/v1/me/classes/999999/grades')->assertNotFound();
        $this->actingAs($class->teacher->user, 'api');
        $this->getJson('/api/v1/classes/999999/grades')->assertNotFound();
        $this->postJson('/api/v1/classes/999999/grades', ['student_id' => $student->student->id, 'grade' => 19])->assertNotFound();
    }

    public function test_cancelled_members_can_read_their_grade_history_but_cannot_receive_new_grades(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        Grade::create(['class_id' => $class->id, 'student_id' => $student->student->id, 'grade' => '16.75']);
        Registration::where('student_id', $student->student->id)->firstOrFail()->delete();
        $this->actingAs($student, 'api');
        $this->getJson('/api/v1/me/classes/'.$class->id.'/grades')->assertOk()->assertJsonPath('grade.student_id', $student->student->id);
        $classes = '/api/v1/me/studying-terms/'.$class->term_id.'/classes';
        $this->getJson($classes)->assertOk()->assertJsonCount(0, 'classes');
        $this->getJson($classes.'?include_cancelled=1')->assertOk()->assertJsonCount(1, 'classes');
        $this->actingAs($class->teacher->user, 'api');
        $this->postJson($this->path($class), ['student_id' => $student->student->id, 'grade' => 20])->assertUnprocessable();
        $this->getJson($this->path($class))->assertOk()->assertJsonCount(0, 'students')->assertJsonCount(1, 'grades');
    }

    public function test_history_flag_never_discloses_other_students_classes(): void
    {
        $class = $this->classroom();
        $this->member($class);
        $other = $this->account('student');
        $this->actingAs($other, 'api');
        $this->getJson('/api/v1/me/studying-terms/'.$class->term_id.'/classes?include_cancelled=1')->assertOk()->assertJsonCount(0, 'classes');
        $this->getJson('/api/v1/me/classes/'.$class->id.'/grades')->assertForbidden();
    }

    public function test_deleted_student_profile_keeps_grade_identity_readable_for_manager(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        Grade::create(['class_id' => $class->id, 'student_id' => $student->student->id, 'grade' => 15]);
        $student->student->delete();
        $this->actingAs($this->account('manager'), 'api');
        $this->getJson($this->path($class))->assertOk()->assertJsonPath('grades.0.student.user.id', $student->id)
            ->assertJsonCount(0, 'students');
    }

    public function test_grade_service_rechecks_cancelled_membership_after_request_validation(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        Registration::where('student_id', $student->student->id)->firstOrFail()->delete();
        try {
            app(GradeService::class)->save($class->teacher->user, (string) $class->id, [['student_id' => $student->student->id, 'grade' => 19]]);
            $this->fail('Membership must be rechecked under lock.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('grades.0.student_id', $error->errors());
        }
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_a_failed_second_save_rolls_back_the_first_and_hides_internal_error_details(): void
    {
        $class = $this->classroom();
        $first = $this->member($class);
        $second = $this->member($class);
        $this->actingAs($class->teacher->user, 'api');
        $original = Grade::getEventDispatcher();
        Grade::setEventDispatcher(new Dispatcher($this->app));
        $saved = 0;
        Grade::saving(function () use (&$saved) { return ++$saved < 2; });
        try {
            $response = $this->postJson($this->path($class).'/batch', ['grades' => [
                ['student_id' => $first->student->id, 'grade' => 17], ['student_id' => $second->student->id, 'grade' => 18],
            ]])->assertStatus(500)->assertJsonPath('status', false);
            $this->assertStringNotContainsString('could not be saved', $response->getContent());
            $this->assertDatabaseCount('grades', 0);
        } finally { Grade::setEventDispatcher($original); }
    }

    public function test_database_rejects_a_duplicate_student_class_grade(): void
    {
        $class = $this->classroom();
        $student = $this->member($class);
        Grade::create(['class_id' => $class->id, 'student_id' => $student->student->id, 'grade' => 15]);
        $this->expectException(QueryException::class);
        Grade::create(['class_id' => $class->id, 'student_id' => $student->student->id, 'grade' => 17]);
    }
}
