<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use App\Support\ContentCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;

final class InstructorService
{
    /** Creates an instructor and assigns courses atomically. */
    public function create(array $data): Instructor
    {
        $instructor = DB::transaction(function () use ($data): Instructor {
            $instructor = Instructor::create(Arr::except($data, 'courses'));
            $this->syncCourses($instructor, $data['courses'] ?? null);
            if (! empty($instructor->user_id)) {
                $this->ensureInstructorRole((int) $instructor->user_id);
            }

            return $instructor;
        });

        ContentCache::forgetInstructors();

        return $instructor->load('user')->loadCount('courses');
    }

    /** Updates an instructor; courses are synced only if the key was sent. */
    public function update(Instructor $instructor, array $data): Instructor
    {
        DB::transaction(function () use ($instructor, $data): void {
            $prevUserId = $instructor->user_id;
            $hasUserIdInPayload = array_key_exists('user_id', $data);
            $newUserId = $hasUserIdInPayload ? $data['user_id'] : $prevUserId;

            $instructor->update(Arr::except($data, 'courses'));
            $this->syncCourses($instructor, $data['courses'] ?? null);

            if ($prevUserId && (int) $prevUserId !== (int) $newUserId) {
                $this->cleanupInstructorRoleIfOrphaned((int) $prevUserId, $instructor->id);
            }
            if ($newUserId && (int) $newUserId !== (int) $prevUserId) {
                $this->ensureInstructorRole((int) $newUserId);
            }
        });

        ContentCache::forgetInstructors();

        return $instructor->refresh()->load('user')->loadCount('courses');
    }

    /** Deletes an instructor (courses.instructor_id is nullOnDelete in DB). */
    public function delete(Instructor $instructor): void
    {
        $userId = $instructor->user_id;
        $instructor->delete();

        if ($userId) {
            $this->cleanupInstructorRoleIfOrphaned((int) $userId);
        }

        ContentCache::forgetInstructors();
    }

    /** Bootstraps a skeleton instructor profile for an instructor user if not present. */
    public function bootstrapSkeletonForUser(User $user): ?Instructor
    {
        if (! $user->exists || ! $user->hasRole('instructor')) {
            return null;
        }

        $instructor = Instructor::firstOrCreate(
            ['user_id' => $user->id],
            ['name' => $user->name]
        );

        ContentCache::forgetInstructors();

        return $instructor;
    }

    /** Updates personal dossier and atomically updates users.name if changed. */
    public function updateProfile(User $user, Instructor $instructor, array $data): Instructor
    {
        DB::transaction(function () use ($instructor, $user, $data): void {
            if (isset($data['name'])) {
                $user->update(['name' => $data['name']]);
            }

            $instructor->update($data);
        });

        ContentCache::forgetInstructors();

        return $instructor->fresh()->load('user');
    }

    /** null = leave assignments untouched; [] = unassign all. */
    private function syncCourses(Instructor $instructor, ?array $courseIds): void
    {
        if ($courseIds === null) {
            return;
        }

        Course::where('instructor_id', $instructor->id)
            ->whereNotIn('id', $courseIds)
            ->update(['instructor_id' => null]);

        if ($courseIds !== []) {
            Course::whereIn('id', $courseIds)->update(['instructor_id' => $instructor->id]);
        }
    }

    /** Ensures the user is assigned the instructor role. */
    private function ensureInstructorRole(int $userId): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        try {
            $role = Role::findByName('instructor', 'web');
        } catch (RoleDoesNotExist $e) {
            throw new \RuntimeException("Role 'instructor' not found in database. Run database migrations or seeders.", 0, $e);
        }

        if (! $user->hasRole('instructor')) {
            $user->assignRole($role);
        }
    }

    /** Removes the instructor role if the user has no remaining instructor profiles. */
    private function cleanupInstructorRoleIfOrphaned(int $userId, ?int $excludeInstructorId = null): void
    {
        $query = Instructor::where('user_id', $userId);
        if ($excludeInstructorId) {
            $query->where('id', '!=', $excludeInstructorId);
        }

        if ($query->count() === 0) {
            $user = User::find($userId);
            if ($user && $user->hasRole('instructor')) {
                $user->removeRole('instructor');
                if (! $user->hasRole('admin') && ! $user->hasRole('student')) {
                    $studentRole = Role::findOrCreate('student', 'web');
                    $user->assignRole($studentRole);
                }
            }
        }
    }
}
