<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\EvaluationController; // Add this import
use Spatie\Permission\Models\Role;

// Public routes
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Events - Public
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

//  check roles
Route::get('/debug/roles', function () {
    return response()->json([
        'all_roles' => \Spatie\Permission\Models\Role::all()->pluck('name'),
        'role_count' => \Spatie\Permission\Models\Role::count()
    ]);
});

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
    
    // Submissions - (author, scientific_committee, event_organizer)
    Route::middleware('role:author,scientific_committee,event_organizer')->group(function () {
        Route::get('/submissions/my', [SubmissionController::class, 'mySubmissions']);
        Route::post('/submissions', [SubmissionController::class, 'store']);
        Route::get('/submissions/{id}', [SubmissionController::class, 'show']);
        Route::put('/submissions/{id}', [SubmissionController::class, 'update']);
        Route::delete('/submissions/{id}', [SubmissionController::class, 'destroy']);
    });
    
    // Submissions - Management (organizer & scientific_committee)
    Route::middleware('role:event_organizer,scientific_committee')->group(function () {
        Route::get('/submissions', [SubmissionController::class, 'index']);
        Route::post('/submissions/{id}/status', [SubmissionController::class, 'updateStatus']);
    });

    // Evaluations - Scientific Committee 
    Route::middleware('role:scientific_committee')->group(function () {
        Route::get('/evaluations/my-assigned', [EvaluationController::class, 'myAssignedSubmissions']);
        Route::post('/submissions/{submissionId}/evaluate', [EvaluationController::class, 'evaluateSubmission']);
        Route::put('/evaluations/{id}', [EvaluationController::class, 'update']);
    });

    // Evaluations - Organizer (Assignment & Management)
    Route::middleware('role:event_organizer')->group(function () {
        Route::post('/evaluations/assign', [EvaluationController::class, 'assignEvaluator']);
        Route::delete('/evaluations/{id}', [EvaluationController::class, 'destroy']);
    });

    // Evaluations - View (Author, Organizer, Scientific Committee)
    Route::middleware('role:author,event_organizer,scientific_committee')->group(function () {
        Route::get('/submissions/{submissionId}/evaluations', [EvaluationController::class, 'getSubmissionEvaluations']);
    });
    

    Route::middleware('auth:sanctum')->group(function () {
    // Public registration routes
    Route::post('/registrations', [RegistrationController::class, 'register']);
    Route::get('/registrations/my', [RegistrationController::class, 'myRegistrations']);
    Route::delete('/registrations/{id}', [RegistrationController::class, 'cancelRegistration']);
    Route::get('/registrations/{id}/badge', [RegistrationController::class, 'getBadgeData']);
    
    // Event organizer routes
    Route::middleware('role:event_organizer,super_admin')->group(function () {
        Route::get('/events/{eventId}/registrations', [RegistrationController::class, 'eventRegistrations']);
        Route::put('/registrations/{id}/payment-status', [RegistrationController::class, 'updatePaymentStatus']);
    });
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