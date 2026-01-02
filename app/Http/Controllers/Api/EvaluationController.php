<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluationController extends Controller
{
    /**
     * Assign evaluator to submission (event_organizer only)
     */
    public function assignEvaluator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'submission_id' => 'required|exists:submissions,id',
            'evaluator_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if evaluator has scientific_committee role
        $evaluator = User::find($request->evaluator_id);
        if (!$evaluator->hasRole('scientific_committee')) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user is not a scientific committee member'
            ], 400);
        }

        // Check if evaluation already exists
        $existingEvaluation = Evaluation::where('submission_id', $request->submission_id)
            ->where('evaluator_id', $request->evaluator_id)
            ->first();

        if ($existingEvaluation) {
            return response()->json([
                'success' => false,
                'message' => 'This evaluator is already assigned to this submission'
            ], 409);
        }

        $evaluation = Evaluation::create([
            'submission_id' => $request->submission_id,
            'evaluator_id' => $request->evaluator_id,
            'is_completed' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Evaluator assigned successfully',
            'data' => $evaluation->load(['evaluator:id,name,email', 'submission:id,title'])
        ], 201);
    }

    /**
     * Get submissions assigned to scientific committee member
     */
    public function myAssignedSubmissions()
    {
        $user = auth()->user();
        
        $evaluations = Evaluation::where('evaluator_id', $user->id)
            ->with(['submission.author:id,name,email', 'submission.event:id,title'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $evaluations
        ]);
    }

    /**
     * Evaluate a submission
     */
    public function evaluateSubmission(Request $request, $submissionId)
    {
        $validator = Validator::make($request->all(), [
            'relevance_score' => 'required|integer|min:1|max:10',
            'scientific_quality_score' => 'required|integer|min:1|max:10',
            'originality_score' => 'required|integer|min:1|max:10',
            'comments' => 'required|string',
            'recommendation' => 'required|in:accept,reject,revision',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $evaluation = Evaluation::where('submission_id', $submissionId)
            ->where('evaluator_id', auth()->id())
            ->first();

        if (!$evaluation) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to evaluate this submission'
            ], 404);
        }

        if ($evaluation->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'You have already completed the evaluation for this submission'
            ], 409);
        }

        $evaluation->update([
            'relevance_score' => $request->relevance_score,
            'scientific_quality_score' => $request->scientific_quality_score,
            'originality_score' => $request->originality_score,
            'comments' => $request->comments,
            'recommendation' => $request->recommendation,
            'is_completed' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission evaluated successfully',
            'data' => $evaluation->load(['evaluator:id,name,email', 'submission:id,title'])
        ]);
    }

    /**
     * Get evaluations for a submission
     */
    public function getSubmissionEvaluations($submissionId)
    {
        $submission = Submission::find($submissionId);
        
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        $user = auth()->user();
        
        // Check if user has permission to view evaluations
        if (!($user->hasRole(['event_organizer', 'scientific_committee']) || $submission->author_id === $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view evaluations for your own submissions or if you are an organizer/committee member.'
            ], 403);
        }

        $evaluations = $submission->evaluations()->with('evaluator:id,name,email')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => $submission->load('author:id,name,email'),
                'evaluations' => $evaluations,
                'average_score' => $submission->average_evaluation_score
            ]
        ]);
    }

    /**
     * Update evaluation
     */
    public function update(Request $request, $id)
    {
        $evaluation = Evaluation::find($id);
        
        if (!$evaluation) {
            return response()->json([
                'success' => false,
                'message' => 'Evaluation not found'
            ], 404);
        }

        // Check if user is the evaluator
        if ($evaluation->evaluator_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only update your own evaluations.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'relevance_score' => 'sometimes|integer|min:1|max:10',
            'scientific_quality_score' => 'sometimes|integer|min:1|max:10',
            'originality_score' => 'sometimes|integer|min:1|max:10',
            'comments' => 'sometimes|string',
            'recommendation' => 'sometimes|in:accept,reject,revision',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $evaluation->update($request->only([
            'relevance_score', 
            'scientific_quality_score', 
            'originality_score', 
            'comments', 
            'recommendation'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Evaluation updated successfully',
            'data' => $evaluation->load(['evaluator:id,name,email', 'submission:id,title'])
        ]);
    }

    /**
     * Delete evaluation (event_organizer only)
     */
    public function destroy($id)
    {
        $evaluation = Evaluation::find($id);
        
        if (!$evaluation) {
            return response()->json([
                'success' => false,
                'message' => 'Evaluation not found'
            ], 404);
        }

        $evaluation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evaluation deleted successfully'
        ]);
    }

    public function getEventScientificCommittee($eventId)
    {
              $event = Event::find($eventId);
        
        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }
        
        
        $committeeMembers = User::whereHas('roles', function($query) {
            $query->where('name', 'scientific_committee');
        })
        ->where('is_active', true)
        ->select('id', 'name', 'email', 'institution', 'research_domain', 'photo_path')
        ->orderBy('name')
        ->get();
        
        return response()->json([
            'success' => true,
            'data' => $committeeMembers
        ]);
    }
}

    
