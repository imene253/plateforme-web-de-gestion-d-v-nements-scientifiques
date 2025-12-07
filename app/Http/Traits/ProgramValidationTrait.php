<?php

namespace App\Http\Traits;

use App\Models\Event;
use App\Models\Session;
use App\Models\ProgramPeriod;
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
}