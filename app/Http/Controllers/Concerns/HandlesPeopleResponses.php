<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HandlesPeopleResponses
{
    protected function peopleResponse(callable $action)
    {
        try {
            return $action();
        } catch (ModelNotFoundException | ValidationException | AuthorizationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            return response()->json(['status' => false, 'message' => 'Unable to process the account or profile request.'], 500);
        }
    }
}
