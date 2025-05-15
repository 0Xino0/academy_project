<?php

use App\Models\Student;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Contracts\Role;
use App\Http\Controllers\TermController;
use App\Http\Controllers\api\user\UserController;
use App\Http\Controllers\api\class\ClassController;
use App\Http\Controllers\api\grade\GradeController;
use App\Http\Controllers\api\course\CourseController;
use App\Http\Controllers\api\student\StudentController;
use App\Http\Controllers\api\teacher\TeacherController;
use App\Http\Controllers\api\role_permission\RoleController;
use App\Http\Controllers\api\registration\RegistrationController;
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

Route::get('v1/permissions', [PermissionController::class, 'index']);
Route::post('v1/permissions', [PermissionController::class, 'store']);
Route::get('v1/permissions/{id}', [PermissionController::class, 'show']);
Route::put('v1/permissions/{id}', [PermissionController::class, 'update']);
Route::delete('v1/permissions/{id}', [PermissionController::class, 'destroy']);

Route::get('v1/roles', [RoleController::class, 'index']);
Route::post('v1/roles', [RoleController::class, 'store']);
Route::get('v1/roles/{id}', [RoleController::class, 'show']);
Route::put('v1/roles/{id}', [RoleController::class, 'update']);
Route::delete('v1/roles/{id}', [RoleController::class, 'destroy']);
Route::get('v1/roles/{id}/permissions', [RoleController::class, 'getPermissionsOfRole']);
Route::put('v1/roles/{id}/permissions', [RoleController::class, 'givePermissionsToRole']);
// optional
Route::delete('v1/roles/{id}/permissions', [RoleController::class, 'revokePermissionFromRole']);

Route::get('v1/users', [UserController::class, 'index']);
Route::post('v1/users', [UserController::class, 'store']);
Route::get('v1/users/{id}', [UserController::class, 'show']);
Route::put('v1/users/{id}', [UserController::class, 'update']);
Route::delete('v1/users/{id}', [UserController::class, 'destroy']);

Route::get('v1/teachers',[TeacherController::class,'index']);
Route::get('v1/teachers/{id}',[TeacherController::class,'show']);
Route::put('v1/teachers/{id}/admin-info', [TeacherController::class, 'updateAdminInfo']); // Updating data from the teacher that the admin must have access to
Route::put('v1/teachers/{id}',[TeacherController::class,'update']); // Updating data from the teacher that the teacher must have access to
Route::delete('v1/teachers/{id}',[TeacherController::class,'destroy']);

Route::get('v1/students',[StudentController::class,'index']);
Route::post('v1/students',[StudentController::class,'store']);
Route::get('v1/students/{id}',[StudentController::class,'show']);
Route::put('v1/students/{id}',[StudentController::class,'update']);
Route::delete('v1/students/{id}',[StudentController::class,'destroy']);

Route::get('v1/courses',[CourseController::class,'index']);
Route::post('v1/courses',[CourseController::class,'store']);
Route::get('v1/courses/{id}',[CourseController::class,'show']);
Route::put('v1/courses/{id}',[CourseController::class,'update']);
Route::delete('v1/courses/{id}',[CourseController::class,'destroy']);

Route::get('v1/terms',[TermController::class,'index']);
Route::post('v1/terms',[TermController::class,'store']);
Route::get('v1/terms/{id}',[TermController::class,'show']);
Route::put('v1/terms/{id}',[TermController::class,'update']);
Route::delete('v1/terms/{id}',[TermController::class,'destroy']);

Route::get('v1/terms/{term_id}/classes',[ClassController::class,'index']);
Route::post('v1/terms/{term_id}/classes',[ClassController::class,'store']);
Route::get('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'show']);
Route::put('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'update']);
Route::delete('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'destroy']);

Route::get('v1/registrations',[RegistrationController::class,'index']);
Route::post('v1/registrations',[RegistrationController::class,'store']);
Route::get('v1/registrations/{id}',[RegistrationController::class,'show']);
Route::put('v1/registrations/{id}',[RegistrationController::class,'update']);
Route::delete('v1/registrations/{id}',[RegistrationController::class,'destroy']);


require __DIR__ . '/auth.php';
