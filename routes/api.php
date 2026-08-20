<?php

// | KB @CerberRus00 - Nexus Invest Team
use App\Http\Controllers\Api\V1\CheckController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/checks', [CheckController::class, 'index']);
    Route::post('/checks/address', [CheckController::class, 'address']);
    Route::post('/checks/token', [CheckController::class, 'token']);
    Route::post('/checks/phishing', [CheckController::class, 'phishing']);
    Route::post('/checks/dapp', [CheckController::class, 'dapp']);
    Route::post('/checks/scan', [CheckController::class, 'scan']);
    Route::get('/checks/{check}', [CheckController::class, 'show']);
    Route::get('/checks/{check}/pdf', [CheckController::class, 'pdf']);
});
