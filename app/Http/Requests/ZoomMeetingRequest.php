<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ZoomMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'zoom_link' => ['required', 'url', 'max:2048'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'الكورس مطلوب',
            'course_id.exists' => 'الكورس غير موجود',
            'title.required' => 'عنوان اللقاء مطلوب',
            'zoom_link.required' => 'رابط زووم مطلوب',
            'zoom_link.url' => 'رابط زووم غير صالح',
            'starts_at.required' => 'وقت البداية مطلوب',
            'starts_at.date' => 'وقت البداية غير صالح',
        ];
    }
}
