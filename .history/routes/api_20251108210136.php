<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\TestController;


// Public routes
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Events - Public (عرض الفعاليات)
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::put('/profile', [ApiAuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [ApiAuthController::class, 'uploadPhoto']);
    
    // Events - Protected (إنشاء، تعديل، حذف - event_organizer فقط)
    Route::middleware('role:event_organizer')->group(function () {
        Route::post('/events', [EventController::class, 'store']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
    });
    
    // Test routes
    Route::get('/test-organizer', function () {
        return response()->json([
            'success' => true,
            'message' => 'Welcome Event Organizer! You have access.',
            'your_roles' => auth()->user()->getRoleNames()
        ]);
    })->middleware('role:event_organizer');
    
    Route::get('/test-author', function () {
        return response()->json([
            'success' => true,
            'message' => 'Welcome Author/Scientific Committee!',
            'your_roles' => auth()->user()->getRoleNames()
        ]);
    })->middleware('role:author,scientific_committee');
});