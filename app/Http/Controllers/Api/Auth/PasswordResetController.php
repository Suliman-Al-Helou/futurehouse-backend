<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
   
    
    /**
     * إرسال رابط إعادة التعيين (مؤمن بالكامل ضد ثغرات Enumeration)
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // نرسل الطلب لـ Laravel ليتعامل مع الـ Broker
        Password::sendResetLink($request->only('email'));

        // 🛡️ القاعدة الذهبية: نرد دائماً بنفس الإجابة وحالة 200 OK سواء كان الإيميل مسجلاً أم لا!
        // هكذا يستحيل على أي مخترق معرفة الحسابات المسجلة في تطبيقك.
        return response()->json([
            'message' => 'إذا كان هذا البريد مسجلاً في منصتنا، فقد أرسلنا رابط إعادة التعيين إلى بريدك الإلكتروني الحين.',
        ]);
    }

    /**
     * إعادة تعيين كلمة المرور وتطهير كافة الجلسات السابقة للحساب لضمان الحماية
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // 1. تحديث الباسورد الجديد مشفراً
                $user->password = bcrypt($password);
                $user->save();

                // 2. 🛡️ الحل العبقري للأمان وتجنب الانهيار: 
                // بما أن الباسورد تغير، نقوم فوراً بحذف "كافة" الرموز القديمة النشطة لهذا المستخدم 
                // لطرده من أي جهاز آخر وسحب الصلاحيات القديمة بأمان ودون انهيار السيرفر!
                $user->tokens()->delete(); 
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح، يمكنك الآن تسجيل الدخول ببياناتك الجديدة.',
            ]);
        }

        return response()->json([
            'message' => 'الرابط غير صالح، أو تم استخدامه مسبقاً، أو انتهت صلاحيته الأمنية.',
        ], 422);
    }
}
