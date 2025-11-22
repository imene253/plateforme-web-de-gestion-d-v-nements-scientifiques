<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\RegistrationController;
use Spatie\Permission\Models\Role;

// Public routes
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Events - Public
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);




Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::put('/profile', [ApiAuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [ApiAuthController::class, 'uploadPhoto']);
    
    // Events  (event_organizer only)
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

    // Registration routes
    Route::post('/registrations', [RegistrationController::class, 'register']);
    Route::get('/registrations/my', [RegistrationController::class, 'myRegistrations']);
    Route::delete('/registrations/{id}', [RegistrationController::class, 'cancelRegistration']);
    Route::get('/registrations/{id}/badge', [RegistrationController::class, 'getBadgeData']);
    
    // Registration management routes (organizer only)
    Route::middleware('role:event_organizer,super_admin')->group(function () {
        Route::get('/events/{eventId}/registrations', [RegistrationController::class, 'eventRegistrations']);
        Route::put('/registrations/{id}/payment-status', [RegistrationController::class, 'updatePaymentStatus']);
    });

    // Super Admin Routes
    Route::middleware('role:super_admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
        
        // User Management
        Route::get('/users', [SuperAdminController::class, 'getAllUsers']);
        Route::post('/users/organizer', [SuperAdminController::class, 'createOrganizer']);
        Route::put('/users/{id}/role', [SuperAdminController::class, 'updateUserRole']);
        Route::put('/users/{id}/toggle-status', [SuperAdminController::class, 'toggleUserStatus']);
        Route::delete('/users/{id}', [SuperAdminController::class, 'deleteUser']);
        
        // Event Management
        Route::get('/events', [SuperAdminController::class, 'getAllEvents']);
        Route::delete('/events/{id}', [SuperAdminController::class, 'deleteEvent']);
        
        // Submission Management
        Route::get('/submissions', [SuperAdminController::class, 'getAllSubmissions']);
        
        // Evaluation Management
        Route::get('/evaluations', [SuperAdminController::class, 'getAllEvaluations']);
    });
    
    
});