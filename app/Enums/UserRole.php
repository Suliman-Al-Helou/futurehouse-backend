<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case STUDENT = 'student';

    /**
     * Determine if the role represents administrative privileges.
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Determine if the role represents a student.
     */
    public function isStudent(): bool
    {
        return $this === self::STUDENT;
    }
}
