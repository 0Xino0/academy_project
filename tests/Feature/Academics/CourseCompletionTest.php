<?php

namespace Tests\Feature\Academics;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CourseCompletionTest extends AcademicTestCase
{
    public static function failures(): iterable
    {
        foreach (['create', 'update'] as $operation) {
            foreach (['saving', 'write', 'saved'] as $phase) yield "$operation $phase" => [$operation, $phase];
        }
    }

    /** @dataProvider failures */
    public function test_failed_course_save_never_reports_success_and_rolls_back_observer_side_effects(string $operation, string $phase): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $target = Course::create(['title' => 'Original', 'level' => 'Beginner']);
        $related = Course::create(['title' => 'Related original', 'level' => 'Advanced']);
        $dispatcher = Course::getEventDispatcher();
        Course::setEventDispatcher(clone $dispatcher);
        try {
            $event = $phase === 'write' ? ($operation === 'create' ? 'creating' : 'updating') : $phase;
            Course::{$event}(function () use ($related, $phase) {
                DB::table('courses')->where('id', $related->id)->update(['title' => 'Side effect']);
                if ($phase === 'saved') throw new RuntimeException('Internal course storage diagnostic');
                return false;
            });
            $data = ['title' => 'Never confirmed', 'level' => 'Intermediate', 'description' => null];
            $response = $operation === 'create'
                ? $this->postJson('/api/v1/courses', $data)
                : $this->putJson('/api/v1/courses/'.$target->id, $data);
            $response->assertStatus(500)->assertExactJson([
                'status' => false, 'message' => 'Unable to process the course request',
            ]);
            $this->assertDatabaseCount('courses', 2);
            $this->assertSame('Original', DB::table('courses')->where('id', $target->id)->value('title'));
            $this->assertSame('Related original', DB::table('courses')->where('id', $related->id)->value('title'));
        } finally {
            Course::setEventDispatcher($dispatcher);
        }
    }

    public static function languages(): iterable
    {
        yield 'Latin' => ['x'];
        yield 'Persian' => ['ف'];
    }

    /** @dataProvider languages */
    public function test_255_character_titles_are_accepted_in_create_and_update(string $letter): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $title = str_repeat($letter, 255);
        $id = $this->postJson('/api/v1/courses', ['title' => ' '.$title.' ', 'level' => 'Beginner'])
            ->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.title', $title)
            ->assertJsonPath('data.description', null)->json('data.id');
        $this->putJson('/api/v1/courses/'.$id, ['title' => $title, 'level' => 'Advanced'])
            ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.title', $title);
        $this->assertSame($title, Course::findOrFail($id)->title);
    }

    public static function invalidTitles(): iterable
    {
        yield '256 Latin characters' => [str_repeat('x', 256)];
        yield '256 Persian characters' => [str_repeat('ف', 256)];
        yield 'blank' => ['   '];
        yield 'array' => [['invalid']];
    }

    /** @dataProvider invalidTitles */
    public function test_invalid_titles_return_field_422_without_changes(mixed $title): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $course = Course::create(['title' => 'Keep me', 'level' => 'Beginner', 'description' => 'Keep description']);
        $data = ['title' => $title, 'level' => 'Advanced', 'description' => 'Never saved'];
        $this->postJson('/api/v1/courses', $data)->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->putJson('/api/v1/courses/'.$course->id, $data)->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->assertDatabaseCount('courses', 1);
        $this->assertSame('Keep me', $course->fresh()->title);
        $this->assertSame('Keep description', $course->fresh()->description);
    }

    public static function operations(): iterable
    {
        yield 'create' => ['create'];
        yield 'update' => ['update'];
    }

    /** @dataProvider operations */
    public function test_success_response_reads_values_actually_persisted_by_storage(string $operation): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $course = Course::create(['title' => 'Original', 'level' => 'Beginner']);
        $dispatcher = Course::getEventDispatcher();
        Course::setEventDispatcher(clone $dispatcher);
        try {
            Course::saved(function (Course $saved) {
                DB::table('courses')->where('id', $saved->id)->update(['title' => 'Persisted canonical title']);
            });
            $data = ['title' => 'Input title', 'level' => 'Intermediate'];
            $response = $operation === 'create'
                ? $this->postJson('/api/v1/courses', $data)
                : $this->putJson('/api/v1/courses/'.$course->id, $data);
            $id = $response->assertOk()->assertJsonPath('data.title', 'Persisted canonical title')->json('data.id');
            $this->assertSame('Persisted canonical title', DB::table('courses')->where('id', $id)->value('title'));
        } finally {
            Course::setEventDispatcher($dispatcher);
        }
    }

    public function test_equal_titles_remain_allowed_and_use_distinct_saved_records(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $data = ['title' => 'Repeatable title', 'level' => 'Beginner'];
        $first = $this->postJson('/api/v1/courses', $data)->assertOk()->json('data.id');
        $second = $this->postJson('/api/v1/courses', $data)->assertOk()->json('data.id');
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('courses', 2);
    }

    public function test_updating_an_archived_course_does_not_restore_or_modify_it(): void
    {
        $this->actingAs($this->account('manager'), 'api');
        $course = Course::create(['title' => 'Archived', 'level' => 'Beginner']); $course->delete();
        $this->putJson('/api/v1/courses/'.$course->id, ['title' => 'Never restored', 'level' => 'Advanced'])
            ->assertNotFound()->assertJsonPath('status', false);
        $this->assertSoftDeleted('courses', ['id' => $course->id, 'title' => 'Archived']);
    }
}
