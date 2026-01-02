<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SessionQuestion;
use App\Models\QuestionVote;
use App\Models\SessionAnswer;
use Illuminate\Http\Request;

class SessionQuestionController extends Controller
{
    public function index($sessionId)
    {
        return SessionQuestion::where('session_id', $sessionId)
            ->with([
                'user:id,name,profile_photo', 
                'answers.user:id,name,profile_photo'
            ])
            ->orderBy('upvotes_count', 'desc') 
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request, $sessionId)
    {
        $request->validate(['content' => 'required|string|max:1000']);

        $question = SessionQuestion::create([
            'session_id' => $sessionId,
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
            'upvotes_count' => 0
        ]);

        return response()->json(['message' => 'Question posted!', 'question' => $question], 201);
    }

    public function storeAnswer(Request $request, $questionId)
    {
        $request->validate(['content' => 'required|string|max:1000']);

        $answer = SessionAnswer::create([
            'session_question_id' => $questionId,
            'user_id' => auth()->id(),
            'content' => $request->input('content') 
        ]);

        return response()->json(['message' => 'Answer posted!', 'answer' => $answer], 201);
    }

    public function toggleUpvote(Request $request, $questionId)
    {
        $userId = auth()->id();
        $question = SessionQuestion::findOrFail($questionId);
        
        $vote = QuestionVote::where('user_id', $userId)
                            ->where('session_question_id', $questionId)
                            ->first();

        if ($vote) {
            $vote->delete();
            $question->decrement('upvotes_count');
            return response()->json(['message' => 'Upvote removed', 'status' => 'unliked']);
        }

        QuestionVote::create([
            'user_id' => $userId,
            'session_question_id' => $questionId
        ]);

        $question->increment('upvotes_count');
        return response()->json(['message' => 'Question upvoted', 'status' => 'liked']);
    }


    public function destroyQuestion($questionId)
    {
        $question = SessionQuestion::findOrFail($questionId);
        $user = auth()->user();

        if ($question->user_id === $user->id || $user->hasRole('event_organizer') || $user->hasRole('super_admin')) {
            $question->delete();
            return response()->json(['message' => 'Question deleted successfully.']);
        }

        return response()->json(['message' => 'Unauthorized. You can only delete your own questions.'], 403);
    }


    public function destroyAnswer($answerId)
    {
        $answer = SessionAnswer::findOrFail($answerId);
        $user = auth()->user();

        if ($answer->user_id === $user->id || $user->hasRole('event_organizer')) {
            $answer->delete();
            return response()->json(['message' => 'Answer deleted successfully.']);
        }
        return response()->json(['message' => 'Unauthorized. You can only delete your own answers.'], 403);
    }
}
