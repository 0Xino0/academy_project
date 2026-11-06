<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\DebtController;
use App\Http\Controllers\api\TermController;
use App\Http\Controllers\api\PaymentController;
use App\Http\Controllers\api\ScheduleController;
use App\Http\Controllers\api\UserController;
use App\Http\Controllers\api\ClassController;
use App\Http\Controllers\api\GradeController;
use App\Http\Controllers\api\CourseController;
use App\Http\Controllers\api\StudentController;
use App\Http\Controllers\api\TeacherController;
use App\Http\Controllers\api\role_permission\RoleController;
use App\Http\Controllers\api\RegistrationController;
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

Route::middleware('auth:api')->group(function(){

Route::get('v1/registration-policy', [\App\Http\Controllers\api\RegistrationSettingController::class, 'show']);
Route::put('v1/registration-policy', [\App\Http\Controllers\api\RegistrationSettingController::class, 'update'])->middleware('role:manager');

Route::get('v1/permissions', [PermissionController::class, 'index'])->middleware(['role:manager']);
Route::post('v1/permissions', [PermissionController::class, 'store'])->middleware(['role:manager']);
Route::get('v1/permissions/{id}', [PermissionController::class, 'show'])->middleware(['role:manager']);
Route::put('v1/permissions/{id}', [PermissionController::class, 'update'])->middleware(['role:manager']);
Route::delete('v1/permissions/{id}', [PermissionController::class, 'destroy'])->middleware(['role:manager']);

Route::get('v1/roles', [RoleController::class, 'index'])->middleware(['role:manager']);
Route::post('v1/roles', [RoleController::class, 'store'])->middleware(['role:manager']);
Route::get('v1/roles/{id}', [RoleController::class, 'show'])->middleware(['role:manager']);
Route::put('v1/roles/{id}', [RoleController::class, 'update'])->middleware(['role:manager']);
Route::delete('v1/roles/{id}', [RoleController::class, 'destroy'])->middleware(['role:manager']);
Route::get('v1/roles/{id}/permissions', [RoleController::class, 'getPermissionsOfRole'])->middleware(['role:manager']);
Route::put('v1/roles/{id}/permissions', [RoleController::class, 'givePermissionsToRole'])->middleware(['role:manager']);
// optional
Route::delete('v1/roles/{id}/permissions', [RoleController::class, 'revokePermissionFromRole'])->middleware(['role:manager']);

Route::get('v1/users', [UserController::class, 'index'])->middleware(['role:manager']);
Route::post('v1/users', [UserController::class, 'store'])->middleware(['role:manager']); // create user with teacher role
Route::get('v1/users/{id}', [UserController::class, 'show']);
Route::put('v1/users/{id}', [UserController::class, 'update']);
Route::delete('v1/users/{id}', [UserController::class, 'destroy'])->middleware(['role:manager']);

Route::get('v1/teachers',[TeacherController::class,'index'])->middleware(['role:manager']);
Route::get('v1/teachers/{id}',[TeacherController::class,'show'])->middleware(['role:manager|teacher']);
Route::put('v1/teachers/{id}/admin-info', [TeacherController::class, 'updateAdminInfo'])->middleware(['role:manager']); // Updating data from the teacher that the admin must have access to
Route::put('v1/teachers/{id}',[TeacherController::class,'update'])->middleware(['role:manager|teacher']); // Updating data from the teacher that the teacher must have access to
Route::delete('v1/teachers/{id}',[TeacherController::class,'destroy'])->middleware(['role:manager']);

Route::get('v1/classes/{class_id}/students',[StudentController::class,'indexPerClasses'])->middleware(['role:manager|teacher', 'can:view student']);
Route::get('v1/students',[StudentController::class,'index'])->middleware(['role:manager']);
Route::post('v1/students',[StudentController::class,'store'])->middleware(['role:manager|student']);
Route::get('v1/classes/{class_id}/students/{student_id}',[StudentController::class,'showPerClass'])->middleware(['role:manager|teacher', 'can:view student']);
Route::get('v1/students/{id}',[StudentController::class,'show'])->middleware(['role:manager|teacher|student']);
Route::put('v1/students/{id}',[StudentController::class,'update'])->middleware(['role:manager|student']);
Route::delete('v1/students/{id}',[StudentController::class,'destroy'])->middleware(['role:manager']);

Route::get('v1/courses',[CourseController::class,'index'])->middleware(['can:view course']);
Route::post('v1/courses',[CourseController::class,'store'])->middleware(['role:manager']);
Route::get('v1/courses/active', [CourseController::class, 'getActiveCourses'])->middleware(['can:view course']);
Route::get('v1/courses/{id}',[CourseController::class,'show'])->middleware(['can:view course']);
Route::put('v1/courses/{id}',[CourseController::class,'update'])->middleware(['role:manager']);
Route::delete('v1/courses/{id}',[CourseController::class,'destroy'])->middleware(['role:manager']);

Route::get('v1/terms',[TermController::class,'index'])->middleware(['can:view term']);
Route::post('v1/terms',[TermController::class,'store'])->middleware(['role:manager']);
Route::get('v1/terms/{id}',[TermController::class,'show'])->middleware(['can:view term']);
Route::put('v1/terms/{id}',[TermController::class,'update'])->middleware(['role:manager']);
Route::delete('v1/terms/{id}',[TermController::class,'destroy'])->middleware(['role:manager']);

Route::get('v1/terms/{term_id}/classes',[ClassController::class,'index'])->middleware(['role:manager']);
Route::get('v1/me/teaching-terms/{term_id}/classes',[ClassController::class,'indexForTeacher'])->middleware(['role:teacher', 'profile:teacher', 'can:view classmodel']);
Route::get('v1/me/studying-terms/{term_id}/classes',[ClassController::class,'indexForStudent'])->middleware(['role:student', 'profile:student', 'can:view classmodel']);
Route::post('v1/terms/{term_id}/classes',[ClassController::class,'store'])->middleware(['role:manager']);
Route::get('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'show'])->middleware(['role:manager']);
Route::get('v1/me/teaching-terms/{term_id}/classes/{class_id}',[ClassController::class,'showForTeacher'])->middleware(['role:teacher', 'profile:teacher', 'can:view classmodel']);
Route::get('v1/me/studying-terms/{term_id}/classes/{class_id}',[ClassController::class,'showForStudent'])->middleware(['role:student', 'profile:student', 'can:view classmodel']);
Route::put('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'update'])->middleware(['role:manager']);
Route::delete('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'destroy'])->middleware(['role:manager']);

Route::get('v1/terms/{term_id}/classes/{class_id}/registrations',[RegistrationController::class,'index'])->middleware(['role:manager|teacher', 'can:view student']);
Route::post('v1/terms/{term_id}/classes/{class_id}/registrations',[RegistrationController::class,'store'])->middleware(['role:student', 'profile:student']);
Route::get('v1/terms/{term_id}/classes/{class_id}/registrations/{registration_id}',[RegistrationController::class,'show'])->middleware(['role:manager|teacher|student']);
Route::delete('v1/terms/{term_id}/classes/{class_id}/registrations/{registration_id}',[RegistrationController::class,'destroy'])->middleware(['role:manager|student']);

Route::post('v1/classes/{class_id}/grades/batch',[GradeController::class,'batchStoreOrUpdate'])->middleware(['role:manager|teacher']); //teacher
Route::post('v1/classes/{class_id}/grades',[GradeController::class,'storeOrUpdate'])->middleware(['role:manager|teacher']); //teacher
Route::get('v1/classes/{class_id}/grades',[GradeController::class,'index'])->middleware(['role:manager|teacher', 'can:view grade']); //admin or teacher
Route::get('v1/me/classes/{class_id}/grades',[GradeController::class,'showForStudent'])->middleware(['role:student', 'profile:student']); //student

Route::post('v1/terms/{term_id}/classes/{class_id}/schedules',[ScheduleController::class,'store'])->middleware(['role:manager']); // admin
Route::get('v1/terms/{term_id}/schedules',[ScheduleController::class,'index'])->middleware(['role:manager']); // admin
Route::get('v1/me/teaching-terms/{term_id}/schedules',[ScheduleController::class,'indexForTeacher'])->middleware(['role:teacher', 'profile:teacher', 'can:view schedule']); // teacher
Route::get('v1/me/studying-terms/{term_id}/schedules',[ScheduleController::class,'indexForStudent'])->middleware(['role:student', 'profile:student', 'can:view schedule']); // student
Route::put('v1/terms/{term_id}/classes/{class_id}/schedules/{schedule_id}',[ScheduleController::class,'update'])->middleware(['role:manager']); // admin
Route::delete('v1/terms/{term_id}/classes/{class_id}/schedules/{schedule_id}',[ScheduleController::class,'destroy'])->middleware(['role:manager']); // admin

Route::get('v1/debts',[DebtController::class,'index'])->middleware(['role:manager']);
Route::get('v1/me/students/debts',[DebtController::class,'show'])->middleware(['role:student', 'profile:student']);

Route::get('v1/payments',[PaymentController::class,'index'])->middleware(['role:manager']);
Route::post('v1/debts/{debt_id}/payments',[PaymentController::class,'store'])->middleware(['role:manager|student']);
Route::get('v1/payments/{payment_id}',[PaymentController::class,'show'])->middleware(['role:manager|student']);

});




require __DIR__ . '/auth.php';
