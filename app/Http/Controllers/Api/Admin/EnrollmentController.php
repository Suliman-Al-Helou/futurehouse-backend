<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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

    /** Manually registers a student into a course. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'student_email' => ['required', 'email'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'payment_confirmed' => ['nullable', 'boolean'],
        ]);

        $user = User::where('email', $validated['student_email'])->first();
        if (! $user) {
            $user = User::create([
                'name' => $validated['student_name'] ?: explode('@', $validated['student_email'])[0],
                'email' => $validated['student_email'],
                'password' => Hash::make(Str::random(16)),
                'is_active' => true,
            ]);
            $studentRole = Role::findOrCreate('student', 'web');
            $user->assignRole($studentRole);
        }

        $existing = Enrollment::where('user_id', $user->id)
            ->where('course_id', $validated['course_id'])
            ->first();

        if ($existing) {
            $existing->status = $validated['status'] ?? 'approved';
            $existing->save();
            $enrollment = $existing;
        } else {
            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $validated['course_id'],
                'status' => $validated['status'] ?? 'approved',
            ]);
        }

        return (new EnrollmentResource($enrollment->loadMissing(['user', 'course'])))
            ->response()
            ->setStatusCode(201);
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
