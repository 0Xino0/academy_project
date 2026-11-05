<?php

namespace Tests\Feature\Academics;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Term;
use App\Models\User;
use App\Services\AccountService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AcademicTestCase extends TestCase
{
    use RefreshDatabase;

    private int $identity = 1;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        // Keep browser testing and the developer database independent of this suite.
        $app['config']->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
            'jwt.secret' => 'academy-academics-tests-only-0123456789abcdef',
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function account(string $role): User
    {
        $identity = $this->identity++;

        return app(AccountService::class)->create([
            'first_name' => 'Academic', 'last_name' => 'Test',
            'email' => 'academic'.$identity.'@example.test',
            'national_id' => (string) (1000000000 + $identity),
            'phone' => '+989'.str_pad((string) $identity, 9, '0', STR_PAD_LEFT),
            'password' => 'academic-test-password',
        ], $role);
    }

    protected function classroom(array $attributes = []): ClassModel
    {
        $teacher = $this->account('teacher')->teacher;
        $term = Term::firstOrCreate(['year' => 1405, 'season' => 'fall'], [
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(), 'is_active' => true,
        ]);
        $course = Course::create(['title' => 'English', 'level' => 'Beginner']);

        return ClassModel::create(array_merge([
            'name' => 'Academic class', 'teacher_id' => $teacher->id,
            'course_id' => $course->id, 'term_id' => $term->id,
            'capacity' => 10, 'tuition_fee' => 123.45,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'startRegistration_Date' => now()->subDay()->toDateString(),
            'endRegistration_Date' => now()->addWeek()->toDateString(),
        ], $attributes));
    }
}
