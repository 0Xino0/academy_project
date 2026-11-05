<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AccountService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\TeacherSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private int $identity = 1;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
            'jwt.secret' => 'academy-account-tests-only-0123456789abcdef',
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_creates_a_usable_student_account_with_a_hashed_password(): void
    {
        $data = $this->accountData();
        $response = $this->postJson('/api/v1/auth/register', $data + ['role' => 'manager'])
            ->assertOk()->assertJsonPath('user.roles.0.name', 'student');
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertFalse($user->hasRole('manager'));
        $response->assertJsonPath('user.student.user_id', $user->id)
            ->assertJsonPath('user.student.father_name', null)
            ->assertJsonPath('user.teacher', null)->assertJsonMissingPath('user.password');
        $this->assertDatabaseCount('students', 1);
        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('access_token'))
            ->getJson('/api/v1/me/studying-terms/1/classes')->assertOk()->assertJsonCount(0, 'classes');
    }

    public function test_teacher_creation_preserves_the_existing_response_and_creates_exactly_one_profile(): void
    {
        $this->asManager();
        $data = $this->accountData();
        $response = $this->postJson('/api/v1/users', $data)->assertOk()->assertJsonPath('role.0', 'teacher');
        $user = User::where('email', $data['email'])->firstOrFail();
        $response->assertJsonPath('data.id', $user->id)->assertJsonPath('teacher.user_id', $user->id);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertDatabaseCount('teachers', 1);
        $this->assertDatabaseCount('students', 0);
        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_manager_registration_does_not_create_an_unrelated_student_or_teacher_profile(): void
    {
        $this->asManager();
        $this->postJson('/api/v1/auth/registerAdmin', $this->accountData())
            ->assertOk()->assertJsonPath('user.roles.0.name', 'manager')
            ->assertJsonPath('user.student', null)->assertJsonPath('user.teacher', null);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('teachers', 0);
    }

    public static function duplicateRegistrationFields(): iterable
    {
        foreach (['auth/register', 'users', 'auth/registerAdmin'] as $path) {
            foreach (['email', 'phone', 'national_id'] as $field) {
                yield "$path $field" => [$path, $field];
            }
        }
    }

    /** @dataProvider duplicateRegistrationFields */
    public function test_duplicate_identity_returns_validation_error_without_creating_partial_accounts(string $path, string $field): void
    {
        $existing = $this->makeUser('student');
        if ($path !== 'auth/register') {
            $this->asManager();
        }
        $before = User::count();
        $data = $this->accountData();
        $data[$field] = $existing->{$field};
        $this->postJson('/api/v1/'.$path, $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('users', $before);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_national_id_with_leading_zeroes_is_preserved(): void
    {
        $data = $this->accountData();
        $data['national_id'] = '0012345678';
        $this->postJson('/api/v1/auth/register', $data)->assertOk()->assertJsonPath('user.national_id', '0012345678');
    }

    public function test_user_update_rejects_another_users_phone_but_accepts_its_own_phone(): void
    {
        $user = $this->makeUser('student');
        $other = $this->makeUser('student');
        $this->asUser($user);
        $data = $user->only(['national_id', 'first_name', 'last_name', 'phone', 'email']);
        $this->putJson('/api/v1/users/'.$user->id, array_replace($data, ['phone' => $other->phone]))
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame($data['phone'], $user->fresh()->phone);
        $this->putJson('/api/v1/users/'.$user->id, $data)->assertOk();
    }

    public static function profileTypes(): iterable
    {
        yield 'student' => ['student', Student::class, 'students', 'auth/register'];
        yield 'teacher' => ['teacher', Teacher::class, 'teachers', 'users'];
    }

    /** @dataProvider profileTypes */
    public function test_profile_creation_failure_rolls_back_user_and_role(string $role, string $model, string $table, string $path): void
    {
        if ($role === 'teacher') {
            $this->asManager();
        }
        $usersBefore = User::count();
        $rolesBefore = DB::table('model_has_roles')->count();
        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(clone $dispatcher);
        $model::creating(function () {
            throw new RuntimeException('Simulated profile storage failure.');
        });

        try {
            $this->postJson('/api/v1/'.$path, $this->accountData())->assertStatus(500);
            $this->assertDatabaseCount('users', $usersBefore);
            $this->assertDatabaseCount('model_has_roles', $rolesBefore);
            $this->assertDatabaseCount($table, 0);
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
    }

    public function test_missing_role_rolls_back_the_new_user(): void
    {
        Role::findByName('student', 'api')->delete();
        $this->postJson('/api/v1/auth/register', $this->accountData())->assertStatus(500);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_token_signing_failure_also_rolls_back_the_account_and_profile(): void
    {
        config(['jwt.secret' => null]);
        $this->postJson('/api/v1/auth/register', $this->accountData())->assertStatus(500);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_manual_student_profile_requires_the_student_role(): void
    {
        $teacher = $this->makeUser('teacher');
        $this->asManager();
        $this->postJson('/api/v1/students', $this->parentData() + ['user_id' => $teacher->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_existing_auto_created_profile_is_updated_instead_of_duplicated(): void
    {
        $user = app(AccountService::class)->create($this->accountData(), 'student');
        $profile = $user->student;
        $this->asUser($user);
        $this->postJson('/api/v1/students', $this->parentData() + ['user_id' => $user->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->putJson('/api/v1/students/'.$profile->id, $this->parentData())->assertOk();
        $this->assertDatabaseCount('students', 1);
        $this->assertSame('Father', $profile->fresh()->father_name);
    }

    /** @dataProvider profileTypes */
    public function test_database_rejects_duplicate_profiles_even_after_soft_deletion(string $role, string $model, string $table): void
    {
        $user = app(AccountService::class)->create($this->accountData(), $role);
        $profile = $user->{$role};
        $profile->delete();

        try {
            $model::create(['user_id' => $user->id]);
            $this->fail('A duplicate profile was accepted by the database.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount($table, 1);
        }
    }

    /** @dataProvider profileTypes */
    public function test_failed_profile_deletion_preserves_the_role_and_profile(string $role, string $model, string $table): void
    {
        $user = app(AccountService::class)->create($this->accountData(), $role);
        $profile = $user->{$role};
        $this->asManager();
        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(clone $dispatcher);
        $model::deleting(fn () => false);

        try {
            $this->deleteJson('/api/v1/'.$table.'/'.$profile->id)->assertStatus(500);
            $this->assertTrue($user->fresh()->hasRole($role));
            $this->assertDatabaseHas($table, ['id' => $profile->id, 'deleted_at' => null]);
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
    }

    /** @dataProvider profileTypes */
    public function test_successful_profile_deletion_removes_only_its_own_role(string $role, string $model, string $table): void
    {
        $user = app(AccountService::class)->create($this->accountData(), $role);
        $user->assignRole('manager');
        $profile = $user->{$role};
        $this->asManager();
        $this->deleteJson('/api/v1/'.$table.'/'.$profile->id)->assertOk();
        $this->assertSoftDeleted($table, ['id' => $profile->id]);
        $this->assertFalse($user->fresh()->hasRole($role));
        $this->assertTrue($user->fresh()->hasRole('manager'));
    }

    public function test_failed_user_deletion_does_not_detach_its_roles_or_delete_profiles(): void
    {
        $user = app(AccountService::class)->create($this->accountData(), 'student');
        $this->asManager();
        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(clone $dispatcher);
        User::deleting(fn () => false);

        try {
            $this->deleteJson('/api/v1/users/'.$user->id)->assertStatus(500);
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertTrue($user->fresh()->hasRole('student'));
            $this->assertDatabaseHas('students', ['user_id' => $user->id]);
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
    }

    public function test_backfill_repairs_missing_profiles_without_restoring_deleted_or_overwriting_existing_ones(): void
    {
        $student = $this->makeUser('student');
        $teacher = $this->makeUser('teacher');
        $manager = $this->makeUser('manager');
        $existing = $this->makeUser('student');
        Student::create(['user_id' => $existing->id, 'father_name' => 'Keep me']);
        $deleted = $this->makeUser('student');
        Student::create(['user_id' => $deleted->id])->delete();
        $migration = require database_path('migrations/2026_11_05_000001_backfill_missing_role_profiles.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('students', ['user_id' => $student->id, 'father_name' => null]);
        $this->assertDatabaseHas('teachers', ['user_id' => $teacher->id]);
        $this->assertDatabaseHas('students', ['user_id' => $existing->id, 'father_name' => 'Keep me']);
        $this->assertSoftDeleted('students', ['user_id' => $deleted->id]);
        $this->assertDatabaseMissing('students', ['user_id' => $manager->id]);
        $this->assertDatabaseMissing('teachers', ['user_id' => $manager->id]);
        $this->assertDatabaseCount('students', 3);
        $this->assertDatabaseCount('teachers', 1);
        $migration->down();
        $this->assertDatabaseHas('students', ['user_id' => $student->id]);
    }

    public function test_unique_profile_migration_stops_before_changes_when_legacy_duplicates_exist(): void
    {
        $migration = require database_path('migrations/2026_11_05_000000_enforce_unique_user_profiles.php');
        $migration->down();
        $user = $this->makeUser('student');
        Student::create(['user_id' => $user->id]);
        Student::create(['user_id' => $user->id]);

        try {
            $migration->up();
            $this->fail('The migration should reject duplicate profiles.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('students', $exception->getMessage());
            $this->assertDatabaseCount('students', 2);
            Teacher::create(['user_id' => $user->id]);
            Teacher::create(['user_id' => $user->id]);
            $this->assertDatabaseCount('teachers', 2);
        }
    }

    public function test_profile_seeders_do_not_recreate_soft_deleted_profiles(): void
    {
        $student = $this->makeUser('student');
        $teacher = $this->makeUser('teacher');
        Student::create(['user_id' => $student->id])->delete();
        Teacher::create(['user_id' => $teacher->id])->delete();
        $this->seed([StudentSeeder::class, TeacherSeeder::class]);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('teachers', 1);
        $this->assertSoftDeleted('students', ['user_id' => $student->id]);
        $this->assertSoftDeleted('teachers', ['user_id' => $teacher->id]);
    }

    private function accountData(): array
    {
        $id = $this->identity++;

        return ['national_id' => (string) (1000000000 + $id), 'first_name' => 'Account', 'last_name' => 'Test',
            'email' => 'account'.$id.'@example.test', 'phone' => '+989'.str_pad((string) $id, 9, '0', STR_PAD_LEFT),
            'password' => 'password123', 'password_confirmation' => 'password123'];
    }

    private function parentData(): array
    {
        return ['father_name' => 'Father', 'mother_name' => 'Mother',
            'father_phone' => '+989123456789', 'mother_phone' => '+989123456788'];
    }

    private function makeUser(string $role): User
    {
        $user = User::create($this->accountData());
        $user->assignRole($role);

        return $user;
    }

    private function asManager(): void
    {
        $this->asUser($this->makeUser('manager'));
    }

    private function asUser(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $token = auth('api')->login($user);
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
    }
}
