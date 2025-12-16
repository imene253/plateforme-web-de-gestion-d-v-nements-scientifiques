<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Models\WorkshopMaterial;
use App\Models\WorkshopRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class WorkshopMaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($workshopId)
    {
        $workshop = Workshop::findOrFail($workshopId);
        $userId = auth()->id();

        $isAccepted = WorkshopRegistration::where('workshop_id', $workshopId)
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->exists();

        $isFacilitator = ($userId === $workshop->animator_id);

        if (!$isAccepted && !$isFacilitator) {
        return response()->json(['message' => 'Access denied. Only accepted participants can view materials.'], 403);
        }

        // Fetch all materials
        $materials = WorkshopMaterial::where('workshop_id', $workshopId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'workshop_title' => $workshop->title,
            'materials' => $materials
        ], 200);
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
    public function store(Request $request, $workshopId)
    {
        // Check if the current user is the workshop animator
        $workshop = Workshop::findOrFail($workshopId);
        if (auth()->id() !== $workshop->animator_id) {
            return response()->json([
                'message' => 'Unauthorized. Only the workshop animator can upload materials.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'type' => 'required|in:pdf,link,video', 
            'file' => 'required_if:type,pdf|file|mimes:pdf|max:10240', // PDF max 10MB
            'external_url' => 'required_if:type,link,video|url|max:2048', // External link validation
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $filePath = null;
        $externalUrl = $request->external_url;

        // Handle File Upload (if type is 'pdf')
        if ($request->type === 'pdf' && $request->hasFile('file')) {
            try {
                // Store the PDF in the 'workshop_materials' disk
                $filePath = $request->file('file')->store('workshop_materials/' . $workshopId, 'public');
                $externalUrl = null; // Clear URL if a file is uploaded
            } catch (\Exception $e) {
                return response()->json(['message' => 'File upload failed.'], 500);
            }
        } 
        
        // Creation
        $material = WorkshopMaterial::create([
            'workshop_id' => $workshopId,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'file_path' => $filePath, 
            'external_url' => $externalUrl,
            'is_public' => $request->is_public ?? false,
        ]);

        return response()->json([
            'message' => 'Material successfully added.',
            'material' => $material
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($workshopId, $materialId)
    {
        $material = WorkshopMaterial::where('workshop_id', $workshopId)->findOrFail($materialId);
        $userId = auth()->id();
        
        $isRegistered = $material->workshop->registrations()->where('user_id', $userId)->exists();
        
        if (!$material->is_public && $userId !== $material->workshop->animator_id && !$isRegistered) {
             return response()->json(['message' => 'Unauthorized access to this material.'], 403);
        }
        
        if ($material->file_path && ($material->type === 'pdf' || $material->type === 'file')) {
            if (!Storage::disk('public')->exists($material->file_path)) {
                return response()->json([
                'message' => 'File not found on server.',
                'debug_path' => $material->file_path
                ], 404);
            }
        $absolutePath = storage_path('app/public/' . $material->file_path);

        return response()->download($absolutePath, $material->title . '.pdf');
        }
        // If it is an external URL, return the link
        if ($material->external_url) {
            return response()->json([
                'message' => 'External resource link.',
                'title' => $material->title,
                'url' => $material->external_url
            ]);
        }
        
        return response()->json(['message' => 'Material found, but no resource attached.'], 404);
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($workshopId, $materialId)
    {
        $material = WorkshopMaterial::where('workshop_id', $workshopId)
        ->findOrFail($materialId);

        // Only the facilitator (animator) can delete
        if (auth()->id() !== $material->workshop->animator_id) {
            return response()->json(['message' => 'Unauthorized. Only the facilitator can delete materials.'], 403);
        }

    // Physical File Deletion (if it's a file/pdf)
        if ($material->type === 'pdf' || $material->type === 'file') {
            if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
                Storage::disk('public')->delete($material->file_path);
            }
        }

        $material->delete();

        return response()->json([
            'message' => 'Material successfully deleted from database and storage.'
        ], 200);
    }
}
