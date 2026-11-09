<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\Academics\AcademicTestCase;

class ApiExceptionResponseTest extends AcademicTestCase
{
    private const GENERIC_ERROR = ['status' => false, 'message' => 'Server Error'];

    public function test_storage_exception_during_form_request_validation_is_generic_even_in_debug(): void
    {
        config(['app.debug' => true]);
        $this->actingAs($this->account('manager'), 'api');
        $class = $this->classroom();
        DB::connection()->beforeExecuting(function (string $sql) {
            if (str_contains($sql, 'count(*) as aggregate') && str_contains($sql, '"courses"')) {
                throw new RuntimeException('internal validation error marker');
            }
        });

        $this->postJson('/api/v1/terms/'.$class->term_id.'/classes', $class->only([
            'name', 'course_id', 'teacher_id', 'start_date', 'end_date',
            'startRegistration_Date', 'endRegistration_Date', 'capacity', 'tuition_fee',
        ]))->assertStatus(500)->assertExactJson(self::GENERIC_ERROR);
        $this->assertDatabaseCount('classes', 1);
    }

    public function test_authentication_keeps_401_for_guests_and_hides_internal_storage_errors(): void
    {
        config(['app.debug' => true]);
        $this->get('/api/v1/terms')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $token = auth('api')->login($this->account('manager'));
        $this->app['auth']->forgetGuards();
        DB::connection()->beforeExecuting(function (string $sql) {
            if (str_contains($sql, 'from "users"')) {
                throw new RuntimeException('internal user lookup error marker');
            }
        });

        $this->withToken($token)->get('/api/v1/terms')
            ->assertStatus(500)->assertExactJson(self::GENERIC_ERROR);
    }

    public function test_api_http_exception_keeps_its_status_and_retry_header(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/test-unavailable', fn () => abort(503, 'internal service error marker', ['Retry-After' => '60']));

        $this->get('/api/test-unavailable')->assertStatus(503)
            ->assertHeader('Retry-After', '60')->assertExactJson(self::GENERIC_ERROR);
    }
}
