<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstructorProfileRequest;
use App\Http\Resources\CourseInstructorProfileResource;
use App\Models\Instructor;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorProfileController extends Controller
{
    public function __construct(
        private readonly InstructorService $instructorService,
    ) {}

    /** Returns the authenticated instructor's profile with read-only metrics. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $instructor = $user->instructor ?? $this->instructorService->bootstrapSkeletonForUser($user);

        return response()->json(new CourseInstructorProfileResource($instructor->load('user')));
    }

    /** Updates personal dossier and atomically updates users.name if changed. */
    public function update(InstructorProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $instructor = $user->instructor ?? $this->instructorService->bootstrapSkeletonForUser($user);

        $updatedInstructor = $this->instructorService->updateProfile($user, $instructor, $request->validated());

        return response()->json(new CourseInstructorProfileResource($updatedInstructor));
    }
}
