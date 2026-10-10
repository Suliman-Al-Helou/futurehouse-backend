<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminZoomMeetingController extends Controller
{
    private function rules(): array
    {
        return [
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'zoom_link' => 'required|url|max:2048',
            'starts_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
        ];
    }

    // قائمة كل اللقاءات (الأحدث أولًا) مع عدد الحاضرين
    public function index(): JsonResponse
    {
        return response()->json(
            ZoomMeeting::with('course:id,title')->withCount(['attendances',   'attendances as confirmed_count' => fn ($q) => $q->whereNotNull('confirmed_at')])->orderByDesc('starts_at')->get()
        );
    }
    // بيانات الحفظ: نتحقق، وبنخزّن اسم الكورس من عنوانه (لتبقى course_name متزامنة)

    private function payload(Request $request): array
    {
        $data = $request->validate($this->rules());

        $data['course_name'] = Course::findOrFail($data['course_id'])->title;
        $data['duration_minutes'] = $data['duration_minutes'] ?? 60;

        return $data;
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $data['course_name'] = Course::findOrFail($data['course_id'])->title;
        $data['duration_minutes'] = $data['duration_minutes'] ?? 60;

        return response()->json(ZoomMeeting::create($data), 201);
    }

    public function update(Request $request, ZoomMeeting $zoomMeeting): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data['course_name'] = Course::findOrFail($data['course_id'])->title;
        if (! isset($data['duration_minutes'])) {
            unset($data['duration_minutes']); // نُبقي المدة الحالية المخزنة سابقاً دون تدميرها
        }

        $zoomMeeting->update($data);

        return response()->json($zoomMeeting);
    }

    public function destroy(ZoomMeeting $zoomMeeting): JsonResponse
    {
        $zoomMeeting->delete(); // cascade يحذف سجلات الحضور

        return response()->json(['message' => 'تم حذف اللقاء']);
    }

    // من حضر هذا اللقاء
    public function attendances(ZoomMeeting $zoomMeeting): JsonResponse
    {
        $rows = $zoomMeeting->attendances()
            ->with('user:id,name,email')
            ->orderBy('attended_at')
            ->get();

        return response()->json($rows);
    }
    
    
    
    // كل الطلاب المقبولين في كورس اللقاء مع حالة كل واحد (للتحضير اليدوي)
    public function roster(ZoomMeeting $zoomMeeting): JsonResponse
    {
        $studentIds = Enrollment::where('course_id', $zoomMeeting->course_id)
            ->where('status', 'approved')
            ->pluck('user_id');

        $students = User::whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name', 'email']);
        $attendances = $zoomMeeting->attendances()->get()->keyBy('user_id');

        return response()->json(
            $students->map(function (User $user) use ($attendances) {
                $attendance = $attendances->get($user->id);

                return [
                    'user'         => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                    'joined_at'    => $attendance?->attended_at,
                    'attended'     => $attendance?->confirmed_at !== null,
                    'confirmed_at' => $attendance?->confirmed_at,
                    'method'       => $attendance?->confirmation_method,
                ];
            })->values()
        );
    }

    // الأدمن يطلق كود حضور جديد (4 أرقام) أثناء اللقاء
    public function releaseCode(ZoomMeeting $zoomMeeting): JsonResponse
    {
        if (! $zoomMeeting->canReleaseCode()) {
            return response()->json(['message' => 'يمكن إطلاق الكود أثناء اللقاء فقط'], 422);
        }

        $zoomMeeting->forceFill([
            'attendance_code'            => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'attendance_code_expires_at' => now()->addMinutes(ZoomMeeting::CODE_VALID_MINUTES),
        ])->save();

        return response()->json([
            'code'       => $zoomMeeting->attendance_code,
            'expires_at' => $zoomMeeting->attendance_code_expires_at,
        ]);
    }

    // تعديل يدوي: تأكيد حضور طالب أو إلغاء تأكيده
    public function setAttendance(Request $request, ZoomMeeting $zoomMeeting, User $user): JsonResponse
    {
        $data = $request->validate(['attended' => 'required|boolean']);

        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $zoomMeeting->course_id)
            ->where('status', 'approved')
            ->exists();

        if (! $enrolled) {
            return response()->json(['message' => 'الطالب غير مقبول في كورس هذا اللقاء'], 422);
        }

        $attendance = ZoomAttendance::where('zoom_meeting_id', $zoomMeeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($data['attended']) {
            $attendance ??= new ZoomAttendance([
                'zoom_meeting_id' => $zoomMeeting->id,
                'user_id'         => $user->id,
            ]);
            $attendance->fill(['confirmed_at' => now(), 'confirmation_method' => 'manual'])->save();
        } elseif ($attendance) {
            $attendance->update(['confirmed_at' => null, 'confirmation_method' => null]);
        }

        return response()->json(['attended' => (bool) $data['attended']]);
    }
 }

    