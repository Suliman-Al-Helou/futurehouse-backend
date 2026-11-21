<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password',   'google_id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if (! $user->isForceDeleting()) {
                Instructor::where('user_id', $user->id)->update(['user_id' => null]);
            }
            ContentCache::forgetInstructors();
        });
    }

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

    /**
     * Determine whether the user is an instructor.
     */
    public function isInstructor(): bool
    {
        return $this->hasRole('instructor');
    }

    /**
     * The instructor profile linked to this user account (1 user -> 1 instructor profile).
     */
    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class);
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
