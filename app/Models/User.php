<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password','role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    // الكورسات المسجل فيها
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    // تقدم الطالب في الدروس
    public function lessonProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    // محاولات المهام
    public function taskAttempts()
    {
        return $this->hasMany(TaskAttempt::class);
    }
    public function sendPasswordResetNotification($token): void
{
    $this->notify(new ResetPasswordNotification($token));
}
}