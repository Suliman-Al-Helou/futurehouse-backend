<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAdminResource;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

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

    /** Deletes a non-admin user safely. */
    public function destroyUser(User $user): JsonResponse
    {
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الخاص'], 403);
        }

        if ($user->isAdmin()) {
            return response()->json(['message' => 'لا يمكن حذف حساب مسؤول'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم بنجاح']);
    }

    /** Updates user role. */
    public function updateUserRole(Request $request, User $user): UserAdminResource|JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:student,instructor,admin'],
        ], [
            'role.required' => 'الدور مطلوب',
            'role.in' => 'الدور المحدد غير صالح',
        ]);

        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return response()->json(['message' => 'لا يمكنك تجريد نفسك من صلاحيات الإدارة'], 403);
        }

        $role = Role::findOrCreate($validated['role'], 'web');
        $user->syncRoles([$role]);

        return new UserAdminResource($user->loadMissing('roles')->loadCount('enrollments'));
    }

    // ═════════════ STATS ═════════════

    /** Dashboard counters. */
    public function stats(): JsonResponse
    {
        return response()->json($this->statsService->overview());
    }
}
