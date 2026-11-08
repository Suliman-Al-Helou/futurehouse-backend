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
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    protected function role(): Attribute
    {
        return Attribute::get(fn ($value) => $this->getRoleNames()->first() ?? $value ?? 'student');
    }

    // D5: no remember_token column; an empty name makes Eloquent skip it.
    public function getRememberTokenName(): string
    {
        return '';
    }

    // ═════════════ SCOPES ═════════════
    public function scopeStudents(Builder $query): Builder
    {
        return $query->role('student');
    }

    public function scopeNonAdmin(Builder $query): Builder
    {
        return $query->withoutRole('admin');
    }

    // ═════════════ CHECKS ═════════════
    /**
     * Determine whether the user has administrator privileges.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || ($this->attributes['role'] ?? null) === 'admin';
    }

    /**
     * Determine whether the user has student privileges.
     */
    public function isStudent(): bool
    {
        return $this->hasRole('student');
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
