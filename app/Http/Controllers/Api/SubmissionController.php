<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    /**
     * عرض كل المقترحات (للمنظم فقط)
     */
    public function index(Request $request)
    {
        $query = Submission::with(['event:id,title', 'author:id,name,email,institution']);

        // Filter by event
        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $submissions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

    /**
     * عرض مقترحات المستخدم الحالي
     */
    public function mySubmissions()
    {
        $submissions = Submission::with('event:id,title,start_date,end_date')
            ->where('author_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

    /**
     * إنشاء مقترح جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'title' => 'required|string|max:255',
            'authors' => 'required|array|min:1',
            // abstract is a Résumé
            'abstract' => 'required|string|min:100',
            'keywords' => 'required|array|min:3',
            'type' => 'required|in:oral,poster,affiche',
            'pdf_file' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if event exists and is upcoming
        $event = Event::find($request->event_id);
        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }

        $data = [
            'event_id' => $request->event_id,
            'author_id' => auth()->id(),
            'title' => $request->title,
            'authors' => $request->authors,
            'abstract' => $request->abstract,
            'keywords' => $request->keywords,
            'type' => $request->type,
        ];

        // Handle PDF upload
        if ($request->hasFile('pdf_file')) {
            $path = $request->file('pdf_file')->store('submissions', 'public');
            $data['pdf_file'] = $path;
        }

        $submission = Submission::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Submission created successfully',
            'data' => $submission->load(['event', 'author'])
        ], 201);
    }

    /**
     * عرض تفاصيل مقترح
     */
    public function show($id)
    {
        $submission = Submission::with(['event', 'author'])->find($id);

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        // Check permissions
        $user = auth()->user();
        if ($submission->author_id !== $user->id && !$user->hasRole(['event_organizer', 'scientific_committee'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $submission
        ]);
    }

    /**
     * تحديث مقترح (Author only, قبل الموعد النهائي)
     */
    public function update(Request $request, $id)
    {
        $submission = Submission::find($id);

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        // Check if user is the author
        if ($submission->author_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not the author of this submission.'
            ], 403);
        }

        // Check if submission is still pending
        if ($submission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update submission. It has already been reviewed.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'authors' => 'sometimes|array|min:1',
            'abstract' => 'sometimes|string|min:100',
            'keywords' => 'sometimes|array|min:3',
            'type' => 'sometimes|in:oral,poster,affiche',
            'pdf_file' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['title', 'authors', 'abstract', 'keywords', 'type']);

        // Handle PDF upload
        if ($request->hasFile('pdf_file')) {
            // Delete old PDF
            if ($submission->pdf_file) {
                Storage::delete('public/' . $submission->pdf_file);
            }
            $path = $request->file('pdf_file')->store('submissions', 'public');
            $data['pdf_file'] = $path;
        }

        $submission->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Submission updated successfully',
            'data' => $submission->load(['event', 'author'])
        ]);
    }

    /**
     * حذف مقترح (Author only, قبل التقييم)
     */
    public function destroy($id)
    {
        $submission = Submission::find($id);

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        // Check if user is the author
        if ($submission->author_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not the author of this submission.'
            ], 403);
        }

        // Check if submission is still pending
        if ($submission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete submission. It has already been reviewed.'
            ], 400);
        }

        // Delete PDF file
        if ($submission->pdf_file) {
            Storage::delete('public/' . $submission->pdf_file);
        }

        $submission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Submission deleted successfully'
        ]);
    }

    /**
     * تحديث حالة المقترح (Organizer or Scientific Committee only)
     */
    public function updateStatus(Request $request, $id)
    {
        $submission = Submission::find($id);

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,accepted,rejected,revision',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $submission->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission status updated successfully',
            'data' => $submission->load(['event', 'author'])
        ]);
    }

    /**
     * ✅ Download submission PDF (auth + role protected by routes)
     */
    public function downloadPdf($id)
    {
        $submission = Submission::find($id);

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        if (!$submission->pdf_file) {
            return response()->json([
                'success' => false,
                'message' => 'No PDF file found for this submission'
            ], 404);
        }

        if (!Storage::disk('public')->exists($submission->pdf_file)) {
            return response()->json([
                'success' => false,
                'message' => 'PDF file not found on server'
            ], 404);
        }

        return Storage::disk('public')->download($submission->pdf_file);
    }
}
