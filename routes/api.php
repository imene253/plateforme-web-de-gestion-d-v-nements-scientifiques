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
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\ProgramPeriodController;
use App\Http\Controllers\Api\WorkshopController;
use App\Http\Controllers\Api\WorkshopMaterialController;
use Spatie\Permission\Models\Role;

// Public routes
Route::post('/register', [ApiAuthController::class, 'register']);
Route::post('/login', [ApiAuthController::class, 'login']);

// Events - Public
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// Program - Public
Route::get('events/{eventId}/program', [SessionController::class, 'showProgram']);

// Workshops - Public
Route::get('events/{eventId}/workshops', [WorkshopController::class, 'index']);




Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::put('/profile', [ApiAuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [ApiAuthController::class, 'uploadPhoto']);
    // Workshop Registration
    Route::post('workshops/{workshopId}/register', [WorkshopController::class, 'register']);
    Route::delete('workshops/{workshopId}/unregister', [WorkshopController::class, 'unregister']);
    
    // Events  (event_organizer only)
    Route::middleware('role:event_organizer,super_admin')->group(function () {
        Route::post('/events', [EventController::class, 'store']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
        // Sessions (event_organizer only)
        Route::prefix('events/{eventId}')->group(function () {
            Route::resource('sessions', SessionController::class)->only([
                'index', 'store', 'update', 'destroy'
            ]);
            
            // Special route to assign a submission to a session
            Route::post('sessions/{sessionId}/assign-submission', [SessionController::class, 'assignSubmission']);
        });
        // Program Period Management
        Route::prefix('events/{eventId}')->group(function () {
        Route::resource('periods', ProgramPeriodController::class)->only([
            'store', 'update', 'destroy'
        ]);
        });

        // route to set the start/end time of an accepted, assigned submission
        Route::patch('events/{eventId}/sessions/{sessionId}/presentations/{submissionId}/time', 
        [SessionController::class, 'updatePresentationTime']
        )->name('presentation.updateTime');

        // routes to manage workshops
        Route::prefix('events/{eventId}')->group(function () {
        Route::post('workshops', [WorkshopController::class, 'store']);
        Route::put('workshops/{workshopId}', [WorkshopController::class, 'update']);
        Route::delete('workshops/{workshopId}', [WorkshopController::class, 'destroy']);
        });
    });
    
    // Submissions - (author, scientific_committee, event_organizer)
    Route::middleware('role:author,scientific_committee,event_organizer,super_admin')->group(function () {
        Route::get('/submissions/my', [SubmissionController::class, 'mySubmissions']);
        Route::post('/submissions', [SubmissionController::class, 'store']);
        Route::get('/submissions/{id}', [SubmissionController::class, 'show']);
        Route::put('/submissions/{id}', [SubmissionController::class, 'update']);
        Route::delete('/submissions/{id}', [SubmissionController::class, 'destroy']);
    });
    
    // Submissions - Management (organizer & scientific_committee)
    Route::middleware('role:event_organizer,scientific_committee,super_admin')->group(function () {
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


    // Workshops - File Upload (Workshop Facilitator)
    Route::middleware('role:workshop_facilitator,super_admin')->group(function () {
    Route::post('workshops/{workshopId}/materials', [WorkshopMaterialController::class, 'store']);
    Route::delete('workshops/{workshopId}/materials/{materialId}', [WorkshopMaterialController::class, 'destroy']);

    // View all workshop submissions (pending, accepted, declined)
    Route::get('workshops/{workshopId}/submissions', [WorkshopController::class, 'viewSubmissions']);

    // Accept or decline a specific submission
    Route::post('workshops/{workshopId}/submissions/{userId}/moderate', [WorkshopController::class, 'moderateRegistration']);

    // View accepted participants
    Route::get('workshops/{workshopId}/participants', [WorkshopController::class, 'viewAcceptedParticipants']);
    });

    // Viewing/Downloading Materials (Accessible by Participants and Animator)
    Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('workshops/{workshopId}/materials', [WorkshopMaterialController::class, 'index']);
    Route::get('workshops/{workshopId}/materials/{materialId}', [WorkshopMaterialController::class, 'show']);
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

        Route::get('/organizers/pending', [SuperAdminController::class, 'getPendingOrganizers']);
        Route::post('/organizers/{id}/approve', [SuperAdminController::class, 'approveOrganizer']);
        Route::delete('/organizers/{id}/reject', [SuperAdminController::class, 'rejectOrganizer']); // Added this
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