<?php

// | KB @CerberRus00 - Nexus Invest Team
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScreeningCaseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WatchItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/checks', [CheckController::class, 'index'])->name('checks.index');
    Route::get('/checks/export', [CheckController::class, 'export'])->name('checks.export');
    Route::get('/checks/create', [CheckController::class, 'create'])->name('checks.create');
    Route::post('/checks/address', [CheckController::class, 'storeAddress'])->name('checks.address');
    Route::post('/checks/token', [CheckController::class, 'storeToken'])->name('checks.token');
    Route::post('/checks/phishing', [CheckController::class, 'storePhishing'])->name('checks.phishing');
    Route::post('/checks/dapp', [CheckController::class, 'storeDapp'])->name('checks.dapp');
    Route::post('/checks/scan', [CheckController::class, 'storeScan'])->name('checks.scan');
    Route::get('/checks/{check}', [CheckController::class, 'show'])->name('checks.show');
    Route::get('/checks/{check}/status', [CheckController::class, 'status'])->name('checks.status');
    Route::post('/checks/{check}/enrich', [CheckController::class, 'enrich'])->name('checks.enrich');
    Route::post('/checks/{check}/rerun', [CheckController::class, 'rerun'])->name('checks.rerun');
    Route::get('/checks/{check}/pdf', [CheckController::class, 'pdf'])->name('checks.pdf');
    Route::patch('/checks/{check}/verdict', [CheckController::class, 'updateVerdict'])->name('checks.verdict');
    Route::delete('/checks/{check}', [CheckController::class, 'destroy'])->name('checks.destroy');

    Route::get('/cases', [ScreeningCaseController::class, 'index'])->name('cases.index');
    Route::post('/cases', [ScreeningCaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{case}', [ScreeningCaseController::class, 'show'])->name('cases.show');

    Route::get('/watch', [WatchItemController::class, 'index'])->name('watch.index');
    Route::post('/watch', [WatchItemController::class, 'store'])->name('watch.store');
    Route::patch('/watch/{watch}', [WatchItemController::class, 'update'])->name('watch.update');
    Route::delete('/watch/{watch}', [WatchItemController::class, 'destroy'])->name('watch.destroy');

    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

    Route::get('/tokens', [ApiTokenController::class, 'index'])->name('tokens.index');
    Route::post('/tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
});

require __DIR__.'/auth.php';
