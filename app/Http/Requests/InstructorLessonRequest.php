<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class InstructorLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Section|null $section */
        $section = $this->route('section');
        /** @var Lesson|null $lesson */
        $lesson = $this->route('lesson');

        if ($section instanceof Section) {
            $course = $section->course;
            if (! $course) {
                abort(404, 'الكورس المرتبط بالقسم غير موجود');
            }

            return Gate::allows('manageAsInstructor', $course);
        }

        if ($lesson instanceof Lesson) {
            $course = $lesson->section?->course;
            if (! $course) {
                abort(404, 'الكورس المرتبط بالدرس غير موجود');
            }

            return Gate::allows('manageAsInstructor', $course);
        }

        return false;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_preview' => ['sometimes', 'nullable', 'boolean'],
            'is_published' => ['sometimes', 'nullable', 'boolean'],
            'video_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'video_provider' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الدرس مطلوب',
            'title.min' => 'يجب أن يتكون عنوان الدرس من حرفين على الأقل',
            'title.max' => 'يجب ألا يتجاوز عنوان الدرس 255 حرفاً',
            'description.max' => 'يجب ألا يتجاوز وصف الدرس 5000 حرف',
            'duration.integer' => 'مدة الدرس يجب أن تكون رقماً صحيحاً بالدقائق',
            'duration.min' => 'مدة الدرس يجب ألا تقل عن 0',
            'order.integer' => 'ترتيب الدرس يجب أن يكون رقماً صحيحاً',
            'order.min' => 'ترتيب الدرس يجب ألا يقل عن 0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! app()->isProduction()) {
                return;
            }

            $existing = $this->route('lesson');
            $existingLesson = $existing instanceof Lesson ? $existing : null;

            $isPublished = $this->has('is_published')
                ? filter_var($this->input('is_published'), FILTER_VALIDATE_BOOLEAN)
                : ($existingLesson ? (bool) $existingLesson->is_published : true);
            $isPreview = $this->has('is_preview')
                ? filter_var($this->input('is_preview'), FILTER_VALIDATE_BOOLEAN)
                : ($existingLesson ? (bool) $existingLesson->is_preview : false);
            $videoProvider = $this->has('video_provider')
                ? $this->input('video_provider')
                : ($existingLesson ? $existingLesson->video_provider : config('services.video.default', 'youtube'));
            $videoId = $this->has('video_id')
                ? $this->input('video_id')
                : ($existingLesson ? $existingLesson->video_id : null);

            if ($isPublished && ! $isPreview && ($videoProvider !== 'hls' || empty($videoId))) {
                $validator->errors()->add(
                    'video_provider',
                    'لا يمكن نشر درس مدفوع في بيئة الإنتاج بدون فيديو HLS.'
                );
            }
        });
    }
}
