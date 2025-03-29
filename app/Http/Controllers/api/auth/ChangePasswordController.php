<?php

namespace App\Http\Controllers\api\auth;

use App\Customs\Services\PasswordService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\Request;

class ChangePasswordController extends Controller
{
    public function __construct(private PasswordService $service){}
    /**
     * updatePassword method
     */
    public function changeUserPassword(ChangePasswordRequest $request)
    {
        $data = $request->validated();
        $data['id'] = auth()->user()->id;
        return $this->service->changePassword($data);
    }
}
