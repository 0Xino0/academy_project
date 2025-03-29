<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\ChangePasswordController;


Route::post('v1/auth/register', [AuthController::class , 'register']);
Route::post('v1/auth/login', [AuthController::class , 'login']);

Route::middleware('auth')->group(function(){
    Route::post('v1/auth/logout', [AuthController::class,'logout']);
    Route::post('v1/changepassword',[ChangePasswordController::class, 'changeUserPassword']);
});
