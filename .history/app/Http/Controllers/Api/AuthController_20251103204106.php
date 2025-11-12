<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
 /**
 * تسجيل مستخدم جديد
 */
public function register(RegisterRequest $request)
{
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    // إسناد الدور للمستخدم
    $user->assignRole($request->role);

    $token = $user->createToken('auth_token')->plainTextToken;

    // إعادة تحميل العلاقات
    $user->load('roles');

    return response()->json([
        'success' => true,
        'message' => 'تم التسجيل بنجاح',
        'data' => [
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'token' => $token,
        ]
    ], 201);
}
   /**
 * تسجيل الدخول
 */
public function login(LoginRequest $request)
{
    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json([
            'success' => false,
            'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'
        ], 401);
    }

    $user = User::where('email', $request->email)->firstOrFail();
    $user->load('roles');
    
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'data' => [
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'token' => $token,
        ]
    ]);
}

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    /**
     * الحصول على معلومات المستخدم الحالي
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }
}