<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\EnrollmentConflict;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HandlesEnrollmentResponses
{
    protected function enrollmentResponse(callable $action)
    {
        try {
            return $action();
        } catch (EnrollmentConflict $e) {
            return response()->json(['status' => false, 'code' => $e->reason, 'message' => $e->getMessage()], 409);
        } catch (ModelNotFoundException | ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status' => false, 'message' => 'The academy request could not be completed.'], 500);
        }
    }
}
