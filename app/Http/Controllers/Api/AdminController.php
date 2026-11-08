<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAdminResource;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminController extends Controller
{
    public function __construct(
        private readonly StatsService $statsService,
    ) {}

    // ═════════════ USERS ═════════════

    /** Lists non-admin users with enrollment counts. */
    public function users(): AnonymousResourceCollection
    {
        return UserAdminResource::collection(
            User::nonAdmin()->with('roles')->withCount('enrollments')->latest()->get()
        );
    }

    /** Lists students only with enrollment counts. */
    public function students(): AnonymousResourceCollection
    {
        return UserAdminResource::collection(
            User::students()->with('roles')->withCount('enrollments')->latest()->get()
        );
    }

    // ═════════════ STATS ═════════════

    /** Dashboard counters. */
    public function stats(): JsonResponse
    {
        return response()->json($this->statsService->overview());
    }
}
