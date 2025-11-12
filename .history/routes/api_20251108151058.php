<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\TestController;


// Public routes
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::put('/profile', [ApiAuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [ApiAuthController::class, 'uploadPhoto']); // ← جديد
});

 // Test route - فقط للـ event_organizer
 Route::middleware('role:event_organizer')->group(function () {
    Route::get('/test-organizer', function () {
        return response()->json([
            'success' => true,
            'message' => 'Welcome Event Organizer! You have access.'
        ]);
    });
});

// Test route - فقط للـ author أو scientific_committee
Route::middleware('role:author,scientific_committee')->group(function () {
    Route::get('/test-author', function () {
        return response()->json([
            'success' => true,
            'message' => 'Welcome Author/Scientific Committee!'
        ]);
    });
});
});