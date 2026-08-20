<?php

// | KB @CerberRus00 - Nexus Invest Team
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
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
    Route::get('/checks/create', [CheckController::class, 'create'])->name('checks.create');
    Route::post('/checks/address', [CheckController::class, 'storeAddress'])->name('checks.address');
    Route::post('/checks/token', [CheckController::class, 'storeToken'])->name('checks.token');
    Route::post('/checks/phishing', [CheckController::class, 'storePhishing'])->name('checks.phishing');
    Route::post('/checks/dapp', [CheckController::class, 'storeDapp'])->name('checks.dapp');
    Route::post('/checks/scan', [CheckController::class, 'storeScan'])->name('checks.scan');
    Route::get('/checks/{check}', [CheckController::class, 'show'])->name('checks.show');
    Route::get('/checks/{check}/status', [CheckController::class, 'status'])->name('checks.status');
    Route::get('/checks/{check}/pdf', [CheckController::class, 'pdf'])->name('checks.pdf');
    Route::patch('/checks/{check}/verdict', [CheckController::class, 'updateVerdict'])->name('checks.verdict');

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
