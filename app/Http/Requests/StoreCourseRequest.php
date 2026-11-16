<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'cover_image' => ['nullable', 'url'],
            'level' => ['required', 'in:beginner,intermediate,advanced'],
            'status' => ['required', 'in:published,draft,coming_soon'],
            'instructor_id' => ['required', 'integer', 'exists:instructors,id'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'is_popular' => ['boolean'],
            'total_duration' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_public' => ['boolean'],
            'what_you_learn' => ['nullable', 'array'],
            'what_you_learn.*' => ['string', 'max:500'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', 'max:500'],
            'target_audience' => ['nullable', 'array'],
            'target_audience.*' => ['string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'العنوان مطلوب',
            'title.min' => 'العنوان يجب أن يكون 3 أحرف على الأقل',
            'description.required' => 'الوصف مطلوب',
            'description.min' => 'الوصف يجب أن يكون 10 أحرف على الأقل',
            'instructor_id.required' => 'اختر مدرباً',
            'instructor_id.exists' => 'المدرب غير موجود',
            'level.in' => 'المستوى غير صالح',
            'status.in' => 'الحالة غير صالحة',
        ];
    }
}
