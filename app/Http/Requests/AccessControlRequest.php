<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class AccessControlRequest extends FormRequest
{
    public function validateResolved()
    {
        try {
            parent::validateResolved();
        } catch (ValidationException | AuthorizationException | HttpResponseException $error) {
            throw $error;
        } catch (Throwable $error) {
            // Validation can query storage before the controller's response handler runs.
            report($error);
            throw new HttpResponseException(response()->json([
                'status' => false, 'message' => 'Unable to process the access control request.',
            ], 500));
        }
    }
}
