<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    // login with goole
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        $clientId = config('services.google.client_id');

        try {
            $client = new GoogleClient(['client_id' => $clientId]);
            // التحقق من توقيع وصلاحية Google ID Token تلقائياً
            $payload = $client->verifyIdToken($request->credential);

            if (! $payload) {
                return response()->json([
                    'message' => 'رمز التحقق من Google غير صالح أو منتهي الصلاحية',
                ], 401);
            }

            $result = $this->authService->loginWithGoogle($payload);

            return response()->json([
                'message' => 'تم تسجيل الدخول بنجاح عبر Google',
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل التحقق من حساب Google',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }
}
