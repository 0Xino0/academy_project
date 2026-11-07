<?php

namespace Tests\Feature\Academics;

use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\IsolatedSchema;
use Tests\TestCase;

class CourseDatabaseTest extends TestCase
{
    use IsolatedSchema;

    public static function drivers(): array
    {
        return ['SQLite' => ['sqlite'], 'PostgreSQL (opt-in)' => ['pgsql']];
    }

    #[DataProvider('drivers')]
    public function test_course_service_persists_and_rolls_back_on_the_existing_schema(string $driver): void
    {
        $this->withIsolatedSchema($driver, function () {
            Schema::create('courses', function (Blueprint $table) {
                $table->id(); $table->string('title'); $table->string('level');
                $table->text('description')->nullable(); $table->timestamps(); $table->softDeletes();
            });
            $service = app(CourseService::class);
            $data = ['title' => str_repeat('ف', 255), 'level' => 'Beginner'];
            $first = $service->save($data); $second = $service->save($data);
            $this->assertNotSame($first->id, $second->id);
            $this->assertSame($data['title'], $first->title);
            $this->assertNull($first->description);
            $this->assertSame(2, Course::count());
            $updated = $service->save(['title' => 'Updated', 'level' => 'Advanced'], (string) $first->id);
            $this->assertSame($first->id, $updated->id);
            $this->assertSame('Updated', DB::table('courses')->where('id', $first->id)->value('title'));

            $dispatcher = Course::getEventDispatcher();
            Course::setEventDispatcher(clone $dispatcher);
            try {
                Course::updating(function () use ($first) {
                    DB::table('courses')->where('id', $first->id)->update(['description' => 'Uncommitted side effect']);
                    return false;
                });
                try {
                    $service->save(['title' => 'Rejected'], (string) $second->id);
                    $this->fail('A vetoed update must not be reported as saved.');
                } catch (RuntimeException $error) {
                    $this->assertSame('The course could not be saved.', $error->getMessage());
                }
                $this->assertNull(DB::table('courses')->where('id', $first->id)->value('description'));
                $this->assertSame($data['title'], DB::table('courses')->where('id', $second->id)->value('title'));
            } finally {
                Course::setEventDispatcher($dispatcher);
            }

            Course::setEventDispatcher(clone $dispatcher);
            try {
                Course::saved(function (Course $course) {
                    DB::table('courses')->where('id', $course->id)->update(['title' => 'Actually stored']);
                });
                $saved = $service->save(['title' => 'Input title'], (string) $first->id);
                $this->assertSame('Actually stored', $saved->title);
            } finally {
                Course::setEventDispatcher($dispatcher);
            }

            $second->delete();
            try {
                $service->save(['title' => 'Never restored'], (string) $second->id);
                $this->fail('An archived course must stay unavailable for update.');
            } catch (ModelNotFoundException) {
                $this->assertSame(1, Course::count());
            }
            $this->assertSame($data['title'], Course::withTrashed()->findOrFail($second->id)->title);
            $this->assertSame(2, Course::withTrashed()->count());
        });
    }
}
