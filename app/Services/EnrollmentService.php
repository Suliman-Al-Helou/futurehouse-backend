<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Support\ContentCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EnrollmentService
{
    private const TRANSITIONS = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['rejected'], // revoke access
        'rejected' => [],           // student re-requests (pending)
    ];

    /**
     * Applies a status transition under a row lock so two admins
     * cannot race on the same enrollment. Throws 422 on invalid moves.
     */
    public function changeStatus(Enrollment $enrollment, string $to): Enrollment
    {
        /**
         * use DB::transaction() : make sure the requests is sync, all success or all fail, and rollback if any error happens
         * $current : prevent  N+1
         * lockForUpdate() : prevents multiple admins from updating the same enrollment at the same time (race condition)
         */
        $locked = DB::transaction(function () use ($enrollment, $to): Enrollment {
            $current = Enrollment::with(['course', 'user'])
                ->lockForUpdate()
                ->findOrFail($enrollment->getKey());

            $allowed = self::TRANSITIONS[$current->status] ?? [];
            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "لا يمكن نقل الطلب من {$current->status} إلى {$to}",
                ]);
            }

            if ($current->course->isFree()) {
                throw ValidationException::withMessages([
                    'status' => 'هذا الكورس مجاني ولا يحتاج موافقة',
                ]);
            }

            $current->update(['status' => $to]);

            return $current;
        });

        // approved_count is cached inside the public course list
        ContentCache::forgetCourses();

        return $locked;
    }
}
