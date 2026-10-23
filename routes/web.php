<?php

use App\Enums\StorePermission;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\CounterpartyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockReportController;
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

    Route::get('products', [ProductController::class, 'index'])
        ->middleware('can:'.StorePermission::ProductsView->value)->name('products.index');

    Route::middleware('can:'.StorePermission::ProductsManage->value)->group(function () {
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
        Route::post('products/{product}/unarchive', [ProductController::class, 'unarchive'])->name('products.unarchive');
    });

    Route::get('stock', StockReportController::class)
        ->middleware('can:'.StorePermission::StockReportView->value)->name('stock.index');

    Route::middleware('can:'.StorePermission::LocationsManage->value)->group(function () {
        Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
        Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::post('locations/{location}/archive', [LocationController::class, 'archive'])->name('locations.archive');
        Route::post('locations/{location}/unarchive', [LocationController::class, 'unarchive'])->name('locations.unarchive');
    });

    Route::get('counterparties', [CounterpartyController::class, 'index'])
        ->middleware('can:'.StorePermission::CounterpartiesView->value)->name('counterparties.index');
    Route::post('counterparties', [CounterpartyController::class, 'store'])
        ->middleware('can:'.StorePermission::CounterpartiesCreate->value)->name('counterparties.store');
    Route::put('counterparties/{counterparty}', [CounterpartyController::class, 'update'])
        ->middleware('can:'.StorePermission::CounterpartiesManage->value)->name('counterparties.update');

    Route::middleware('can:'.StorePermission::OpeningBalanceManage->value)->group(function () {
        Route::get('opening-balance', [OpeningBalanceController::class, 'show'])->name('opening-balance.show');
        Route::put('opening-balance', [OpeningBalanceController::class, 'update'])->name('opening-balance.update');
        Route::post('opening-balance/execute', [OpeningBalanceController::class, 'execute'])->name('opening-balance.execute');
        Route::delete('opening-balance', [OpeningBalanceController::class, 'destroy'])->name('opening-balance.destroy');
        Route::post('opening-balance/skip', [OpeningBalanceController::class, 'skip'])->name('opening-balance.skip');
    });
});

Route::middleware(['auth', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('stores', [StoreController::class, 'index'])->name('stores.index');
    Route::post('stores/{team}/block', [StoreController::class, 'block'])->name('stores.block');
    Route::post('stores/{team}/unblock', [StoreController::class, 'unblock'])->name('stores.unblock');
    Route::put('stores/{team}/owner-password', [StoreController::class, 'updateOwnerPassword'])->name('stores.owner-password.update');
});

require __DIR__.'/settings.php';
