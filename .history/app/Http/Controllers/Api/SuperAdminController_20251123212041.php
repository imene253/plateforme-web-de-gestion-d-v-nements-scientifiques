<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\Submission;
use App\Models\Evaluation;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'users_by_role' => User::select('roles.name', DB::raw('count(*) as count'))
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->groupBy('roles.name')
                ->get(),
            'pending_organizers' => User::whereHas('roles', function($query) {
                $query->where('name', 'event_organizer');
            })->where('is_active', false)->count(),
            'total_events' => Event::count(),
            'events_by_status' => Event::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get(),
            'events_by_type' => Event::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get(),
            'total_submissions' => Submission::count(),
            'submissions_by_status' => Submission::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get(),
            'total_evaluations' => Evaluation::count(),
            'total_registrations' => Registration::count(),
            'registration_revenue' => Registration::where('payment_status', 'paid')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get pending event organizers for approval
     */
    public function getPendingOrganizers()
    {
        $pendingOrganizers = User::whereHas('roles', function($query) {
            $query->where('name', 'event_organizer');
        })
        ->where('is_active', false)
        ->with('roles')
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $pendingOrganizers
        ]);
    }

    /**
     * Approve event organizer
     */
    public function approveOrganizer($id)
    {
        $organizer = User::whereHas('roles', function($query) {
            $query->where('name', 'event_organizer');
        })
        ->where('is_active', false)
        ->findOrFail($id);

        $organizer->update(['is_active' => true]);

        // Here you could send an email notification to the organizer
        // Mail::to($organizer->email)->send(new OrganizerApprovedMail($organizer));

        return response()->json([
            'success' => true,
            'message' => 'Event organizer approved successfully',
            'data' => [
                'organizer' => $organizer->load('roles'),
                'approved_at' => now()
            ]
        ]);
    }

    /**
     * Reject event organizer
     */
    public function rejectOrganizer($id)
    {
        $organizer = User::whereHas('roles', function($query) {
            $query->where('name', 'event_organizer');
        })
        ->where('is_active', false)
        ->findOrFail($id);

        // Optionally delete the user or keep them inactive
        $organizer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event organizer application rejected and removed'
        ]);
    }

    /**
     * Get all users
     */
    public function getAllUsers()
    {
        $users = User::with('roles')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Create organizer directly (bypass approval)
     */
    public function createOrganizer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $organizer = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'institution' => $request->institution,
            'country' => $request->country,
            'is_active' => true, // Pre-approved by super admin
        ]);

        $organizer->assignRole('event_organizer');

        return response()->json([
            'success' => true,
            'message' => 'Event organizer created successfully',
            'data' => [
                'organizer' => $organizer->load('roles')
            ]
        ], 201);
    }

    /**
     * Toggle user status
     */
    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);
        
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => [
                'user' => $user->load('roles'),
                'new_status' => $user->is_active ? 'active' : 'inactive'
            ]
        ]);
    }

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Don't allow deleting super admins
        if ($user->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin user'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get all events
     */
    public function getAllEvents()
    {
        $events = Event::with(['organizer:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get all submissions
     */
    public function getAllSubmissions()
    {
        $submissions = Submission::with(['event:id,title', 'author:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

    /**
     * Get all evaluations
     */
    public function getAllEvaluations()
    {
        $evaluations = Evaluation::with([
            'submission:id,title',
            'evaluator:id,name,email'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $evaluations
        ]);
    }
}