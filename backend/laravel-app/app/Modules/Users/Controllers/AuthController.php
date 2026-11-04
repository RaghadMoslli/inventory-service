<?php

namespace App\Modules\Users\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // تسجيل مستخدم جديد (Customer افتراضياً)
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'البيانات المدخلة غير صحيحة',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer', // دائماً يُسجل كزبون بشكل آمن
        ]);

        $token = auth('api')->login($user);

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ], 201);
    }

    // تسجيل الدخول (مع حماية Rate Limiting 5 محاولات)
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // محاولة المصادقة وإخفاء سبب الخطأ (سواء الإيميل أو كلمة المرور خطأ)
        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'بيانات الاعتماد غير صحيحة',
                ]
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    // جلب بيانات المستخدم الحالي
    public function me()
    {
        return response()->json(auth('api')->user());
    }

    // تسجيل الخروج
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => auth('api')->user()
        ]);
    }
}