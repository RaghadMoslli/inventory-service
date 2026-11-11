<?php

namespace App\Modules\Users\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/v1/auth/register",
     *      summary="تسجيل مستخدم جديد",
     *      tags={"Auth"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name", "email", "password"},
     *              @OA\Property(property="name", type="string", example="أحمد محمد"),
     *              @OA\Property(property="email", type="string", format="email", example="ahmad@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="secret12345")
     *          )
     *      ),
     *      @OA\Response(response=201, description="تم إنشاء الحساب بنجاح"),
     *      @OA\Response(response=422, description="خطأ في التحقق من صحة البيانات أو البريد مستخدم مسبقاً")
     * )
     */
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

    /**
     * @OA\Post(
     *      path="/api/v1/auth/login",
     *      summary="تسجيل الدخول للمستخدم",
     *      tags={"Auth"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email", "password"},
     *              @OA\Property(property="email", type="string", format="email", example="ahmad@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="secret12345")
     *          )
     *      ),
     *      @OA\Response(response=200, description="تم تسجيل الدخول بنجاح وإرجاع التوكن"),
     *      @OA\Response(response=401, description="بيانات الاعتماد غير صحيحة")
     * )
     */
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

    /**
     * @OA\Get(
     *      path="/api/v1/me",
     *      summary="جلب بيانات المستخدم الحالي المسجل دخوله",
     *      tags={"Auth"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *      @OA\Response(response=401, description="غير مصرح")
     * )
     */
    // جلب بيانات المستخدم الحالي
    public function me()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * @OA\Post(
     *      path="/api/v1/auth/logout",
     *      summary="تسجيل الخروج وإبطال التوكن",
     *      tags={"Auth"},
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="تم تسجيل الخروج بنجاح"),
     *      @OA\Response(response=401, description="غير مصرح")
     * )
     */
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