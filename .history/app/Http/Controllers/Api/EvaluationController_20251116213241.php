<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluationController extends Controller
{
    /**
     * (Organizer only)
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
        $evaluator = \App\Models\User::find($request->evaluator_id);
        if (!$evaluator->hasRole('scientific_committee')) {
            return response()->json([
                'success' => false,
                'message' => 'User must have scientific_committee role'
            ], 400);
        }

        // Check if already assigned
        // each evaluator can evaluate a submission only once
        $exists = Evaluation::where('submission_id', $request->submission_id)
            ->where('evaluator_id', $request->evaluator_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This evaluator is already assigned to this submission'
            ], 400);
        }

        $evaluation = Evaluation::create([
            'submission_id' => $request->submission_id,
            'evaluator_id' => $request->evaluator_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Evaluator assigned successfully',
            'data' => $evaluation->load(['submission', 'evaluator'])
        ], 201);
    }

    /**
     * عرض المقترحات المخصصة لي للتقييم (Scientific Committee)
     */
    public function myAssignedSubmissions()
    {
        $evaluations = Evaluation::with(['submission.event', 'submission.author'])
            ->where('evaluator_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $evaluations
        ]);
    }

    /**
     * تقييم مقترح (Scientific Committee only)
     */
    public function evaluateSubmission(Request $request, $submissionId)
    {
        // Find the evaluation for this user and submission
        $evaluation = Evaluation::where('submission_id', $submissionId)
            ->where('evaluator_id', auth()->id())
            ->first();

        if (!$evaluation) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to evaluate this submission'
            ], 403);
        }

        if ($evaluation->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'You have already completed this evaluation'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'relevance_score' => 'required|integer|min:1|max:10',
            'scientific_quality_score' => 'required|integer|min:1|max:10',
            'originality_score' => 'required|integer|min:1|max:10',
            'comments' => 'required|string|min:50',
            'recommendation' => 'required|in:accept,reject,revision',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
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
            'message' => 'Evaluation submitted successfully',
            'data' => [
                'evaluation' => $evaluation,
                'total_score' => $evaluation->total_score,
                'average_score' => $evaluation->average_score,
            ]
        ]);
    }

    /**
     * عرض تقييمات مقترح معين (Organizer or Author)
     */
    public function getSubmissionEvaluations($submissionId)
    {
        $submission = Submission::with(['evaluations.evaluator:id,name,institution'])
            ->find($submissionId);

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        // Check permissions
        $user = auth()->user();
        $isAuthor = $submission->author_id === $user->id;
        $isOrganizer = $user->hasRole(['event_organizer', 'scientific_committee']);

        if (!$isAuthor && !$isOrganizer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // If author, hide evaluator names and show only completed evaluations
        $evaluations = $submission->evaluations;
        if ($isAuthor) {
            $evaluations = $evaluations->where('is_completed', true)->map(function ($eval) {
                return [
                    'id' => $eval->id,
                    'relevance_score' => $eval->relevance_score,
                    'scientific_quality_score' => $eval->scientific_quality_score,
                    'originality_score' => $eval->originality_score,
                    'comments' => $eval->comments,
                    'recommendation' => $eval->recommendation,
                    'total_score' => $eval->total_score,
                    'average_score' => $eval->average_score,
                    'created_at' => $eval->created_at,
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => [
                    'id' => $submission->id,
                    'title' => $submission->title,
                    'status' => $submission->status,
                ],
                'evaluations' => $evaluations,
                'average_evaluation_score' => $submission->average_evaluation_score,
                'total_evaluations' => $submission->evaluations->where('is_completed', true)->count(),
            ]
        ]);
    }

    /**
     * تحديث تقييم (Scientific Committee)
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
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'relevance_score' => 'sometimes|integer|min:1|max:10',
            'scientific_quality_score' => 'sometimes|integer|min:1|max:10',
            'originality_score' => 'sometimes|integer|min:1|max:10',
            'comments' => 'sometimes|string|min:50',
            'recommendation' => 'sometimes|in:accept,reject,revision',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $evaluation->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Evaluation updated successfully',
            'data' => $evaluation
        ]);
    }

    /**
     * حذف تقييم (Organizer only)
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