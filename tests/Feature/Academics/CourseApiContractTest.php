<?php

namespace Tests\Feature\Academics;

use App\Models\Course;
use App\Models\Registration;
use App\Models\Schedule;
use RuntimeException;

class CourseApiContractTest extends AcademicTestCase
{
    public function test_course_list_can_be_empty_and_nullable_description_is_preserved(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $this->getJson('/api/v1/courses')->assertOk()->assertJsonPath('status', true)->assertJsonPath('data', []);
        $course = Course::create(['title' => 'English', 'level' => 'Beginner', 'description' => null]);
        $this->getJson('/api/v1/courses')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $course->id)->assertJsonPath('data.0.description', null);
    }

    public function test_course_create_read_update_delete_use_the_same_success_key(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $data = ['title' => 'English', 'level' => 'Beginner', 'description' => null];
        $id = $this->postJson('/api/v1/courses', $data)->assertOk()->assertJsonPath('status', true)->json('data.id');
        $this->getJson('/api/v1/courses/'.$id)->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.id', $id);
        $data['title'] = 'Updated English';
        $this->putJson('/api/v1/courses/'.$id, $data)->assertOk()->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Updated English');
        $this->deleteJson('/api/v1/courses/'.$id)->assertOk()->assertJsonPath('status', true)->assertJsonMissingPath('stauts');
        $this->assertDatabaseMissing('courses', ['id' => $id]);
    }

    public function test_missing_courses_return_404_instead_of_a_resolved_error_with_a_typo(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $data = ['title' => 'English', 'level' => 'Beginner'];
        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            $this->json($method, '/api/v1/courses/999999', $data)->assertNotFound()
                ->assertJsonPath('status', false)->assertJsonMissingPath('stauts')->assertJsonMissingPath('error');
        }
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_unexpected_course_failure_is_500_without_exposing_internal_details(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        Course::create(['title' => 'English', 'level' => 'Beginner']);
        $dispatcher = Course::getEventDispatcher();
        Course::setEventDispatcher(clone $dispatcher);

        try {
            Course::retrieved(function () {
                throw new RuntimeException('Internal SQL diagnostic that must stay on the server');
            });
            $this->getJson('/api/v1/courses')->assertStatus(500)->assertExactJson([
                'status' => false, 'message' => 'Unable to process the course request',
            ]);
        } finally {
            Course::setEventDispatcher($dispatcher);
        }
    }

    public function test_invalid_course_data_is_a_validation_error_without_creating_a_course(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $this->postJson('/api/v1/courses', ['title' => 'English', 'level' => 'Unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors('level');
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_active_classes_include_registration_ids_fee_count_and_their_own_schedules(): void
    {
        $class = $this->classroom();
        $student = $this->account('student');
        Registration::create(['class_id' => $class->id, 'student_id' => $student->student->id]);
        Schedule::create(['class_id' => $class->id, 'day_of_week' => 'Monday',
            'start_time' => '09:00:00', 'end_time' => '10:00:00']);
        $expired = $this->classroom(['endRegistration_Date' => now()->subDay()->toDateString()]);
        Schedule::create(['class_id' => $expired->id, 'day_of_week' => 'Tuesday',
            'start_time' => '11:00:00', 'end_time' => '12:00:00']);
        $this->actingAs($student, 'api');

        $response = $this->getJson('/api/v1/courses/active')->assertOk()->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $class->id)
            ->assertJsonPath('data.0.term_id', $class->term_id)
            ->assertJsonPath('data.0.tuition_fee', '123.45')
            ->assertJsonPath('data.0.registered_students_count', 1)
            ->assertJsonCount(1, 'data.0.schedules')->assertJsonPath('data.0.schedules.0.day_of_week', 'Monday');
        $this->assertSame('Academic Test', $response->json('data.0.teacher_name'));
    }

    public function test_active_classes_without_schedules_or_with_a_deleted_teacher_do_not_crash(): void
    {
        $class = $this->classroom();
        $class->teacher->delete();
        $this->actingAs($this->account('student'), 'api');

        $this->getJson('/api/v1/courses/active')->assertOk()->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.teacher_name', null)
            ->assertJsonPath('data.0.schedules', []);
    }

    public function test_active_classes_exclude_inactive_terms_and_accept_the_last_registration_day(): void
    {
        $class = $this->classroom(['endRegistration_Date' => now()->toDateString()]);
        $this->actingAs($this->account('student'), 'api');
        $this->getJson('/api/v1/courses/active')->assertOk()->assertJsonCount(1, 'data');
        $class->term->update(['is_active' => false]);
        $this->getJson('/api/v1/courses/active')->assertOk()->assertJsonPath('data', []);
    }
}
