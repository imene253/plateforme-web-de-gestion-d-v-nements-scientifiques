<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Models\User; // To check the animator's role
use App\Models\Registration; // To check the animator's event registration
use App\Models\WorkshopRegistration;
use App\Http\Traits\ProgramValidationTrait; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; 

class WorkshopController extends Controller
{
    use ProgramValidationTrait;
    public function index($eventId)
    {
        $workshops = Workshop::where('event_id', $eventId)
            ->with('animator:id,name,email') 
            ->orderBy('start_time') 
            ->get();

        if ($workshops->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No workshops found for this event.',
                'workshops' => [],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'event_id' => (int)$eventId,
            'workshops' => $workshops,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $eventId)
    {
        if ($response = $this->checkEventStarted($eventId)) {
            return $response; 
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'animator_id' => 'required|exists:users,id', 
            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
            'room' => 'required|string|max:255',
            'max_participants' => 'required|integer|min:1', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($response = $this->checkItemAgainstEventDates($eventId, $request->start_time, $request->end_time)) {
            return $response; 
        }

        $animatorId = $request->animator_id;
        $animator = User::find($animatorId);

        if (!$animator || !$animator->hasRole('workshop_facilitator')) {
            return response()->json([
                'message' => 'Validation Error: The specified animator is invalid or is not a workshop animator.'
            ], 422);
        }


        if (!Registration::where('user_id', $animatorId)->where('event_id', $eventId)->exists()) {
            return response()->json([
                'message' => 'Validation Error: The specified animator must be registered for this event.'
            ], 422);
        }


        if ($response = $this->checkRoomAvailability(
            $eventId, 
            $request->room, 
            $request->start_time, 
            $request->end_time,
            null, 
            'Workshop' 
        )) {
            return $response; 
        }


        $workshop = Workshop::create([
            'event_id' => $eventId,
            'title' => $request->title,
            'description' => $request->description,
            'animator_id' => $animatorId,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'room' => $request->room,
            'max_participants' => $request->max_participants,
            'current_participants' => 0, // Initialize to zero
        ]);

        return response()->json([
            'message' => 'Workshop created successfully.',
            'workshop' => $workshop
        ], 201);
    }


    public function register(Request $request, $workshopId)
    {
        $userId = auth()->id();
        $workshop = Workshop::findOrFail($workshopId);

        $request->validate(['reason_for_interest' => 'required|string|max:750']);
    
        $existing = WorkshopRegistration::where('workshop_id', $workshopId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $statusMessage = match ($existing->status) {
                'accepted' => 'You are already accepted into this workshop.',
                'declined' => 'Your registration for this workshop was declined.',
                default    => 'Your registration is already pending approval.',
            };
            return response()->json(['message' => $statusMessage], 422);
        }

        WorkshopRegistration::create([
            'workshop_id' => $workshopId,
            'user_id' => $userId,
            'status' => 'pending', 
            'reason_for_interest' => $request->reason_for_interest,
        ]);

        return response()->json(['message' => 'Registration submitted successfully. Awaiting workshop facilitator approval.'], 201);
    }

    
    public function unregister(Request $request, $workshopId)
    {
        return DB::transaction(function () use ($request, $workshopId) {
            $workshop = Workshop::findOrFail($workshopId);
            $currentUser = auth()->user();
            $targetUserId = $request->user_id ?? $currentUser->id;
        
            if ($targetUserId !== $currentUser->id && $currentUser->id !== $workshop->animator_id && !$currentUser->hasRole('organizer')) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $submission = DB::table('workshop_registrations')
                ->where('workshop_id', $workshopId)
                ->where('user_id', $targetUserId)
                ->first();

            if (!$submission) {
                return response()->json(['message' => 'Record not found.'], 404);
            }

            // If they were accepted, decrement the count
            if ($submission->status === 'accepted' && $workshop->current_participants > 0) {
                $workshop->decrement('current_participants');
            }

            // Remove the row entirely from the table
            DB::table('workshop_registrations')
                ->where('workshop_id', $workshopId)
                ->where('user_id', $targetUserId)
                ->delete();

            return response()->json([
                'message' => 'User record destroyed. They can now re-register.',
                'current_participants' => $workshop->fresh()->current_participants
            ], 200);
        });
    } 



    public function viewSubmissions($workshopId)
    {
        $workshop = Workshop::findOrFail($workshopId);
        if (auth()->id() !== $workshop->animator_id) {
            return response()->json(['message' => 'Unauthorized. Only the facilitator can view submissions.'], 403);
        }

        $submissions = $workshop->registrationSubmissions()
            ->with('user:id,name,email') 
            ->orderBy('status', 'asc') 
            ->get();

        return response()->json(['submissions' => $submissions]);
    }



    public function moderateRegistration(Request $request, $workshopId, $userId)
    {
        $workshop = Workshop::findOrFail($workshopId);
        if (auth()->id() !== $workshop->animator_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
    
        $request->validate(['action' => 'required|in:accept,decline']);

        $submission = WorkshopRegistration::where('workshop_id', $workshopId)
            ->where('user_id', $userId)
            ->firstOrFail();
        
        if ($submission->status !== 'pending') {
            return response()->json(['message' => 'Submission has already been processed.'], 422);
        }

        if ($request->action === 'accept') {
            if ($workshop->current_participants >= $workshop->max_participants) {
                return response()->json(['message' => 'Workshop is full.'], 422);
            }
        
            // Update status and increment count atomically
            WorkshopRegistration::where('workshop_id', $workshopId)
                ->where('user_id', $userId)
                ->update(['status' => 'accepted']);
        
            $workshop->increment('current_participants');
            $message = 'User accepted into the workshop.';
        
        } else {
            WorkshopRegistration::where('workshop_id', $workshopId)
                ->where('user_id', $userId)
                ->update(['status' => 'declined']);
            $message = 'User declined for the workshop.';
        }

        return response()->json(['message' => $message]);
    }



    public function viewAcceptedParticipants($workshopId)
    {
        $workshop = Workshop::findOrFail($workshopId);
    
        if (auth()->id() !== $workshop->animator_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $acceptedParticipants = $workshop->registrations()
            ->wherePivot('status', 'accepted')
            ->select('users.id', 'users.name', 'users.email') 
            ->orderBy('workshop_registrations.created_at', 'asc')
            ->get();
        
        // Map to flatten the structure and access pivot data
        $participants = $acceptedParticipants->map(function ($user) {
            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->pivot->status,
                'reason_for_interest' => $user->pivot->reason_for_interest,
                'registered_at' => $user->pivot->created_at,
            ];
        });

        return response()->json([
            'current_participants' => $workshop->current_participants,
            'max_participants' => $workshop->max_participants,
            'participants' => $participants
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $eventId, $workshopId)
    {
        //check if the event has started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }

        $workshop = Workshop::where('event_id', $eventId)->findOrFail($workshopId);

        //Check if the workshop has already started
        if ($response = $this->checkItemStarted($workshop->start_time)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'animator_id' => 'sometimes|exists:users,id', 
            'start_time' => 'sometimes|date_format:Y-m-d H:i:s',
            'end_time' => 'sometimes|date_format:Y-m-d H:i:s|after:start_time',
            'room' => 'sometimes|string|max:255',
            'max_participants' => 'sometimes|integer|min:1', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }



        $workshop->update($validator->validated());

        return response()->json([
            'message' => 'Workshop details updated successfully.',
            'period' => $workshop
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($eventId, $workshopId)
    {
        // check if the event has already started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }

        $workshop = Workshop::where('event_id', $eventId)->findOrFail($workshopId);

        // check if the workshop has already started
        if ($response = $this->checkItemStarted($workshop->start_time)) {
            return $response;
        }

        $workshop->delete();
        return response()->json([
            'message'=> 'Workshop deleted successfully.'
            ],200);
    }
}
