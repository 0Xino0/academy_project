<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Debt;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $identity = 1;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        // Never use a developer database, including when config is cached.
        $app['config']->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
            'jwt.secret' => 'academy-authorization-tests-only-0123456789abcdef',
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public static function administrativeRequests(): iterable
    {
        $requests = [
            ['POST', 'auth/registerAdmin'],
            ['GET', 'roles'], ['POST', 'roles'], ['PUT', 'roles/1'], ['DELETE', 'roles/1'],
            ['GET', 'roles/1/permissions'], ['PUT', 'roles/1/permissions'], ['DELETE', 'roles/1/permissions'],
            ['GET', 'permissions'], ['POST', 'permissions'], ['PUT', 'permissions/1'], ['DELETE', 'permissions/1'],
            ['GET', 'users'], ['POST', 'users'], ['DELETE', 'users/1'],
            ['GET', 'teachers'], ['PUT', 'teachers/1/admin-info'], ['DELETE', 'teachers/1'],
            ['GET', 'students'], ['DELETE', 'students/1'],
            ['POST', 'courses'], ['PUT', 'courses/1'], ['DELETE', 'courses/1'],
            ['POST', 'terms'], ['PUT', 'terms/1'], ['DELETE', 'terms/1'],
            ['GET', 'terms/1/classes'], ['GET', 'terms/1/classes/1'],
            ['POST', 'terms/1/classes'], ['PUT', 'terms/1/classes/1'], ['DELETE', 'terms/1/classes/1'],
            ['GET', 'terms/1/schedules'], ['POST', 'terms/1/classes/1/schedules'],
            ['PUT', 'terms/1/classes/1/schedules/1'], ['DELETE', 'terms/1/classes/1/schedules/1'],
            ['GET', 'debts'], ['GET', 'payments'],
        ];

        foreach (['student', 'teacher'] as $role) {
            foreach ($requests as [$method, $path]) {
                yield "$role $method $path" => [$role, $method, $path];
            }
        }
    }

    /** @dataProvider administrativeRequests */
    public function test_non_managers_cannot_use_administrative_endpoints(string $role, string $method, string $path): void
    {
        $this->asUser($this->makeUser($role));
        $this->json($method, '/api/v1/'.$path)->assertForbidden();
    }

    public function test_anonymous_admin_registration_is_rejected_even_without_an_accept_header(): void
    {
        $this->post('/api/v1/auth/registerAdmin', $this->registrationData())
            ->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_manager_can_create_another_manager(): void
    {
        $this->asUser($this->makeUser('manager'));
        $data = $this->registrationData();
        $this->postJson('/api/v1/auth/registerAdmin', $data)
            ->assertOk()->assertJsonPath('user.roles.0.name', 'manager');
        $this->assertTrue(User::where('email', $data['email'])->firstOrFail()->hasRole('manager'));
    }

    public function test_public_student_registration_and_login_still_work(): void
    {
        $data = $this->registrationData();
        $this->postJson('/api/v1/auth/register', $data)
            ->assertOk()->assertJsonPath('user.roles.0.name', 'student');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => $data['email'], 'password' => $data['password']])
            ->assertOk()->assertJsonStructure(['access_token', 'user']);
    }

    public function test_manager_operations_and_shared_read_permissions_are_preserved(): void
    {
        $manager = $this->makeUser('manager');
        $this->asUser($manager);
        $this->postJson('/api/v1/courses', ['title' => 'Authorized course', 'level' => 'Beginner'])
            ->assertOk()->assertJsonPath('status', true);
        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/users')->assertOk();
        foreach (['student', 'teacher'] as $role) {
            $this->asUser($this->makeUser($role));
            $this->getJson('/api/v1/courses')->assertOk()->assertJsonCount(1, 'data');
            $this->getJson('/api/v1/terms')->assertOk();
        }
    }

    public function test_shared_reads_require_permission_and_direct_permission_does_not_grant_manager_role(): void
    {
        $student = $this->makeUser('student');
        $student->roles->first()->revokePermissionTo('view course');
        $student->givePermissionTo('create course');
        $this->asUser($student);
        $this->getJson('/api/v1/courses')->assertForbidden();
        $this->postJson('/api/v1/courses', ['title' => 'Forbidden', 'level' => 'Beginner'])->assertForbidden();
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_users_can_read_and_update_only_their_own_account(): void
    {
        $user = $this->makeUser('student');
        $other = $this->makeUser('student');
        $this->asUser($user);
        $this->getJson('/api/v1/users/'.$user->id)->assertOk();
        $this->getJson('/api/v1/users/'.$other->id)->assertForbidden();
        $this->putJson('/api/v1/users/'.$other->id, ['first_name' => 'Forbidden'])->assertForbidden();
        $this->putJson('/api/v1/users/'.$user->id, [
            'national_id' => $user->national_id, 'first_name' => 'Updated', 'last_name' => $user->last_name,
            'phone' => $user->phone, 'email' => $user->email,
        ])->assertOk();
        $this->assertSame('Updated', $user->fresh()->first_name);
        $this->assertSame('User', $other->fresh()->first_name);
    }

    public function test_teacher_profile_edit_is_limited_to_owner_and_cannot_change_salary(): void
    {
        $teacher = $this->makeTeacher();
        $other = $this->makeTeacher();
        $this->asUser($teacher->user);
        $this->getJson('/api/v1/teachers/'.$other->id)->assertForbidden();
        $this->putJson('/api/v1/teachers/'.$other->id, ['bio' => 'Forbidden'])->assertForbidden();
        $this->putJson('/api/v1/teachers/'.$teacher->id, ['bio' => 'Updated', 'salary' => 999])
            ->assertOk()->assertJsonPath('data.bio', 'Updated');
        $this->assertEquals(100, $teacher->fresh()->salary);
    }

    public function test_student_profile_creation_and_editing_cannot_target_another_user(): void
    {
        $owner = $this->makeUser('student');
        $other = $this->makeUser('student');
        $data = ['father_name' => 'Father', 'mother_name' => 'Mother',
            'father_phone' => '+989123456789', 'mother_phone' => '+989123456788'];
        $this->asUser($owner);
        $this->postJson('/api/v1/students', $data + ['user_id' => $other->id])->assertForbidden();
        $id = $this->postJson('/api/v1/students', $data + ['user_id' => $owner->id])
            ->assertOk()->json('data.id');
        $otherProfile = Student::create(['user_id' => $other->id]);
        $this->getJson('/api/v1/students/'.$id)->assertOk();
        $this->getJson('/api/v1/students/'.$otherProfile->id)->assertForbidden();
        $this->putJson('/api/v1/students/'.$otherProfile->id, $data)->assertForbidden();
        $this->putJson('/api/v1/students/'.$id, $data)->assertOk();
    }

    public function test_teacher_can_read_only_their_own_class_roster_and_registered_students(): void
    {
        [$teacher, $student, $class, $registration] = $this->classroom();
        $otherTeacher = $this->makeTeacher();
        $otherStudent = $this->makeStudent();
        $this->asUser($teacher->user);
        $this->getJson('/api/v1/classes/'.$class->id.'/students')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/classes/'.$class->id.'/students/'.$student->id)->assertOk();
        $this->getJson('/api/v1/classes/'.$class->id.'/students/'.$otherStudent->id)->assertNotFound();
        $this->getJson('/api/v1/students/'.$student->id)->assertOk();
        $this->getJson('/api/v1/students/'.$otherStudent->id)->assertForbidden();
        $this->asUser($otherTeacher->user);
        $this->getJson('/api/v1/classes/'.$class->id.'/students')->assertForbidden();
        $this->getJson($this->registrationPath($class, $registration))->assertForbidden();
        $this->getJson('/api/v1/classes/'.$class->id.'/grades')->assertForbidden();
    }

    public function test_personal_class_and_schedule_routes_enforce_role_profile_and_ownership(): void
    {
        [$teacher, $student, $class] = $this->classroom();
        $otherStudent = $this->makeStudent();
        $otherTeacher = $this->makeTeacher();
        $teacherPath = '/api/v1/me/teaching-terms/'.$class->term_id.'/classes';
        $studentPath = '/api/v1/me/studying-terms/'.$class->term_id.'/classes';
        $this->asUser($teacher->user);
        $this->getJson($teacherPath)->assertOk()->assertJsonCount(1, 'classes');
        $this->getJson($studentPath)->assertForbidden();
        $this->getJson($teacherPath.'/'.$class->id)->assertOk();
        $this->asUser($otherTeacher->user);
        $this->getJson($teacherPath.'/'.$class->id)->assertForbidden();
        $this->asUser($student->user);
        $this->getJson($studentPath)->assertOk()->assertJsonCount(1, 'classes');
        $this->getJson($studentPath.'/'.$class->id)->assertOk();
        $this->getJson('/api/v1/me/teaching-terms/'.$class->term_id.'/schedules')->assertForbidden();
        $this->asUser($otherStudent->user);
        $this->getJson($studentPath)->assertOk()->assertJsonCount(0, 'classes');
        $this->getJson($studentPath.'/'.$class->id)->assertForbidden();
        $this->asUser($this->makeUser('student'));
        $this->getJson($studentPath)->assertForbidden();
        $this->getJson('/api/v1/me/students/debts')->assertForbidden();
        $this->postJson('/api/v1/terms/'.$class->term_id.'/classes/'.$class->id.'/registrations')->assertForbidden();
        $this->asUser($this->makeUser('teacher'));
        $this->getJson($teacherPath)->assertForbidden();
    }

    public function test_registration_delete_requires_owner_and_matching_url_parents(): void
    {
        [$teacher, $student, $class, $registration] = $this->classroom();
        $otherStudent = $this->makeStudent();
        $path = $this->registrationPath($class, $registration);
        $this->asUser($otherStudent->user);
        $this->getJson($path)->assertForbidden();
        $this->deleteJson($path)->assertForbidden();
        $this->asUser($teacher->user);
        $this->getJson($path)->assertOk();
        $this->deleteJson($path)->assertForbidden();
        $this->asUser($student->user);
        $this->getJson($path)->assertOk();
        $this->deleteJson(str_replace('/terms/'.$class->term_id.'/', '/terms/999/', $path))->assertNotFound();
        $this->deleteJson(str_replace('/classes/'.$class->id.'/', '/classes/999/', $path))->assertNotFound();
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        $this->deleteJson($path)->assertOk();
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
    }

    public function test_manager_cannot_delete_a_registration_through_wrong_parents(): void
    {
        [, , $class, $registration] = $this->classroom();
        $this->asUser($this->makeUser('manager'));
        $this->deleteJson(str_replace('/terms/'.$class->term_id.'/', '/terms/999/', $this->registrationPath($class, $registration)))
            ->assertNotFound();
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        $this->deleteJson($this->registrationPath($class, $registration))->assertOk();
    }

    public function test_grade_writes_require_assigned_teacher_and_validate_entire_batch_membership(): void
    {
        [$teacher, $student, $class] = $this->classroom();
        $otherTeacher = $this->makeTeacher();
        $otherStudent = $this->makeStudent();
        $path = '/api/v1/classes/'.$class->id.'/grades';
        $valid = ['student_id' => $student->id, 'grade' => 18];
        $this->asUser($student->user);
        $this->postJson($path, $valid)->assertForbidden();
        $this->asUser($otherTeacher->user);
        $this->postJson($path, $valid)->assertForbidden();
        $this->postJson($path.'/batch', ['grades' => [$valid]])->assertForbidden();
        $this->assertDatabaseCount('grades', 0);
        $this->asUser($teacher->user);
        $this->postJson($path, ['student_id' => $otherStudent->id, 'grade' => 17])->assertUnprocessable();
        $this->postJson($path.'/batch', ['grades' => [$valid, ['student_id' => $otherStudent->id, 'grade' => 17]]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('grades', 0);
        $this->postJson($path, $valid)->assertOk();
        $this->postJson($path.'/batch', ['grades' => [['student_id' => $student->id, 'grade' => 19]]])->assertOk();
        $this->assertDatabaseHas('grades', ['student_id' => $student->id, 'class_id' => $class->id, 'grade' => 19]);
        $this->getJson($path)->assertOk()->assertJsonCount(1, 'grades');
        $this->asUser($student->user);
        $this->getJson('/api/v1/me/classes/'.$class->id.'/grades')->assertOk();
        $this->asUser($otherStudent->user);
        $this->getJson('/api/v1/me/classes/'.$class->id.'/grades')->assertForbidden();
    }

    public function test_financial_access_is_scoped_without_modifying_the_existing_financial_schema(): void
    {
        [$teacher, $student, $class, $registration] = $this->classroom();
        $otherStudent = $this->makeStudent();
        // These are the actual migrated columns. Financial schema repair is a later stage.
        $debtId = DB::table('debts')->insertGetId([
            'registration_id' => $registration->id, 'amount' => 100, 'remaining' => 80,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payment = Payment::forceCreate(['debt_id' => $debtId, 'amount' => 20, 'paid_at' => now()->toDateString()]);
        $debt = Debt::findOrFail($debtId);
        $this->assertTrue(Gate::forUser($student->user)->allows('pay', $debt));
        $this->assertFalse(Gate::forUser($otherStudent->user)->allows('pay', $debt));
        $this->asUser($otherStudent->user);
        $this->getJson('/api/v1/payments/'.$payment->id)->assertForbidden();
        $this->postJson('/api/v1/debts/'.$debtId.'/payments', ['amount' => 10])->assertForbidden();
        $this->asUser($teacher->user);
        $this->getJson('/api/v1/payments/'.$payment->id)->assertForbidden();
        $this->postJson('/api/v1/debts/'.$debtId.'/payments', ['amount' => 10])->assertForbidden();
        $this->asUser($student->user);
        $this->getJson('/api/v1/payments/'.$payment->id)->assertOk()->assertJsonPath('data.id', $payment->id);
        $this->asUser($this->makeUser('manager'));
        $this->getJson('/api/v1/payments/'.$payment->id)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('debts', ['id' => $debtId, 'remaining' => 80]);
    }

    private function makeUser(string $role): User
    {
        $identity = $this->identity++;
        $user = User::create([
            'first_name' => 'User', 'last_name' => 'Test',
            'email' => 'user'.$identity.'@example.test', 'national_id' => (string) (1000000000 + $identity),
            'phone' => '+989'.str_pad((string) $identity, 9, '0', STR_PAD_LEFT), 'password' => 'password123',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeTeacher(): Teacher
    {
        return Teacher::create(['user_id' => $this->makeUser('teacher')->id, 'salary' => 100]);
    }

    private function makeStudent(): Student
    {
        return Student::create(['user_id' => $this->makeUser('student')->id]);
    }

    private function asUser(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $token = auth('api')->login($user);
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
    }

    private function classroom(): array
    {
        $teacher = $this->makeTeacher();
        $student = $this->makeStudent();
        $term = Term::create(['year' => 1405, 'season' => 'fall', 'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(), 'is_active' => true]);
        $course = Course::create(['title' => 'English', 'level' => 'Beginner']);
        $class = ClassModel::create([
            'teacher_id' => $teacher->id, 'course_id' => $course->id, 'term_id' => $term->id,
            'name' => 'Test class', 'capacity' => 10, 'tuition_fee' => 100,
            'start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString(),
            'startRegistration_Date' => now()->subDay()->toDateString(),
            'endRegistration_Date' => now()->addWeek()->toDateString(),
        ]);
        $registration = Registration::create(['student_id' => $student->id, 'class_id' => $class->id]);

        return [$teacher, $student, $class, $registration];
    }

    private function registrationPath(ClassModel $class, Registration $registration): string
    {
        return '/api/v1/terms/'.$class->term_id.'/classes/'.$class->id.'/registrations/'.$registration->id;
    }

    private function registrationData(): array
    {
        return ['national_id' => '1234567890', 'first_name' => 'New', 'last_name' => 'User',
            'email' => 'new@example.test', 'phone' => '+989123456789',
            'password' => 'password123', 'password_confirmation' => 'password123'];
    }
}
