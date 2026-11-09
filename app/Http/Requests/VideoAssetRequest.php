<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Extract clean 11-char YouTube ID if a full URL (watch, embed, live, shorts, youtu.be) was provided
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|live|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $raw, $matches)) {
                $this->merge(['provider_video_id' => $matches[1]]);
            } else {
                $this->merge(['provider_video_id' => trim($raw)]);
            }
        }

        if ($this->isMethod('post')) {
            if (! $this->has('provider') || blank($this->input('provider'))) {
                $this->merge(['provider' => 'youtube']);
            }

            if (! $this->has('type') || blank($this->input('type'))) {
                $this->merge(['type' => 'recording']);
            }
        }

        if ($this->has('duration_seconds')) {
            $duration = $this->input('duration_seconds');
            if ($duration === null || $duration === '') {
                $this->merge(['duration_seconds' => 0]);
            }
        }
    }

    public function rules(): array
    {
        $courseId = $this->input('course_id');
        $isPost = $this->isMethod('post');

        return [
            'course_id' => [$isPost ? 'required' : 'sometimes', 'integer', 'exists:courses,id'],
            'zoom_meeting_id' => [
                'nullable',
                'integer',
                Rule::exists('zoom_meetings', 'id')->when($courseId, fn ($q) => $q->where('course_id', $courseId)),
            ],
            'title' => [$isPost ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider' => [$isPost ? 'required' : 'sometimes', 'string', 'in:youtube,bunny,cloudflare'],
            'provider_video_id' => [$isPost ? 'required' : 'sometimes', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'type' => [$isPost ? 'required' : 'sometimes', 'string', 'in:recording,lesson'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'الكورس مطلوب',
            'course_id.exists' => 'الكورس المختار غير موجود',
            'zoom_meeting_id.exists' => 'لقاء الزوم المختار غير موجود أو لا ينتمي لهذا الكورس',
            'title.required' => 'عنوان الفيديو مطلوب',
            'provider.in' => 'مزود الفيديو غير مدعوم',
            'provider_video_id.required' => 'رابط أو معرّف الفيديو مطلوب',
        ];
    }
}

