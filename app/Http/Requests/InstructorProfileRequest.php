<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstructorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('instructor') || $user->isAdmin());
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'cover_url' => ['nullable', 'string', 'max:2048'],
            'specializations' => ['nullable', 'string'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:100'],
            'achievements' => ['nullable', 'array'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],

            // Strictly prohibited metrics & identifiers (Zero-tampering guard)
            'rating' => ['prohibited'],
            'total_reviews' => ['prohibited'],
            'students_count' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.prohibited' => 'لا يمكن للمدرب تعديل التقييم يدوياً',
            'total_reviews.prohibited' => 'لا يمكن للمدرب تعديل عدد التقييمات',
            'students_count.prohibited' => 'لا يمكن للمدرب تعديل عدد الطلاب المسجلين',
            'user_id.prohibited' => 'لا يمكن تعديل معرف الحساب المرتبط',
        ];
    }
}
