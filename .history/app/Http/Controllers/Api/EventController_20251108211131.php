<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class EventController extends Controller
{
    /**
     * عرض كل الفعاليات (public)
     */
    public function index()
    {
        $events = Event::with('organizer:id,name,email,institution')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * إنشاء فعالية جديدة (event_organizer only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'required|string|max:255',
            'theme' => 'required|string|max:255',
            'contact_email' => 'required|email',
            'contact_phone' => 'nullable|string|max:20',
            'scientific_committee' => 'nullable|array',
            'invited_speakers' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $event = Event::create([
            'organizer_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'location' => $request->location,
            'theme' => $request->theme,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'scientific_committee' => $request->scientific_committee,
            'invited_speakers' => $request->invited_speakers,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => $event->load('organizer')
        ], 201);
    }

    /**
     * عرض تفاصيل فعالية
     */
    public function show($id)
    {
        $event = Event::with('organizer:id,name,email,institution')->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $event
        ]);
    }

    /**
     * تحديث فعالية (organizer only)
     */
    public function update(Request $request, $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        // التحقق من أن المستخدم هو المنظم
        if ($event->organizer_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not the organizer of this event.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'location' => 'sometimes|string|max:255',
            'theme' => 'sometimes|string|max:255',
            'contact_email' => 'sometimes|email',
            'contact_phone' => 'nullable|string|max:20',
            'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled',
            'scientific_committee' => 'nullable|array',
            'invited_speakers' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $event->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $event->load('organizer')
        ]);
    }

    /**
     * حذف فعالية (organizer only)
     */
    public function destroy($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        // التحقق من أن المستخدم هو المنظم
        if ($event->organizer_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not the organizer of this event.'
            ], 403);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }
}