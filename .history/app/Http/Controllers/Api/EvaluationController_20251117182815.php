<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\User;
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
            'assigned_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Evaluator assigned successfully',
            'data' => $evaluation->load(['evaluator', 'submission'])
        ], 201);
    }

    /**
     * Get submissions assigned to scientific committee member
     */
    public function myAssignedSubmissions()
    {
        $user = auth()->user();
        
        // Get submissions assigned to this scientific committee member
        $submissions = Submission::whereHas('evaluations', function($query) use ($user) {
            $query->where('evaluator_id', $user->id);
        })->with(['author', 'event', 'evaluations' => function($query) use ($user) {
            $query->where('evaluator_id', $user->id);
        }])->get();

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

    /**
     * Evaluate a submission
     */
    public function evaluateSubmission(Request $request, $submissionId)
    {
        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|min:0|max:100',
            'comments' => 'required|string',
            'recommendation' => 'required|in:accept,reject,needs_revision',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $submission = Submission::find($submissionId);
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        // Check if evaluation already exists
        $existingEvaluation = Evaluation::where('submission_id', $submissionId)
            ->where('evaluator_id', auth()->id())
            ->first();

        if ($existingEvaluation) {
            return response()->json([
                'success' => false,
                'message' => 'You have already evaluated this submission'
            ], 409);
        }

        $evaluation = Evaluation::create([
            'submission_id' => $submissionId,
            'evaluator_id' => auth()->id(),
            'score' => $request->score,
            'comments' => $request->comments,
            'recommendation' => $request->recommendation,
            'evaluated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission evaluated successfully',
            'data' => $evaluation->load(['evaluator', 'submission'])
        ], 201);
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
                'evaluations' => $evaluations
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
            'score' => 'sometimes|integer|min:0|max:100',
            'comments' => 'sometimes|string',
            'recommendation' => 'sometimes|in:accept,reject,needs_revision',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $evaluation->update($request->only(['score', 'comments', 'recommendation']));

        return response()->json([
            'success' => true,
            'message' => 'Evaluation updated successfully',
            'data' => $evaluation->load(['evaluator', 'submission'])
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
}