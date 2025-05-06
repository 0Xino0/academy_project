<?php

use App\Models\Student;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\user\UserController;
use App\Http\Controllers\api\class\ClassController;
use App\Http\Controllers\api\grade\GradeController;
use App\Http\Controllers\api\course\CourseController;
use App\Http\Controllers\api\student\StudentController;
use App\Http\Controllers\api\teacher\TeacherController;
use App\Http\Controllers\api\role_permission\RoleController;
use App\Http\Controllers\api\registration\RegistrationController;
use App\Http\Controllers\api\role_permission\PermissionController;
use Spatie\Permission\Contracts\Role;

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
Route::post('v1/teacher',[TeacherController::class,'store']);
Route::put('v1/teacher/{id}',[TeacherController::class,'update']);
Route::delete('v1/teacher/{id}',[TeacherController::class,'destroy']);

Route::get('v1/students',[StudentController::class,'index']);
Route::get('v1/student/create/{id}',[StudentController::class,'create']);
Route::post('v1/student',[StudentController::class,'store']);
Route::get('v1/student/update/{id}',[StudentController::class,'edit']);
Route::put('v1/student/{id}',[StudentController::class,'update']);
Route::delete('v1/student/{id}',[StudentController::class,'destroy']);

Route::get('v1/courses',[CourseController::class,'index']);
Route::post('v1/course/create',[CourseController::class,'store']);
Route::get('v1/course/show/{id}',[CourseController::class,'show']);
Route::get('v1/course/edit/{id}',[CourseController::class,'edit']);
Route::put('v1/course/update/{id}',[CourseController::class,'update']);
Route::delete('v1/course/delete/{id}',[CourseController::class,'destroy']);

Route::get('v1/classes',[ClassController::class,'index']);
Route::get('v1/class/create',[ClassController::class,'create']);
Route::post('v1/class',[ClassController::class,'store']);
Route::get('v1/class/edit/{id}',[ClassController::class,'edit']);
Route::put('v1/class/update/{id}',[ClassController::class,'update']);
Route::delete('v1/class/delete/{id}',[ClassController::class,'destroy']);

Route::get('v1/registrations',[RegistrationController::class,'index']);
Route::get('v1/registration/create',[RegistrationController::class,'create']);
Route::post('v1/registration',[RegistrationController::class,'store']);
Route::get('v1/registration/show/{id}',[RegistrationController::class,'show']);
Route::get('v1/registration/edit/{id}',[RegistrationController::class,'edit']);
Route::put('v1/registration/update/{id}',[RegistrationController::class,'update']);
Route::delete('v1/registration/delete/{id}',[RegistrationController::class,'destroy']);


require __DIR__ . '/auth.php';
