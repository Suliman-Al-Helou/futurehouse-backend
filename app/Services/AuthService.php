<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

final class AuthService
{
    /**
     * Register a new student account within a database transaction.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $role = Role::findOrCreate('student', 'web');
            $user->assignRole($role);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    /**
     * Authenticate user credentials, inspect active status, and issue token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws AuthenticationException
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        // 1. التحقق من وجود المستخدم وصحة كلمة المرور
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new AuthenticationException('البريد الإلكتروني أو كلمة المرور غير صحيحة');
        }

        // 2. التحقق الصارم من حالة تفعيل الحساب
        if (! $user->is_active) {
            throw new AuthenticationException('تم تعطيل هذا الحساب، يرجى التواصل مع الإدارة');
        }

        // 3. مسح التوكنات القديمة لمنع تراكم الرموز وفرض جلسة نشطة واحدة
        $user->tokens()->delete();

        // 4. إصدار التوكن الجديد
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current access token used for authentication.
     * Preserves active sessions on other devices.
     */
    public function logout(User $user): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        $token?->delete();
    }
}
