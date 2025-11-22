<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\Submission;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    /**
     * Dashboard - إحصائيات شاملة
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
            'completed_evaluations' => Evaluation::where('is_completed', true)->count(),
            'recent_users' => User::with('roles')->latest()->take(5)->get(),
            'recent_events' => Event::with('organizer:id,name,institution')->latest()->take(5)->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     *  عرض الكل
     */
    public function getAllUsers(Request $request)
    {
        $query = User::with('roles');

        // Filter by role
        if ($request->has('role')) {
            $query->role($request->role);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Create Event Organizer
     */
    public function createOrganizer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'institution' => 'required|string|max:255',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'institution' => $request->institution,
            'country' => $request->country,
        ]);

        $user->assignRole('event_organizer');

        return response()->json([
            'success' => true,
            'message' => 'Event Organizer created successfully',
            'data' => $user->load('roles')
        ], 201);
    }

    /**
     * تفعيل/تعطيل حساب مستخدم
     */
    public function toggleUserStatus($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent deactivating super admin
        if ($user->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate super admin'
            ], 403);
        }

        // Toggle is_active (add column if not exists)
        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'User activated' : 'User deactivated',
            'data' => $user
        ]);
    }

    /**
     * حذف مستخدم
     */
    public function deleteUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent deleting super admin
        if ($user->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

  
    public function getAllEvents(Request $request)
    {
        $query = Event::with(['organizer:id,name,email,institution']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $events = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

   
    public function getAllSubmissions(Request $request)
    {
        $query = Submission::with(['event:id,title', 'author:id,name,email,institution', 'evaluations']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by event
        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

   
    public function getAllEvaluations(Request $request)
    {
        $query = Evaluation::with(['submission.event', 'evaluator:id,name,institution']);

        // Filter by completed status
        if ($request->has('completed')) {
            $query->where('is_completed', $request->completed === 'true');
        }

        $evaluations = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $evaluations
        ]);
    }

    
    public function updateUserRole(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Remove old roles and assign new one
        $user->syncRoles([$request->role]);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => $user->load('roles')
        ]);
    }

   
    public function deleteEvent($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $event->delete(); 

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }
}