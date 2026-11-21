<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case STUDENT = 'student';
    case INSTRUCTOR = 'instructor';

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

    /**
     * Determine if the role represents an instructor.
     */
    public function isInstructor(): bool
    {
        return $this === self::INSTRUCTOR;
    }
}
