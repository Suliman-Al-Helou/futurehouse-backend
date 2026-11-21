<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAdminResource;
use App\Models\Instructor;
use App\Models\User;
use App\Services\StatsService;
use App\Support\ContentCache;
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
        if (request()->has('limit')) {
            $perPage = min(max(request()->integer('limit'), 1), 20);
        } else {
            $perPage = min(max(request()->integer('per_page', 50), 1), 100);
        }

        $query = User::nonAdmin()->with('roles')->withCount('enrollments');

        if (request()->boolean('unlinked_only')) {
            $query->whereDoesntHave('instructor');
        }

        if ($search = trim((string) request('search', request('q', '')))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return UserAdminResource::collection(
            $query->latest()->paginate($perPage)
        );
    }

    /** Lists students only with enrollment counts. */
    public function students(): AnonymousResourceCollection
    {
        $perPage = min(max(request()->integer('per_page', 50), 1), 100);

        return UserAdminResource::collection(
            User::students()->with('roles')->withCount('enrollments')->latest()->paginate($perPage)
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

        if ($user->hasRole('instructor') && $validated['role'] !== 'instructor') {
            Instructor::where('user_id', $user->id)->update(['user_id' => null]);
            ContentCache::forgetInstructors();
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
