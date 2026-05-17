<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OTPController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/email/verify', [OTPController::class, 'verify']);
    Route::post('/email/resend-otp', [OTPController::class, 'resend']);

    Route::middleware('verified.otp')->group(function () {
        Route::get('/verified-ping', fn () => response()->json(['message' => 'ok']));
    });
});
