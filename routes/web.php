<?php

use App\Enums\StorePermission;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'store'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:'.StorePermission::EmployeesManage->value)->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('employees/{employee}/password', [EmployeeController::class, 'updatePassword'])->name('employees.password.update');
        Route::post('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        Route::post('employees/{employee}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');
    });
});

Route::middleware(['auth', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('stores', [StoreController::class, 'index'])->name('stores.index');
    Route::post('stores/{team}/block', [StoreController::class, 'block'])->name('stores.block');
    Route::post('stores/{team}/unblock', [StoreController::class, 'unblock'])->name('stores.unblock');
    Route::put('stores/{team}/owner-password', [StoreController::class, 'updateOwnerPassword'])->name('stores.owner-password.update');
});

require __DIR__.'/settings.php';
