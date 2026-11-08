<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstructorRequest;
use App\Http\Requests\UpdateInstructorRequest;
use App\Http\Resources\InstructorDetailResource;
use App\Http\Resources\InstructorResource;
use App\Models\Instructor;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InstructorController extends Controller
{
    public function __construct(
        private readonly InstructorService $instructorService,
    ) {}

    /** Lists instructors with course counts. */
    public function index(): AnonymousResourceCollection
    {
        return InstructorResource::collection(Instructor::withCount('courses')->get());
    }

    /** Instructor profile with course summaries. */
    public function show(int $id): JsonResponse
    {
        $instructor = Instructor::withCourseStats()->findOrFail($id);

        return response()->json(new InstructorDetailResource($instructor));
    }

    /** Creates an instructor. */
    public function store(InstructorRequest $request): JsonResponse
    {
        $instructor = $this->instructorService->create($request->validated());

        return response()->json(new InstructorResource($instructor), 201);
    }

    /** Updates an instructor. */
    public function update(UpdateInstructorRequest $request, Instructor $instructor): JsonResponse
    {
        $instructor = $this->instructorService->update($instructor, $request->validated());

        return response()->json(new InstructorResource($instructor));
    }

    /** Deletes an instructor. */
    public function destroy(Instructor $instructor): Response
    {
        $this->instructorService->delete($instructor);

        return response()->noContent();
    }
}
