<?php

namespace App\Http\Traits;

use App\Models\Event;
use App\Models\Session;
use App\Models\ProgramPeriod;
use App\Models\Workshop;
use Illuminate\Support\Carbon;

trait ProgramValidationTrait
{
    // this function checks if the event has started
    protected function checkEventStarted($eventId)
    {
        $event = Event::findOrFail($eventId);
        
        if (Carbon::now()->gte(Carbon::parse($event->start_date)->startOfDay())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify the program. The event has already started.'
            ], 403);
        }
        return null; 
    }

    // this function checks if the session/period has started
    protected function checkItemStarted($startTime)
    {
        if (Carbon::now()->gte(Carbon::parse($startTime))) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify this item. Its scheduled time has already passed.'
            ], 403);
        }
        return null;
    }


    // this function checks if there is an overlap between the new time slot (start/end) and existing Sessions and Periods.
    protected function checkTimeOverlap($eventId, $startTime, $endTime, $excludeId = null, $type = null)
    {
        //  Check Overlap with Sessions
        $sessionOverlapQuery = Session::where('event_id', $eventId)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            });
            
        // Exclude the current session if we are updating it
        if ($type == 'session' && $excludeId) {
            $sessionOverlapQuery->where('id', '!=', $excludeId);
        }

        if ($sessionOverlapQuery->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'The proposed time slot overlaps with an existing Session.'
            ], 422);
        }

        // Check Overlap with Program Periods
        $periodOverlapQuery = ProgramPeriod::where('event_id', $eventId)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            });
            
        // Exclude the current period if we are updating it
        if ($type == 'period' && $excludeId) {
            $periodOverlapQuery->where('id', '!=', $excludeId);
        }
        
        if ($periodOverlapQuery->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'The proposed time slot overlaps with an existing Program Period (e.g., a break).'
            ], 422);
        }

        return null;
    }


    // this function checks if there is an overlap between Workshops/Sessions/Periods in the same room in the same time.
    protected function checkRoomAvailability($event_id, $room, $startTime, $endTime, $ignoreId = null, $modelName = null)
    {
    // Check against existing Sessions
    $sessionQuery = Session::where('event_id', $event_id)
        ->where('room', $room)
        ->where(function ($q) use ($startTime, $endTime) {
            $q->whereBetween('start_time', [$startTime, $endTime])
              ->orWhereBetween('end_time', [$startTime, $endTime])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('start_time', '<', $startTime)->where('end_time', '>', $endTime);
              });
        });

    if ($modelName === 'Session' && $ignoreId) {
        $sessionQuery->where('id', '!=', $ignoreId);
    }
    
    if ($sessionQuery->exists()) {
        return response()->json(['message' => "Validation Error: Room '$room' is already occupied by a Session at this time."], 422);
    }


    // Check against existing Program Periods
    $periodQuery = ProgramPeriod::where('event_id', $event_id)
        ->where('room', $room)
        ->where(function ($q) use ($startTime, $endTime) {
            $q->whereBetween('start_time', [$startTime, $endTime])
              ->orWhereBetween('end_time', [$startTime, $endTime])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('start_time', '<', $startTime)->where('end_time', '>', $endTime);
              });
        });
    
    if ($modelName === 'Period' && $ignoreId) {
        $periodQuery->where('id', '!=', $ignoreId);
    }
    
    if ($periodQuery->exists()) {
        return response()->json(['message' => "Validation Error: Room '$room' is already reserved by a Program Period at this time."], 422);
    }


    // Check against existing Workshops 
    $workshopQuery = Workshop::where('event_id', $event_id)
        ->where('room', $room)
        ->where(function ($q) use ($startTime, $endTime) {
            // Overlap logic (same as above)
            $q->whereBetween('start_time', [$startTime, $endTime])
              ->orWhereBetween('end_time', [$startTime, $endTime])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('start_time', '<', $startTime)->where('end_time', '>', $endTime);
              });
        });

    if ($modelName === 'Workshop' && $ignoreId) {
        $workshopQuery->where('id', '!=', $ignoreId);
    }
    
    if ($workshopQuery->exists()) {
        return response()->json(['message' => "Validation Error: Room '$room' is already occupied by a Workshop at this time."], 422);
    }


    return null; // = Room is available
    }


    // this function checks if a program item (Session, Period, Workshop) is within the event's start and end dates.
    protected function checkItemAgainstEventDates($eventId, $startTime, $endTime)
    {
        $event = Event::findOrFail($eventId);
        // startOfDay and endOfDay for inclusive boundary checking
        $eventStartDate = Carbon::parse($event->start_date)->startOfDay();
        $eventEndDate = Carbon::parse($event->end_date)->endOfDay();

        $itemStartTime = Carbon::parse($startTime);
        $itemEndTime = Carbon::parse($endTime);

        // Check if item starts before the event starts OR item ends after the event ends
        if ($itemStartTime->lt($eventStartDate) || $itemEndTime->gt($eventEndDate)) {
            return response()->json([
                'success' => false,
                'message' => 'The proposed item time slot is outside the event\'s scheduled dates.'
            ], 422);
        }

        return null; 
    }
}