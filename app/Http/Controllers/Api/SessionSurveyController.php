<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\SessionSurvey;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SessionSurveyController extends Controller
{
    public function store(Request $request, $sessionId)
    {
        $request->validate([
            'quality' => 'required|integer|min:1|max:5',
            'relevance' => 'required|integer|min:1|max:5',
            'organization' => 'required|integer|min:1|max:5',
        ]);

        $session = Session::findOrFail($sessionId);

        $now = now();
        $endTime = Carbon::parse($session->end_time);
        $expiryTime = $endTime->copy()->addMinutes(3);

        if ($now->lt($endTime)) {
            return response()->json([
                'message' => 'The session has not ended yet. Survey will open at ' . $endTime->toTimeString()
            ], 403);
        }

        if ($now->gt($expiryTime)) {
            return response()->json([
                'message' => 'The survey window has closed. It was only available for 3 minutes after the session.'
            ], 403);
        }

        $exists = SessionSurvey::where('session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already submitted a survey for this session.'], 400);
        }

        $survey = SessionSurvey::create([
            'session_id' => $sessionId,
            'user_id' => auth()->id(),
            'quality' => $request->quality,
            'relevance' => $request->relevance,
            'organization' => $request->organization,
        ]);

        return response()->json([
            'message' => 'Thank you for your feedback!',
            'survey' => $survey
        ], 201);
    }

    public function getResults($sessionId)
    {
        $session = Session::findOrFail($sessionId);

        $stats = SessionSurvey::where('session_id', $sessionId)
            ->select(
                DB::raw('AVG(quality) as avg_quality'),
                DB::raw('AVG(relevance) as avg_relevance'),
                DB::raw('AVG(organization) as avg_organization'),
                DB::raw('COUNT(*) as total_responses')
            )
            ->first();

        return response()->json([
            'session_title' => $session->title,
            'results' => [
                'quality' => round($stats->avg_quality, 2),
                'relevance' => round($stats->avg_relevance, 2),
                'organization' => round($stats->avg_organization, 2),
                'total_participants' => $stats->total_responses
            ]
        ]);
    }
}
