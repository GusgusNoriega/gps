<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::middleware(['auth:web', 'administrator'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot'])->name('dashboard.snapshot');
    Route::post('/dashboard/demo', [DashboardController::class, 'demo'])->name('dashboard.demo');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/enrollments', [DeviceController::class, 'enrollments'])->name('enrollments.index');
    Route::post('/enrollments/{enrollment}/approve', [DeviceController::class, 'approve'])->name('enrollments.approve');
    Route::post('/enrollments/{enrollment}/reject', [DeviceController::class, 'reject'])->name('enrollments.reject');
    Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::post('/devices/{device}/disable', [DeviceController::class, 'disable'])->name('devices.disable');
    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/history/data', [HistoryController::class, 'data'])->name('history.data');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/server/preview', [SettingsController::class, 'prepareServer'])->name('settings.server.preview');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [SettingsController::class, 'password'])->name('settings.password');
});
