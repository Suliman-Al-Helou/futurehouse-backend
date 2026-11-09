<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService,
    ) {}

    /** Lists all enrollments with user and course (eager-loaded). */
    public function index(): AnonymousResourceCollection
    {
        return EnrollmentResource::collection(
            Enrollment::with(['user', 'course'])->latest()->get()
        );
    }

    /** Delegates the status transition (rules + lock) to the service. */
    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment): JsonResponse
    {
        $updated = $this->enrollmentService->changeStatus($enrollment, $request->validated('status'));

        return response()->json([
            'message' => 'تم تحديث حالة التسجيل',
            'enrollment' => new EnrollmentResource($updated),
        ]);
    }
}
