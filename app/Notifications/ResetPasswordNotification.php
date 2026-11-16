<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function toMail($notifiable): MailMessage
    {
        $url = env('FRONTEND_URL').'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور')
            ->line('لقد تلقينا طلباً لإعادة تعيين كلمة مرور حسابك.')
            ->action('إعادة تعيين كلمة المرور', $url)
            ->line('ينتهي هذا الرابط خلال 60 دقيقة.')
            ->line('إذا لم تطلب ذلك، تجاهل هذا البريد.');
    }
}
