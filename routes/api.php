<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\api\auth\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('v1/auth/register', [AuthController::class , 'register']);
Route::post('v1/auth/login', [AuthController::class , 'login']);

Route::post('v1/permission',[PermissionController::class,'store']);
Route::get('v1/permission',[PermissionController::class,'index']);
Route::put('v1/permission/{id}',[PermissionController::class,'update']);
Route::get('v1/permission/{id}',[PermissionController::class,'edit']);
Route::delete('v1/permission/{id}',[PermissionController::class,'destroy']);

Route::get('v1/role',[RoleController::class,'index']);
Route::post('v1/role',[RoleController::class,'store']);
Route::put('v1/role/{id}',[RoleController::class,'update']);
Route::get('v1/role/{id}',[RoleController::class,'edit']);
Route::delete('v1/role/{id}',[RoleController::class,'destroy']);
Route::get('v1/role/give-permission/{id}',[RoleController::class,'addPermissionsToRole']);
Route::put('v1/role/give-permission/{id}',[RoleController::class,'givePermissionsToRole']);
Route::get('v1/role/revoke-permission/{id}',[RoleController::class,'getPermissionsOfRole']);
Route::put('v1/role/revoke-permission/{id}',[RoleController::class,'revokePermissionFromRole']);
