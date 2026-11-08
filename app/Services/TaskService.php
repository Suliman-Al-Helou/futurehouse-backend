<?php

// FILE: app/Services/TaskService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lesson;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TaskService
{
    /** Creates the lesson's task; a lesson may have only one (409 otherwise). */
    public function store(Lesson $lesson, array $data): Task
    {
        // 1. فتح عملية داخل قاعدة البيانات لضمان تنفيذ الخطوات كحزمة واحدة
        $task = DB::transaction(function () use ($lesson, $data): Task {

            // 2. قفل سجل الدرس الحالي في قاعدة البيانات لمنع أي مسؤول آخر من فحص أو تعديل هذا الدرس في نفس اللحظة
            $lockedLesson = Lesson::lockForUpdate()->findOrFail($lesson->getKey());

            // 3. الفحص الآمن الآن بعد قفل السجل
            if ($lockedLesson->task()->exists()) {
                throw new ConflictHttpException('هذا الدرس لديه مهمة بالفعل');
            }

            // 4. إنشاء المهمة عبر السجل المقفل والمحمي
            return $lockedLesson->task()->create($data);
        });

        // 5. إرجاع المهمة التي تم إنشاؤها بنجاح
        return $task;
    }
}
