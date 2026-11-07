<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HandlesAccessResponses
{
    protected function accessResponse(callable $action)
    {
        try {
            return $action();
        } catch (ModelNotFoundException $error) {
            return response()->json(['status' => false, 'message' => 'Access control record not found.'], 404);
        } catch (ValidationException | AuthorizationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            return response()->json(['status' => false, 'message' => 'Unable to process the access control request.'], 500);
        }
    }
}
