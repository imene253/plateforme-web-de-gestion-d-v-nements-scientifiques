<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ProgramPeriod;
use App\Http\Traits\ProgramValidationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProgramPeriodController extends Controller
{
    use ProgramValidationTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        // check if the event has started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'room' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        Event::findOrFail($eventId);

        //check for overlap
        if ($response = $this->checkTimeOverlap($eventId, $validatedData['start_time'], $validatedData['end_time'])) {
            return $response;
        }

        $period = ProgramPeriod::create(array_merge($validatedData, [
            'event_id' => $eventId
        ]));

        return response()->json([
            'message' => 'Program period created successfully.',
            'period' => $period
        ], 201);
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
    public function update(Request $request, $eventId, $periodId)
    {
        //check if the event has started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }

        $period = ProgramPeriod::where('event_id', $eventId)->findOrFail($periodId);

        //Check if the period has already started
        if ($response = $this->checkItemStarted($period->start_time)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'room' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $startTime = $validatedData['start_time'] ?? $period->start_time;
        $endTime = $validatedData['end_time'] ?? $period->end_time;

        // Check for overlap
        if ($response = $this->checkTimeOverlap($eventId, $startTime, $endTime, $periodId, 'period')) {
            return $response;
        }

        $period->update($validator->validated());

        return response()->json([
            'message' => 'Program period updated successfully.',
            'period' => $period
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($eventId, $periodId)
    {
        // check if the event has already started
        if ($response = $this->checkEventStarted($eventId)) {
            return $response;
        }

        $period = ProgramPeriod::where('event_id', $eventId)->findOrFail($periodId);

        // check if the period has already started
        if ($response = $this->checkItemStarted($period->start_time)) {
            return $response;
        }

        $period->delete();
        return response()->json([
            'message'=> 'Program period deleted successfully.'
            ],200);
    }
}
