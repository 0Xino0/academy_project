<?php

use App\Models\Student;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Contracts\Role;
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
Route::post('v1/users', [UserController::class, 'store']); // create user with teacher role
Route::get('v1/users/{id}', [UserController::class, 'show']);
Route::put('v1/users/{id}', [UserController::class, 'update']);
Route::delete('v1/users/{id}', [UserController::class, 'destroy']);

Route::get('v1/teachers',[TeacherController::class,'index']);
Route::get('v1/teachers/{id}',[TeacherController::class,'show']);
Route::put('v1/teachers/{id}/admin-info', [TeacherController::class, 'updateAdminInfo']); // Updating data from the teacher that the admin must have access to
Route::put('v1/teachers/{id}',[TeacherController::class,'update']); // Updating data from the teacher that the teacher must have access to
Route::delete('v1/teachers/{id}',[TeacherController::class,'destroy']);

Route::get('v1/classes/{class_id}/students',[StudentController::class,'indexPerClasses']);
Route::get('v1/students',[StudentController::class,'index']);
Route::post('v1/students',[StudentController::class,'store']);
Route::get('v1/classes/{class_id}/students/{student_id}',[StudentController::class,'showPerClass']);
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
Route::get('v1/me/teaching-terms/{term_id}/classes',[ClassController::class,'indexForTeacher']);
Route::get('v1/me/studying-terms/{term_id}/classes',[ClassController::class,'indexForStudent']);
Route::post('v1/terms/{term_id}/classes',[ClassController::class,'store']);
Route::get('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'show']);
Route::get('v1/me/teaching-terms/{term_id}/classes/{class_id}',[ClassController::class,'showForTeacher']);
Route::get('v1/me/studying-terms/{term_id}/classes/{class_id}',[ClassController::class,'showForStudent']);
Route::put('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'update']);
Route::delete('v1/terms/{term_id}/classes/{class_id}',[ClassController::class,'destroy']);

Route::get('v1/terms/{term_id}/classes/{class_id}/registrations',[RegistrationController::class,'index']);
Route::post('v1/terms/{term_id}/classes/{class_id}/registrations',[RegistrationController::class,'store']);
Route::get('v1/terms/{term_id}/classes/{class_id}/registrations/{registration_id}',[RegistrationController::class,'show']);
Route::delete('v1/terms/{term_id}/classes/{class_id}/registrations/{registration_id}',[RegistrationController::class,'destroy']);

Route::post('v1/classes/{class_id}/grades/batch',[GradeController::class,'batchStoreOrUpdate']); //teacher
Route::post('v1/classes/{class_id}/grades',[GradeController::class,'storeOrUpdate']); //teacher
Route::get('v1/classes/{class_id}/grades',[GradeController::class,'index']); //admin or teacher
Route::get('v1/me/classes/{class_id}/grades',[GradeController::class,'showForStudent']); //student

Route::post('v1/terms/{term_id}/classes/{class_id}/schedules',[ScheduleController::class,'store']); // admin
Route::get('v1/terms/{term_id}/schedules',[ScheduleController::class,'index']); // admin
Route::get('v1/me/teaching-terms/{term_id}/schedules',[ScheduleController::class,'indexForTeacher']); // teacher   
Route::get('v1/me/studying-terms/{term_id}/schedules',[ScheduleController::class,'indexForStudent']); // student
Route::put('v1/terms/{term_id}/classes/{class_id}/schedules/{schedule_id}',[ScheduleController::class,'update']); // admin
Route::delete('v1/terms/{term_id}/classes/{class_id}/schedules/{schedule_id}',[ScheduleController::class,'destroy']); // admin

Route::get('v1/debts',[DebtController::class,'index']);
Route::get('v1/students/{student_id}/debts',[DebtController::class,'show']);

Route::get('v1/payments',[PaymentController::class,'index']);
Route::post('v1/debts/{debt_id}/payments',[PaymentController::class,'store']);
Route::get('v1/payments/{payment_id}',[PaymentController::class,'show']);




require __DIR__ . '/auth.php';
