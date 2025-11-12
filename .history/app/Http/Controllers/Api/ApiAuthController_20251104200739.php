<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            'role' => 'required|string|exists:roles,name',
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
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

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
                'user' => $user,
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
   /**
 * Get authenticated user
 */
public function me(Request $request)
{
    $user = $request->user();
    
    return response()->json([
        'success' => true,
        'data' => [
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')
        ]
    ]);
}
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
            'user' => $user,
            'roles' => $user->getRoleNames()
        ]
    ]);
}