<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Event;
use App\Http\Traits\ProgramValidationTrait;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    use ProgramValidationTrait;
    // THIS function shows the Program (sessions, presentations, periods), ordered and grouped by day.
    public function showProgram($eventId)
    {
        $event = Event::with([
            'sesions.submissions' => function ($query) { 
                 // Only include submissions that have been assigned a specific time slot
                 $query->whereNotNull('presentation_start_time')
                       ->orderBy('presentation_start_time');
            },
            'programPeriods'
        ])->findOrFail($eventId);
        
        $programItems = collect();

        // Process Sessions (and their Presentations)
        foreach ($event->sesions as $session) {

            $programItems->push([
                'type' => 'session',
                'id' => $session->id,
                'title' => $session->title,
                'room' => $session->room,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'data' => $session->only(['session_chair_id', 'event_id']),
            ]);
            
            // each Presentation/Submission within the session as its own item
            foreach ($session->submissions as $submission) {
                $programItems->push([
                    'type' => 'presentation',
                    'id' => $submission->id,
                    'title' => $submission->title,
                    'session_id' => $session->id,
                    'start_time' => $submission->presentation_start_time,
                    'end_time' => $submission->presentation_end_time,
                    'data' => $submission->only(['type', 'authors', 'event_id']), 
                ]);
            }
        }

        // Process Program Periods (Breaks)
        foreach ($event->programPeriods as $period) {
            $programItems->push([
                'type' => 'period',
                'id' => $period->id,
                'title' => $period->title,
                'room' => $period->room,
                'start_time' => $period->start_time,
                'end_time' => $period->end_time,
                'data' => $period->only(['description', 'event_id']),
            ]);
        }
        
        // Combine, Sort, and Group by Day
        
        // all items stored chronologically by start_time
        $sortedProgram = $programItems->sortBy(function ($item) {
            return $item['start_time'];
        });
        
        // Group the sorted items by date (Y-m-d)
        $programByDay = $sortedProgram->groupBy(function ($item) {
            return Carbon::parse($item['start_time'])->format('Y-m-d');
        });

        return response()->json([
            'success' => true,
            'event_title' => $event->title,
            'program' => $programByDay, // The fully structured program
        ], 200);
    }

    public function store(Request $request, $eventId) {
        Event::findOrFail($eventId);
        // checks if the event has already started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'room' => 'required|string|max:100',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'session_chair_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $startTime = $validatedData['start_time'];
        $endTime = $validatedData['end_time'];

        // Check if the Start/End time is within the event Start/End date
        if ($response = $this->checkItemAgainstEventDates($eventId, $startTime, $endTime)) {
            return $response;
        }

        // Check for overlap with existing sessions/periods
        if ($response = $this->checkTimeOverlap($eventId, $startTime, $endTime)) {
            return $response;
        }

        $session = Session::create(array_merge($validatedData, ['event_id' => $eventId]));
        return response()->json($session, 201);
    }


    
    public function update(Request $request, $eventId, $sessionId)
    {
        // checks if the event has already started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }

        $session = Session::where('event_id', $eventId)->findOrFail($sessionId);

        // checks if the session has already started
        if ($response = $this->checkItemStarted($session->start_time)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'room' => 'sometimes|string|max:100',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'session_chair_id' => 'nullable|exists:users,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $validatedData = $validator->validated();
        $startTime = $validatedData['start_time'] ?? $session->start_time;
        $endTime = $validatedData['end_time'] ?? $session->end_time;
        
        // Check for overlap
        if ($response = $this->checkTimeOverlap($eventId, $startTime, $endTime, $sessionId, 'session')) {
            return $response;
        }

        $session->update($validatedData);

        return response()->json([
            'message' => 'Session updated successfully.',
            'session' => $session
        ]);
    }


    public function destroy($eventId, $sessionId)
    {
        // 1. Locate the item and check global lock
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }
        
        $session = Session::where('event_id', $eventId)->findOrFail($sessionId);
        
        // 2. Check if the session has already started (Requirement 3)
        if ($response = $this->checkItemStarted($session->start_time)) {
            return $response;
        }

        //* THIS will unasign any submission/presentation that was previously assigned to this session

        \App\Models\Submission::where('session_id', $session->id)->update([
        'session_id' => null, 
        'presentation_start_time' => null,
        'presentation_end_time' => null,
    ]);



        $session->delete();

        return response()->json(['message' => 'Session deleted successfully.'], 200);
    }



    // 3. ORGANIZER ROUTE: POST /api/events/{eventId}/sessions/{sessionId}/assign-submission
    public function assignSubmission(Request $request, $eventId, $sessionId) {
        // FIX: Ensure you are validating against the correct field name in the request
        $request->validate(['submission_id' => 'required|exists:submissions,id']);

        $session = Session::where('event_id', $eventId) -> findOrFail($sessionId);
        $submission = \App\Models\Submission::where('event_id', $eventId)->findOrFail($request->submission_id);

        // Only assign if the submission has been accepted (status=accepted)
        if ($submission->status !== 'accepted') {
            return response()->json([
                'message' => 'Cannot assign. Submission must have an "accepted" status first.'
            ], 403); 
        }

        $submission->session_id = $session->id;
        $submission->save();

        return response()->json([
            'message' => 'Submission assigned successfully to session ' . $session->title,
            'submission' => $submission
        ]);
    }



    public function updatePresentationTime(Request $request, $eventId, $sessionId, $submissionId)
    {
        // check if the event started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }

        // 1. Locate the Session and Submission
        $session = Session::where('event_id', $eventId)->findOrFail($sessionId);
        //check overlapping 
        if ($response = $this->checkItemStarted($session->start_time)) {
            return $response;
        }
        $submission = \App\Models\Submission::where('event_id', $eventId)
                                        ->where('session_id', $sessionId)
                                        ->findOrFail($submissionId);

        // 2. Validation
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $start_time = $request->start_time;
        $end_time = $request->end_time;

        // --- Core Validation Checks (Requirements 3 & 5) ---

        // A. Check if the presentation time is within the parent session time.
        if (! (Carbon::parse($start_time)->gte(Carbon::parse($session->start_time)) &&
          Carbon::parse($end_time)->lte(Carbon::parse($session->end_time))) ) 
        {
        return response()->json([
            'message' => 'Presentation time must be entirely within the session time slot (' 
                       . $session->start_time . ' to ' . $session->end_time . ').'
        ], 422);
        }

        // B. Check for overlap with other presentations in the same session.
        $overlappingPresentations = \App\Models\Submission::where('session_id', $sessionId)
            ->where('id', '!=', $submissionId) // Exclude the current submission
            ->where(function ($query) use ($start_time, $end_time) {
                $query->where(function ($q) use ($start_time, $end_time) {
                // Check if the new time range starts during an existing presentation
                $q->where('presentation_start_time', '<', $end_time)
                  ->where('presentation_end_time', '>', $start_time);
            });
        })
        ->exists();

        if ($overlappingPresentations) {
        return response()->json([
            'message' => 'The proposed presentation time overlaps with another presentation in this session.'
        ], 422);
        }
    
        // TODO: C. Add global check: editing is only allowed BEFORE the session starts (Phase 3, Task 5)

        // 3. Update the Submission/Presentation Times
        $submission->presentation_start_time = $start_time;
        $submission->presentation_end_time = $end_time;
        $submission->save();

        return response()->json([
            'message' => 'Presentation time successfully set.',
            'submission' => $submission
        ]);
    }
}