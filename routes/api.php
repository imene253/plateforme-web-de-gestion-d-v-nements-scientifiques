<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\SubmissionController;

// Public routes
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Events - Public
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::put('/profile', [ApiAuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [ApiAuthController::class, 'uploadPhoto']);
    
    // Events - Protected (event_organizer only)
    Route::middleware('role:event_organizer')->group(function () {
        Route::post('/events', [EventController::class, 'store']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
    });
    
    // Submissions -  (author)
    Route::middleware('role:author,scientific_committee,event_organizer')->group(function () {
        Route::get('/submissions/my', [SubmissionController::class, 'mySubmissions']);
        Route::post('/submissions', [SubmissionController::class, 'store']);
        Route::get('/submissions/{id}', [SubmissionController::class, 'show']);
        Route::put('/submissions/{id}', [SubmissionController::class, 'update']);
        Route::delete('/submissions/{id}', [SubmissionController::class, 'destroy']);
    });
    
    // Submissions - إدارة (organizer & scientific_committee)
    Route::middleware('role:event_organizer,scientific_committee')->group(function () {
        Route::get('/submissions', [SubmissionController::class, 'index']);
        Route::post('/submissions/{id}/status', [SubmissionController::class, 'updateStatus']);
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