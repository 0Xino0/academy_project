<?php

namespace Tests\Feature\AccessControl;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AccessControlService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Feature\Academics\AcademicTestCase;

class AccessControlApiTest extends AcademicTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->account('manager'), 'api');
    }

    public function test_custom_role_crud_keeps_api_guard_and_logical_history(): void
    {
        $created = $this->postJson('/api/v1/roles', [
            'name' => '  assistant  ', 'id' => 9000, 'deleted_at' => now(), 'permissions' => ['view course'],
        ])->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.name', 'assistant')
            ->assertJsonPath('data.guard_name', 'api');
        $id = $created->json('data.id');
        $this->assertSame($id, $created->json('role.id'));
        $this->assertNotSame(9000, $id);
        $this->getJson('/api/v1/roles/'.$id)->assertOk()->assertJsonPath('data.deleted_at', null);
        $this->putJson('/api/v1/roles/'.$id, ['name' => 'assistant-renamed'])
            ->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.name', 'assistant-renamed');
        $owner = $this->account('student');
        $owner->assignRole(Role::findOrFail($id));
        $pivots = DB::table('model_has_roles')->count();
        $this->deleteJson('/api/v1/roles/'.$id)->assertOk()->assertJsonPath('status', true);
        $this->assertSoftDeleted('roles', ['id' => $id]);
        $this->assertSame($pivots, DB::table('model_has_roles')->count());
        $this->assertFalse($owner->fresh()->hasRole('assistant-renamed'));
        $this->getJson('/api/v1/roles/'.$id)->assertNotFound()->assertJsonPath('status', false);
        $this->deleteJson('/api/v1/roles/'.$id)->assertNotFound();
    }

    public function test_permission_updates_and_archive_invalidate_warmed_authorization_cache(): void
    {
        $created = $this->postJson('/api/v1/permissions', ['name' => 'review lesson'])
            ->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.guard_name', 'api');
        $id = $created->json('data.id');
        $role = Role::create(['name' => 'reviewer', 'guard_name' => 'api']);
        $role->givePermissionTo(Permission::findOrFail($id));
        $owner = $this->account('student');
        $owner->assignRole($role);
        $this->assertTrue($owner->fresh()->can('review lesson'));
        $this->putJson('/api/v1/permissions/'.$id, ['name' => 'review lesson renamed'])
            ->assertOk()->assertJsonPath('data.name', 'review lesson renamed');
        $this->assertFalse($owner->fresh()->can('review lesson'));
        $this->assertTrue($owner->fresh()->can('review lesson renamed'));
        $pivots = DB::table('role_has_permissions')->count();
        $this->deleteJson('/api/v1/permissions/'.$id)->assertOk()->assertJsonPath('status', true);
        $this->assertSoftDeleted('permissions', ['id' => $id]);
        $this->assertSame($pivots, DB::table('role_has_permissions')->count());
        $this->assertFalse($owner->fresh()->can('review lesson renamed'));
        $this->getJson('/api/v1/permissions/'.$id)->assertNotFound();
    }

    public function test_lists_and_role_permissions_exclude_archived_and_other_guard_records(): void
    {
        $role = Role::create(['name' => 'api-role', 'guard_name' => 'api']);
        $permission = Permission::create(['name' => 'api-permission', 'guard_name' => 'api']);
        $role->givePermissionTo($permission);
        $archivedRole = Role::create(['name' => 'old-role', 'guard_name' => 'api']); $archivedRole->delete();
        $archived = Permission::create(['name' => 'old-permission', 'guard_name' => 'api']);
        $role->givePermissionTo($archived); $archived->delete();
        Role::create(['name' => 'web-only-role', 'guard_name' => 'web']);
        Permission::create(['name' => 'web-only-permission', 'guard_name' => 'web']);
        $this->getJson('/api/v1/roles')->assertOk()->assertJsonPath('status', true)
            ->assertJsonMissing(['name' => 'old-role'])->assertJsonMissing(['name' => 'web-only-role']);
        $this->getJson('/api/v1/permissions')->assertOk()->assertJsonPath('status', true)
            ->assertJsonMissing(['name' => 'old-permission'])->assertJsonMissing(['name' => 'web-only-permission']);
        $response = $this->getJson('/api/v1/roles/'.$role->id.'/permissions')->assertOk();
        $this->assertSame([$permission->id], array_column($response->json('permissionsOfRole'), 'id'));
        $this->assertSame($response->json('permissionsOfRole'), $response->json('permissonsOfRole'));
        $response->assertJsonMissing(['name' => 'old-permission'])->assertJsonMissing(['name' => 'web-only-permission']);
    }

    public static function invalidNames(): iterable
    {
        foreach (['roles', 'permissions'] as $resource) {
            foreach ([
                'missing' => [], 'null' => ['name' => null], 'blank' => ['name' => '  '],
                'number' => ['name' => 42], 'array' => ['name' => ['x']],
                'too long' => ['name' => str_repeat('x', 256)],
                'wrong guard' => ['name' => 'valid-name', 'guard_name' => 'web'],
                'array guard' => ['name' => 'valid-name', 'guard_name' => ['api']],
            ] as $label => $payload) yield "$resource $label" => [$resource, $payload];
        }
    }

    /** @dataProvider invalidNames */
    public function test_create_and_update_validate_names_without_changes(string $resource, array $payload): void
    {
        $model = $resource === 'roles' ? Role::class : Permission::class;
        $record = $model::create(['name' => 'keep-name', 'guard_name' => 'api']);
        $count = $model::withTrashed()->count();
        $this->postJson('/api/v1/'.$resource, $payload)->assertUnprocessable();
        $this->putJson('/api/v1/'.$resource.'/'.$record->id, $payload)->assertUnprocessable();
        $this->assertSame($count, $model::withTrashed()->count());
        $this->assertSame('keep-name', $record->fresh()->name);
        $this->assertSame('api', $record->fresh()->guard_name);
    }

    public static function resources(): iterable
    {
        yield 'roles' => ['roles', Role::class];
        yield 'permissions' => ['permissions', Permission::class];
    }

    /** @dataProvider resources */
    public function test_unique_names_include_archived_api_rows_but_are_scoped_to_guard(string $resource, string $model): void
    {
        $row = $model::create(['name' => 'first', 'guard_name' => 'api']);
        $other = $model::create(['name' => 'reserved', 'guard_name' => 'api']);
        $web = $model::create(['name' => 'web-name', 'guard_name' => 'web']);
        $this->putJson('/api/v1/'.$resource.'/'.$row->id, ['name' => 'first'])->assertOk();
        foreach ([false, true] as $archive) {
            if ($archive) $other->delete();
            $this->postJson('/api/v1/'.$resource, ['name' => 'reserved'])->assertUnprocessable()->assertJsonValidationErrors('name');
            $this->putJson('/api/v1/'.$resource.'/'.$row->id, ['name' => 'reserved'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/'.$resource, ['name' => 'web-name'])->assertOk()->assertJsonPath('data.guard_name', 'api');
        $this->getJson('/api/v1/'.$resource.'/'.$web->id)->assertNotFound();
        $this->putJson('/api/v1/'.$resource.'/'.$web->id, ['name' => 'renamed-web'])->assertNotFound();
        $this->deleteJson('/api/v1/'.$resource.'/'.$web->id)->assertNotFound();
        $this->assertNull($web->fresh()->deleted_at);
        $this->assertSame('web-name', $web->fresh()->name);
        $this->assertSame('first', $row->fresh()->name);
    }

    public function test_missing_or_invalid_route_ids_return_json_404_without_query_details(): void
    {
        foreach (['999999', 'not-an-id', '9223372036854775808', '0', '-1'] as $id) {
            foreach (['roles', 'permissions'] as $resource) {
                $this->getJson('/api/v1/'.$resource.'/'.$id)->assertNotFound()->assertExactJson([
                    'status' => false, 'message' => 'Access control record not found.',
                ]);
                $this->putJson('/api/v1/'.$resource.'/'.$id, ['name' => 'valid-new-name'])->assertNotFound();
                $this->deleteJson('/api/v1/'.$resource.'/'.$id)->assertNotFound();
            }
            $this->getJson('/api/v1/roles/'.$id.'/permissions')->assertNotFound();
            foreach (['PUT', 'DELETE'] as $method) {
                $this->json($method, '/api/v1/roles/'.$id.'/permissions', ['permissions' => []])->assertNotFound();
            }
        }
    }

    public static function malformedLists(): iterable
    {
        foreach ([
            'missing' => [], 'null' => ['permissions' => null],
            'string' => ['permissions' => 'view course'], 'number' => ['permissions' => 1],
            'bool' => ['permissions' => true], 'associative' => ['permissions' => ['a' => 'view course']],
            'empty object' => ['permissions' => (object) []],
            'indexed object' => ['permissions' => (object) ['0' => 'view course']],
            'nested' => ['permissions' => [['view course']]],
            'null member' => ['permissions' => [null]], 'boolean member' => ['permissions' => [true]],
            'zero' => ['permissions' => [0]], 'negative' => ['permissions' => [-1]],
            'float' => ['permissions' => [1.5]], 'blank' => ['permissions' => ['  ']],
            'long name' => ['permissions' => [str_repeat('x', 256)]],
        ] as $label => $payload) yield $label => [$payload];
    }

    /** @dataProvider malformedLists */
    public function test_permission_payload_must_be_a_flat_list_and_leaves_assignments_intact(array $payload): void
    {
        $role = Role::create(['name' => 'keep-role', 'guard_name' => 'api']);
        $permission = Permission::findByName('view course', 'api'); $role->givePermissionTo($permission);
        foreach (['PUT', 'DELETE'] as $method) {
            $this->json($method, '/api/v1/roles/'.$role->id.'/permissions', $payload)->assertUnprocessable();
            $this->assertSame([$permission->id], $role->fresh()->permissions->pluck('id')->all());
        }
    }

    public static function unavailableLists(): iterable
    {
        foreach (['missing id', 'missing name', 'archived id', 'archived name', 'foreign id', 'foreign name',
            'duplicate id', 'duplicate name', 'duplicate alias', 'numeric string'] as $case) yield $case => [$case];
    }

    /** @dataProvider unavailableLists */
    public function test_entire_selection_is_resolved_before_any_sync_or_revoke(string $case): void
    {
        $role = Role::create(['name' => 'keep-role', 'guard_name' => 'api']);
        $kept = Permission::findByName('view course', 'api'); $role->givePermissionTo($kept);
        $archived = Permission::create(['name' => 'archived-choice', 'guard_name' => 'api']); $archived->delete();
        $foreign = Permission::create(['name' => 'web-choice', 'guard_name' => 'web']);
        $invalid = match ($case) {
            'missing id' => 999999, 'missing name' => 'missing-action',
            'archived id' => $archived->id, 'archived name' => $archived->name,
            'foreign id' => $foreign->id, 'foreign name' => $foreign->name,
            'duplicate id', 'duplicate alias' => $kept->id,
            'duplicate name' => $kept->name, 'numeric string' => (string) $kept->id,
        };
        $first = $case === 'duplicate alias' ? $kept->name : ($case === 'duplicate name' ? $kept->name : $kept->id);
        foreach (['PUT', 'DELETE'] as $method) {
            $this->json($method, '/api/v1/roles/'.$role->id.'/permissions', ['permissions' => [$first, $invalid]])
                ->assertUnprocessable()->assertJsonValidationErrors('permissions.1');
            $this->assertSame([$kept->id], $role->fresh()->permissions->pluck('id')->all());
        }
    }

    public function test_replace_and_revoke_accept_ids_and_names_and_allow_explicit_empty_lists(): void
    {
        $role = Role::create(['name' => 'review-role', 'guard_name' => 'api']);
        $old = Permission::findByName('view course', 'api'); $role->givePermissionTo($old);
        $first = Permission::findByName('view term', 'api');
        $second = Permission::findByName('view schedule', 'api');
        $owner = $this->account('student'); $owner->syncRoles([$role]);
        $this->assertTrue($owner->fresh()->can($old->name));
        $url = '/api/v1/roles/'.$role->id.'/permissions';
        $this->putJson($url, ['permissions' => [$first->id, $second->name]])->assertOk()
            ->assertJsonPath('status', true)->assertJsonCount(2, 'permissions');
        $this->assertFalse($owner->fresh()->can($old->name));
        $this->assertTrue($owner->fresh()->can($first->name));
        $this->deleteJson($url, ['permissions' => []])->assertOk()->assertJsonCount(2, 'permissions');
        $this->deleteJson($url, ['permissions' => [$first->name, $old->id]])->assertOk()->assertJsonCount(1, 'permissions');
        $this->assertFalse($owner->fresh()->can($first->name));
        $this->assertTrue($owner->fresh()->can($second->name));
        $this->deleteJson($url, ['permissions' => [$first->id]])->assertOk()->assertJsonCount(1, 'permissions');
        $this->putJson($url, ['permissions' => []])->assertOk()->assertJsonCount(0, 'permissions');
        $this->assertFalse($owner->fresh()->can($second->name));
        $this->putJson($url, ['permissions' => []])->assertOk()->assertJsonCount(0, 'permissions');
    }

    public static function vetoes(): iterable
    {
        foreach (self::resources() as [$resource, $model]) {
            foreach (['create', 'update', 'delete'] as $operation) yield "$resource $operation" => [$resource, $model, $operation];
        }
    }

    /** @dataProvider vetoes */
    public function test_model_veto_returns_generic_500_and_keeps_database_state(string $resource, string $model, string $operation): void
    {
        $record = $model::create(['name' => 'keep-record', 'guard_name' => 'api']);
        $before = $model::withTrashed()->count();
        $event = 'eloquent.'.match ($operation) { 'create' => 'creating', 'update' => 'updating', 'delete' => 'deleting' }.': '.$model;
        Event::listen($event, fn () => false);
        try {
            $response = match ($operation) {
                'create' => $this->postJson('/api/v1/'.$resource, ['name' => 'new-record']),
                'update' => $this->putJson('/api/v1/'.$resource.'/'.$record->id, ['name' => 'new-record']),
                'delete' => $this->deleteJson('/api/v1/'.$resource.'/'.$record->id),
            };
            $response->assertStatus(500)->assertExactJson([
                'status' => false, 'message' => 'Unable to process the access control request.',
            ]);
            $this->assertSame($before, $model::withTrashed()->count());
            $this->assertSame('keep-record', $record->fresh()->name);
            $this->assertNull($record->fresh()->deleted_at);
        } finally {
            Event::forget($event);
        }
    }

    /** @dataProvider resources */
    public function test_database_duplicate_after_validation_is_still_422_and_rolled_back(string $resource, string $model): void
    {
        $table = (new $model)->getTable();
        $count = DB::table($table)->count();
        $event = 'eloquent.creating: '.$model;
        Event::listen($event, function ($record) use ($table) {
            if ($record->name === 'race-name') DB::table($table)->insert(['name' => 'race-name', 'guard_name' => 'api']);
        });
        try {
            $this->postJson('/api/v1/'.$resource, ['name' => 'race-name'])->assertUnprocessable()->assertJsonValidationErrors('name');
            $this->assertSame($count, DB::table($table)->count());
        } finally {
            Event::forget($event);
        }
    }

    public static function pivotFailures(): iterable
    {
        yield 'sync insertion' => [false];
        yield 'revoke deletion' => [true];
    }

    /** @dataProvider pivotFailures */
    public function test_mid_operation_database_failure_rolls_back_all_permission_changes(bool $revoke): void
    {
        $role = Role::create(['name' => 'atomic-role', 'guard_name' => 'api']);
        $old = Permission::create(['name' => 'atomic-old', 'guard_name' => 'api']);
        $first = Permission::create(['name' => 'atomic-first', 'guard_name' => 'api']);
        $second = Permission::create(['name' => 'atomic-second', 'guard_name' => 'api']);
        $role->givePermissionTo($revoke ? [$first, $second] : [$old]);
        $before = $role->fresh()->permissions->pluck('id')->sort()->values()->all();
        $operation = $revoke ? 'DELETE' : 'INSERT';
        $row = $revoke ? 'OLD' : 'NEW';
        DB::unprepared("CREATE TRIGGER fail_access_pivot BEFORE $operation ON role_has_permissions
            WHEN $row.role_id = $role->id AND $row.permission_id = $second->id
            BEGIN SELECT RAISE(ABORT, 'sensitive-test-only-storage-error'); END");
        try {
            $this->json($revoke ? 'DELETE' : 'PUT', '/api/v1/roles/'.$role->id.'/permissions',
                ['permissions' => [$first->id, $second->id]])
                ->assertStatus(500)->assertExactJson(['status' => false, 'message' => 'Unable to process the access control request.']);
            $this->assertSame($before, $role->fresh()->permissions->pluck('id')->sort()->values()->all());
        } finally {
            DB::unprepared('DROP TRIGGER fail_access_pivot');
        }
    }

    public function test_read_failure_is_not_a_200_or_a_debug_exception_dump(): void
    {
        config(['app.debug' => true]);
        $this->mock(AccessControlService::class, function (MockInterface $mock) {
            $mock->shouldReceive('roles')->once()->andThrow(new RuntimeException('SQLSTATE secret-storage-details'));
        });
        $this->getJson('/api/v1/roles')->assertStatus(500)->assertExactJson([
            'status' => false, 'message' => 'Unable to process the access control request.',
        ]);
    }

    /** @dataProvider resources */
    public function test_storage_failure_during_validation_also_returns_generic_500(string $resource, string $model): void
    {
        config(['app.debug' => true]);
        $record = $model::create(['name' => 'keep-record', 'guard_name' => 'api']);
        DB::listen(function (QueryExecuted $query) {
            if (str_contains($query->sql, 'count(*) as aggregate') && in_array('storage-failure-name', $query->bindings, true)) {
                throw new RuntimeException('SQLSTATE sensitive-validation-storage-details');
            }
        });
        foreach (['POST', 'PUT'] as $method) {
            $url = '/api/v1/'.$resource.($method === 'PUT' ? '/'.$record->id : '');
            $this->json($method, $url, ['name' => 'storage-failure-name'])->assertStatus(500)->assertExactJson([
                'status' => false, 'message' => 'Unable to process the access control request.',
            ]);
        }
        $this->assertSame('keep-record', $record->fresh()->name);
        $this->assertFalse($model::where('name', 'storage-failure-name')->exists());
    }

    public function test_every_existing_access_endpoint_requires_an_authenticated_manager(): void
    {
        $routes = [
            ['GET', 'roles'], ['POST', 'roles'], ['GET', 'roles/1'], ['PUT', 'roles/1'], ['DELETE', 'roles/1'],
            ['GET', 'permissions'], ['POST', 'permissions'], ['GET', 'permissions/1'], ['PUT', 'permissions/1'], ['DELETE', 'permissions/1'],
            ['GET', 'roles/1/permissions'], ['PUT', 'roles/1/permissions'], ['DELETE', 'roles/1/permissions'],
        ];
        auth('api')->forgetUser();
        foreach ($routes as [$method, $path]) $this->json($method, '/api/v1/'.$path, [])->assertUnauthorized();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($this->account($role), 'api');
            foreach ($routes as [$method, $path]) $this->json($method, '/api/v1/'.$path, [])->assertForbidden();
        }
    }

    public function test_core_role_guards_stay_intact_while_permissions_remain_editable(): void
    {
        foreach (['manager', 'teacher', 'student'] as $name) {
            $role = Role::findByName($name, 'api');
            $this->putJson('/api/v1/roles/'.$role->id, ['name' => $name])->assertOk();
            $this->putJson('/api/v1/roles/'.$role->id, ['name' => $name.'-other'])->assertUnprocessable();
            $this->deleteJson('/api/v1/roles/'.$role->id)->assertUnprocessable();
        }
        $manager = Role::findByName('manager', 'api');
        $this->putJson('/api/v1/roles/'.$manager->id.'/permissions', ['permissions' => []])->assertOk();
        // Role-admin endpoints require the manager role, so managing grants cannot lock the manager out of them.
        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/courses')->assertOk(); // Existing Gate::before gives managers full access.
        $teacher = Role::findByName('teacher', 'api');
        $this->putJson('/api/v1/roles/'.$teacher->id.'/permissions', ['permissions' => []])->assertOk();
        $this->actingAs($this->account('teacher'), 'api');
        $this->getJson('/api/v1/courses')->assertForbidden();
    }
}
