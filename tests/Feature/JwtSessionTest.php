<?php

namespace Tests\Feature;

use App\Services\AccountService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JwtSessionTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
            'jwt.secret' => 'academy-session-tests-only-0123456789abcdef',
            'jwt.blacklist_enabled' => true,
            'jwt.blacklist_grace_period' => 0,
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_valid_refresh_rotates_token_and_invalidates_the_previous_token(): void
    {
        [$user, $token] = $this->account();
        $new = $this->using($token)->postJson('/api/v1/auth/refresh')->assertOk()
            ->assertJsonPath('user.id', $user->id)->assertJsonPath('user.roles.0.name', 'student')
            ->assertJsonPath('user.student.user_id', $user->id)->json('access_token');
        $this->assertNotSame($token, $new);
        $this->using($token)->getJson('/api/v1/users/'.$user->id)->assertUnauthorized();
        $this->using($new)->getJson('/api/v1/users/'.$user->id)->assertOk();
    }

    public function test_expired_token_can_refresh_inside_the_refresh_window(): void
    {
        [$user, $token] = $this->account();
        $this->travel(61)->minutes();
        $this->using($token)->getJson('/api/v1/users/'.$user->id)->assertUnauthorized();
        $new = $this->using($token)->postJson('/api/v1/auth/refresh')->assertOk()->json('access_token');
        $this->using($new)->getJson('/api/v1/users/'.$user->id)->assertOk();
    }

    public function test_refresh_window_expiration_returns_401_without_exception_details(): void
    {
        [, $token] = $this->account();
        $this->travel(15)->days();
        $this->using($token)->postJson('/api/v1/auth/refresh')->assertUnauthorized()->assertJsonMissingPath('error');
    }

    public function test_missing_and_malformed_refresh_tokens_are_authentication_errors(): void
    {
        $this->postJson('/api/v1/auth/refresh')->assertUnauthorized()->assertJsonMissingPath('error');
        $this->using('invalid-token')->postJson('/api/v1/auth/refresh')->assertUnauthorized()->assertJsonMissingPath('error');
    }

    public function test_bad_signature_is_rejected_instead_of_returning_server_error(): void
    {
        [, $token] = $this->account();
        $invalid = substr($token, 0, -8).'modified';
        $this->using($invalid)->postJson('/api/v1/auth/refresh')->assertUnauthorized()->assertJsonMissingPath('error');
    }

    public function test_logout_invalidates_access_and_refresh(): void
    {
        [$user, $token] = $this->account();
        $this->using($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->using($token)->getJson('/api/v1/users/'.$user->id)->assertUnauthorized();
        $this->using($token)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
        $this->using($token)->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_refresh_then_logout_revokes_an_expired_session(): void
    {
        [, $token] = $this->account();
        $this->travel(61)->minutes();
        $this->using($token)->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $new = $this->using($token)->postJson('/api/v1/auth/refresh')->assertOk()->json('access_token');
        $this->using($new)->postJson('/api/v1/auth/logout')->assertOk();
        $this->using($new)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
    }

    public function test_deleted_account_cannot_be_refreshed(): void
    {
        [$user, $token] = $this->account();
        app(AccountService::class)->deleteUser($user);
        $this->using($token)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
    }

    public function test_wrong_credentials_return_401_without_creating_a_new_session(): void
    {
        [$user] = $this->account();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnauthorized()->assertJsonMissingPath('access_token');
    }

    public function test_wrong_current_password_is_a_422_field_error_and_keeps_the_session_valid(): void
    {
        [$user, $token] = $this->account();
        $this->using($token)->postJson('/api/v1/changepassword', [
            'current_password' => 'wrong-password', 'password' => 'new-password123', 'password_confirmation' => 'new-password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->using($token)->getJson('/api/v1/users/'.$user->id)->assertOk();
    }

    public function test_password_change_uses_only_the_authenticated_user_and_hashes_the_new_password(): void
    {
        [$user, $token] = $this->account();
        $other = app(AccountService::class)->create([
            'national_id' => '1234567891', 'first_name' => 'Other', 'last_name' => 'User',
            'phone' => '+989123456788', 'email' => 'other@example.test', 'password' => 'password123',
        ], 'student');
        $this->using($token)->postJson('/api/v1/changepassword', [
            'id' => $other->id, 'current_password' => 'password123',
            'password' => 'new-password123', 'password_confirmation' => 'new-password123',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertTrue(Hash::check('password123', $other->fresh()->password));
        $this->using($token)->getJson('/api/v1/users/'.$user->id)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'new-password123'])->assertOk();
    }

    private function account(): array
    {
        $user = app(AccountService::class)->create([
            'national_id' => '1234567890', 'first_name' => 'Session', 'last_name' => 'User',
            'phone' => '+989123456789', 'email' => 'session@example.test', 'password' => 'password123',
        ], 'student');
        $token = auth('api')->login($user);
        $this->app['auth']->forgetGuards();

        return [$user, $token];
    }

    private function using(string $token): static
    {
        // Feature requests share the container; real PHP requests do not share these cached tokens.
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->unsetToken();
        $this->app['tymon.jwt.manager']->setRefreshFlow(false);
        $this->app['auth']->forgetGuards();
        $this->withToken($token);

        return $this;
    }
}
