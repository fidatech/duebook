<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth('web')->check() ? 'dashboard' : 'login'));

/* ------------------------------------------------------------------ sign in, sign up */

Route::middleware('guest:web')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,60')->name('register.store');
    Route::get('forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'email'])->middleware('throttle:6,10')->name('password.email');
    Route::get('reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'store'])->name('password.store');
});

/* ------------------------------------------------------------- a business (tenant) */

Route::middleware('auth:web')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('tenant.active')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('dues', [DueController::class, 'index'])->name('dues.index');

        crud_routes('clients', ClientController::class, 'clients');
        Route::get('clients/{id}', [ClientController::class, 'show'])->whereNumber('id')->name('clients.show');

        crud_routes('services', ServiceController::class, 'services');

        crud_routes('contracts', ContractController::class, 'contracts');
        Route::get('contracts/{id}/pay', [ContractController::class, 'pay'])->whereNumber('id')->name('contracts.pay');
        Route::post('contracts/{id}/pay', [ContractController::class, 'storePayment'])->whereNumber('id')->name('contracts.pay.store');
        Route::get('contracts/{id}/file', [ContractController::class, 'file'])->whereNumber('id')->name('contracts.file');

        crud_routes('people', PersonController::class, 'people');
        Route::get('people/{id}', [PersonController::class, 'show'])->whereNumber('id')->name('people.show');
        Route::post('people/{id}/entries', [PersonController::class, 'storeEntry'])->whereNumber('id')->name('people.entries.store');
        Route::delete('people/{id}/entries/{entry}', [PersonController::class, 'destroyEntry'])->whereNumber(['id', 'entry'])->name('people.entries.destroy');

        Route::prefix('money/{type}')->where(['type' => 'income|expense'])->group(function () {
            crud_routes('', TransactionController::class, 'money');
        });
        Route::prefix('categories/{type}')->where(['type' => 'income|expense'])->group(function () {
            crud_routes('', CategoryController::class, 'categories');
        });

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::middleware('owner')->group(function () {
            crud_routes('team', TeamController::class, 'team');
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('notifications', [ProfileController::class, 'notices'])->name('notices.index');
    Route::get('notifications/{id}', [ProfileController::class, 'openNotice'])->whereNumber('id')->name('notices.open');
    Route::post('notifications/read', [ProfileController::class, 'readNotices'])->name('notices.read');
});

/* ------------------------------------------------- system administrators (/admin) */

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [Admin\LoginController::class, 'show'])->name('login');
        Route::post('login', [Admin\LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [Admin\LoginController::class, 'destroy'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('tenants', [Admin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/create', [Admin\TenantController::class, 'create'])->name('tenants.create');
        Route::post('tenants', [Admin\TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{id}/edit', [Admin\TenantController::class, 'edit'])->whereNumber('id')->name('tenants.edit');
        Route::put('tenants/{id}', [Admin\TenantController::class, 'update'])->whereNumber('id')->name('tenants.update');
        Route::delete('tenants/{id}', [Admin\TenantController::class, 'destroy'])->whereNumber('id')->name('tenants.destroy');

        crud_routes('plans', Admin\PlanController::class, 'plans');
        crud_routes('subscriptions', Admin\SubscriptionController::class, 'subscriptions');
        crud_routes('accounts', Admin\AccountController::class, 'accounts');
        crud_routes('admins', Admin\AdminController::class, 'admins');
    });
});
