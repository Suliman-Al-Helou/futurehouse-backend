<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $url = env('FRONTEND_URL') . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('إعادة تعيين كلمة المرور')
            ->line('لقد تلقينا طلباً لإعادة تعيين كلمة مرور حسابك.')
            ->action('إعادة تعيين كلمة المرور', $url)
            ->line('ينتهي هذا الرابط خلال 60 دقيقة.')
            ->line('إذا لم تطلب ذلك، تجاهل هذا البريد.');
    }
}