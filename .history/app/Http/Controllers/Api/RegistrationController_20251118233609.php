<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegistrationController extends Controller
{
    /**
     * Register for an event
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'registration_type' => 'required|in:participant,author,guest_speaker,workshop_facilitator',
            'additional_info' => 'nullable|array',
            'additional_info.dietary_requirements' => 'nullable|string',
            'additional_info.accommodation_needed' => 'nullable|boolean',
            'additional_info.transportation_needed' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        // get event and user
        $event = Event::findOrFail($request->event_id);
        $user = auth()->user();

        // Check if already registered
        $existingRegistration = Registration::where('event_id', $request->event_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'success' => false,
                'message' => 'You are already registered for this event'
            ], 409);
        }

        // Calculate registration fee 
        $amount = $this->calculateRegistrationFee($request->registration_type, $event);

        $registration = Registration::create([
            'event_id' => $request->event_id,
            'user_id' => $user->id,
            'registration_type' => $request->registration_type,
            'amount' => $amount,
            'additional_info' => $request->additional_info,
            'payment_status' => 'unpaid',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم التسجيل بنجاح',
            'data' => [
                'registration' => $registration->load(['event:id,title', 'user:id,name,email']),
                'amount_due' => $amount,
                'payment_instructions' => 'يرجى دفع الرسوم في مكان الحدث أو عبر التحويل البنكي'
            ]
        ], 201);
    }

    /**
     * Get my registrations
     */
    public function myRegistrations()
    {
        $registrations = auth()->user()
          // registrations relation defined in User model
            ->registrations()
            ->with(['event:id,title,start_date,end_date,location'])
            ->orderBy('registered_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $registrations
        ]);
    }

    /**
     * Get event registrations (organizer only)
     */
    public function eventRegistrations($eventId)
    {
        $event = Event::findOrFail($eventId);
        
        // Check if user is organizer of this event
        if ($event->organizer_id !== auth()->id() && !auth()->user()->hasRole(['super_admin', 'event_organizer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $registrations = $event->registrations()
            ->with(['user:id,name,email,institution', 'event:id,title'])
            ->get();

        $statistics = [
            'total_registrations' => $registrations->count(),
            'paid_registrations' => $registrations->where('payment_status', 'paid')->count(),
            'unpaid_registrations' => $registrations->where('payment_status', 'unpaid')->count(),
            'total_revenue' => $registrations->where('payment_status', 'paid')->sum('amount'),
            'pending_revenue' => $registrations->where('payment_status', 'unpaid')->sum('amount'),
            'by_type' => $registrations->groupBy('registration_type')->map->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'registrations' => $registrations,
                'statistics' => $statistics
            ]
        ]);
    }

    /**
     * Update payment status (organizer only)
     */
    public function updatePaymentStatus(Request $request, $registrationId)
    {
        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|in:paid,unpaid,pending,refunded',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,online',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $registration = Registration::findOrFail($registrationId);
        
        // Check authorization
        if ($registration->event->organizer_id !== auth()->id() && !auth()->user()->hasRole(['super_admin', 'event_organizer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
       // array with inforamtion to update
        $updateData = [
            'payment_status' => $request->payment_status,
            'notes' => $request->notes,
        ];

        if ($request->payment_status === 'paid') {
            $updateData['payment_date'] = now();
            $updateData['payment_method'] = $request->payment_method;
            $updateData['is_confirmed'] = true;
        }

        $registration->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الدفع بنجاح',
            'data' => $registration->load(['user:id,name,email', 'event:id,title'])
        ]);
    }

    /**
     * Cancel registration
     */
    public function cancelRegistration($registrationId)
    {
        $registration = Registration::findOrFail($registrationId);
        
        // Check if user owns this registration
        if ($registration->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if event hasn't started yet
        if ($registration->event->start_date <= now()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel registration for events that have already started'
            ], 400);
        }

        $registration->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء التسجيل بنجاح'
        ]);
    }

    /**
     * Get registration badge data
     */
    public function getBadgeData($registrationId)
    {
        $registration = Registration::findOrFail($registrationId);
        
        // Check authorization
        if ($registration->user_id !== auth()->id() && 
            $registration->event->organizer_id !== auth()->id() && 
            !auth()->user()->hasRole(['super_admin', 'event_organizer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!$registration->isConfirmed()) {
            return response()->json([
                'success' => false,
                'message' => 'Registration is not confirmed yet'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $registration->generateBadgeData()
        ]);
    }

    /**
     * Calculate registration fee based on type and event
     */
    private function calculateRegistrationFee($registrationType, $event)
    {
        // You can customize this logic based on your requirements
        $fees = [
            'participant' => 1000, 
            'author' => 1500,      
            'guest_speaker' => 0,  
            'workshop_facilitator' => 0, // Free for facilitators
        ];

        return $fees[$registrationType] ?? 1000;
    }
}