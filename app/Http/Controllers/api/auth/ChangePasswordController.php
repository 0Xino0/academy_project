<?php

namespace App\Http\Controllers\api\auth;

use App\Services\PasswordService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;

class ChangePasswordController extends Controller
{
    public function __construct(private PasswordService $service){}
    /**
     * updatePassword method
     */
    public function changeUserPassword(ChangePasswordRequest $request)
    {
        return $this->service->changePassword($request->user(), $request->validated());
    }
}
