<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\user\UserController;
use App\Http\Controllers\api\teacher\TeacherController;
use App\Http\Controllers\api\role_permission\RoleController;
use App\Http\Controllers\api\role_permission\PermissionController;

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

Route::get('v1/user/create',[UserController::class,'create']);
Route::get('v1/user',[UserController::class,'index']);
Route::get('v1/user/{id}',[UserController::class,'edit']);
Route::put('v1/user/{id}',[UserController::class,'update']);
Route::delete('v1/user/{id}',[UserController::class,'destroy']);

Route::get('v1/teachers',[TeacherController::class,'index']);
Route::get('v1/teacher/create/{id}',[TeacherController::class,'create']);
Route::post('v1/teacher/{id}',[TeacherController::class,'store']);
Route::put('v1/teacher/{id}',[TeacherController::class,'update']);
Route::delete('v1/teacher/{id}',[TeacherController::class,'destroy']);


require __DIR__ . '/auth.php';
