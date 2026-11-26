<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AttendanceCorrectRequestController as AdminAttendanceCorrectRequestController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectRequestController;
use App\Http\Controllers\AttendanceReportController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('admin.login');

Route::post('admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:login'])
    ->name('admin.login.store');

Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('admin.logout');

Route::middleware(['auth', 'verified'])->group(function () {
    // 一般ユーザーの画面
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::get('/attendance/list', [AttendanceController::class, 'list']);
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->where('id', '[0-9]+|\d{4}-\d{2}-\d{2}');
    Route::post('/attendance/detail/{id}', [AttendanceCorrectRequestController::class, 'store'])->where('id', '[0-9]+|\d{4}-\d{2}-\d{2}');
    Route::get('/attendance/{id}', [AttendanceController::class, 'redirectToDetail'])->where('id', '[0-9]+|\d{4}-\d{2}-\d{2}');
    Route::post('/attendance/{id}', [AttendanceCorrectRequestController::class, 'store'])->where('id', '[0-9]+|\d{4}-\d{2}-\d{2}');
    Route::get('/application/{id}', [AttendanceCorrectRequestController::class, 'redirectToDetail'])->where('id', '[0-9]+');
    Route::get('/stamp_correction_request/list', [AttendanceCorrectRequestController::class, 'index']);
    Route::get('/attendance/report', [AttendanceReportController::class, 'index']);
});

Route::middleware(['auth', 'admin'])->group(function () {
    // 管理者の画面
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index']);
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])->where('id', '[0-9]+|\d{4}-\d{2}-\d{2}');
    Route::get('/admin/staff/list', [StaffController::class, 'index']);
    Route::get('/admin/attendance/staff/{id}', [StaffController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceCorrectRequestController::class, 'show'])->where('attendance_correct_request_id', '[0-9]+');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceCorrectRequestController::class, 'approve'])->where('attendance_correct_request_id', '[0-9]+');
    Route::post('/export', [StaffController::class, 'export']);
});
