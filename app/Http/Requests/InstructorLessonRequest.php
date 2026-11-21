<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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
}
