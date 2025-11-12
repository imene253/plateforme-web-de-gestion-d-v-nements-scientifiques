<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\TestController;

// Test route
Route::get('test', [TestController::class, 'test']);

// Public routes (no authentication required)
Route::post('register', [ApiAuthController::class, 'register']);
Route::post('login', [ApiAuthController::class, 'login']);

// Protected routes (need Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [ApiAuthController::class, 'logout']);
    Route::get('me', [ApiAuthController::class, 'me']);
});