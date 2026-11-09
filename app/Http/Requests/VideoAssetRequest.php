<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VideoAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('provider_video_id')) {
            $raw = (string) $this->input('provider_video_id');
            // Extract clean 11-char YouTube ID if a full URL was provided
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $raw, $matches)) {
                $this->merge(['provider_video_id' => $matches[1]]);
            } else {
                $this->merge(['provider_video_id' => trim($raw)]);
            }
        }

        if (! $this->has('provider') || blank($this->input('provider'))) {
            $this->merge(['provider' => 'youtube']);
        }

        if (! $this->has('type') || blank($this->input('type'))) {
            $this->merge(['type' => 'recording']);
        }
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'zoom_meeting_id' => ['nullable', 'integer', 'exists:zoom_meetings,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider' => ['required', 'string', 'in:youtube,bunny,cloudflare'],
            'provider_video_id' => ['required', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'type' => ['required', 'string', 'in:recording,lesson'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'الكورس مطلوب',
            'course_id.exists' => 'الكورس المختار غير موجود',
            'zoom_meeting_id.exists' => 'لقاء الزوم المختار غير موجود',
            'title.required' => 'عنوان الفيديو مطلوب',
            'provider.in' => 'مزود الفيديو غير مدعوم',
            'provider_video_id.required' => 'رابط أو معرّف الفيديو مطلوب',
        ];
    }
}
