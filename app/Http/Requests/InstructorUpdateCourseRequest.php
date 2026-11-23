<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class InstructorUpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Course|null $course */
        $course = $this->route('course');

        if (! $course) {
            return false;
        }

        return Gate::allows('manageAsInstructor', $course);
    }

    public function rules(): array
    {
        $rules = [
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'string', 'min:10', 'max:5000'],
            'level' => ['sometimes', 'in:beginner,intermediate,advanced'],
            'what_you_learn' => ['sometimes', 'nullable', 'array', 'max:30'],
            'what_you_learn.*' => ['string', 'max:255'],
            'requirements' => ['sometimes', 'nullable', 'array', 'max:30'],
            'requirements.*' => ['string', 'max:255'],
            'target_audience' => ['sometimes', 'nullable', 'array', 'max:30'],
            'target_audience.*' => ['string', 'max:255'],

            // Sensitive administrative and commercial fields strictly prohibited for instructors
            'price' => ['prohibited'],
            'youtube_playlist_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'is_public' => ['prohibited'],
            'status' => ['prohibited'],
        ];

        if ($this->hasFile('cover_image')) {
            $rules['cover_image'] = ['sometimes', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'];
        } else {
            $rules['cover_image'] = ['sometimes', 'nullable', 'string', 'max:2048'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.min' => 'يجب أن يتكون عنوان الكورس من 3 أحرف على الأقل',
            'title.max' => 'يجب ألا يتجاوز عنوان الكورس 255 حرفاً',
            'description.min' => 'يجب أن يتكون وصف الكورس من 10 أحرف على الأقل',
            'level.in' => 'المستوى المحدد غير صالح',
            'cover_image.image' => 'يجب أن تكون صورة الغلاف ملف صورة صالح',
            'cover_image.mimes' => 'يجب أن تكون صورة الغلاف بصيغة: jpeg, png, jpg, webp',
            'cover_image.max' => 'حجم صورة الغلاف يجب ألا يتجاوز 2 ميجابايت',
            'price.prohibited' => 'لا يمكن للمدرب تعديل سعر الكورس',
            'youtube_playlist_id.prohibited' => 'لا يمكن للمدرب تعديل قائمة تشغيل اليوتيوب',
            'instructor_id.prohibited' => 'لا يمكن للمدرب نقل ملكية الكورس',
            'is_public.prohibited' => 'لا يمكن للمدرب تعديل حالة النشر العام',
            'status.prohibited' => 'لا يمكن للمدرب تعديل حالة اعتماد الكورس',
        ];
    }
}
