<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ApiAuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => [
                'required',
                'string',
                Rule::in([
                    'participant',
                    'author',
                    'scientific_committee',
                    'guest_speaker',
                    'workshop_facilitator'
                ])
            ],
            'phone' => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'research_field' => 'nullable|string|max:255',
            'biography' => 'nullable|string|max:1000',
            'country' => 'nullable|string|max:100',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'institution' => $request->institution,
            'research_field' => $request->research_field,
            'biography' => $request->biography,
            'country' => $request->country,
        ]);
    
        $user->assignRole($request->role);
    
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'roles' => $user->getRoleNames(),
                'token' => $token,
            ]
        ], 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('roles'),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'token' => $token,
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load('roles'),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name')
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'institution' => 'sometimes|string|max:255',
            'research_field' => 'sometimes|string|max:255',
            'biography' => 'sometimes|string|max:1000',
            'country' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only([
            'name',
            'phone',
            'institution',
            'research_field',
            'biography',
            'country'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user->load('roles'),
                'roles' => $user->getRoleNames()
            ]
        ]);
    }

    /**
     * Upload profile photo
     */
    public function uploadPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Delete old photo if exists
        if ($user->profile_photo) {
            Storage::delete('public/' . $user->profile_photo);
        }

        // Store new photo
        $path = $request->file('photo')->store('profile_photos', 'public');

        $user->update([
            'profile_photo' => $path
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile photo uploaded successfully',
            'data' => [
                'user' => $user->load('roles'),
                'photo_url' => asset('storage/' . $path)
            ]
        ]);
    }
}

