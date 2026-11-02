<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtolower(trim($value)));
    }

    // D5: no remember_token column; an empty name makes Eloquent skip it.
    public function getRememberTokenName(): string
    {
        return '';
    }

    // ═════════════ SCOPES ═════════════
    public function scopeStudents(Builder $query): Builder
    {
        return $query->where('role', 'student');
    }

    public function scopeNonAdmin(Builder $query): Builder
    {
        return $query->where('role', '!=', 'admin');
    }

    // ═════════════ CHECKS ═════════════
    /**
     * التحقق مما إذا كان المستخدم يمتلك صلاحيات المسؤول (Admin).
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    // ═════════════ RELATIONS ═════════════

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
