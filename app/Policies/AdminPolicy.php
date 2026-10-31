<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    /**
     * يتم استدعاء هذه الدالة قبل أي فحص آخر.
     * إذا أعادت true، يتم السماح للمستخدم فوراً بالقيام بالعملية.
     */
    public function before(User $user, string $ability): ?bool
    {
        // افحص هنا طريقة التحقق من الأدمن في نظامك (مثال: حقل الـ role أو دالة جاهزة)
        if ($user->isAdmin()) {
            return true;
        }

        return null; // اترك القرار لبقية الدالات إذا لم يكن أدمن
    }

    /**
     * صلاحية عامة للوصول لأي دالة إدارية
     */
    public function manageAdmin(User $user): bool
    {
        return false; // أي مستخدم عادٍ سيتم رفضه تلقائياً هنا لأن الـ before لم تعتد true
    }
}
