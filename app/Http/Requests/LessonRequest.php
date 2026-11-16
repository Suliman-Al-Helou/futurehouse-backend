<?php

namespace App\Http\Requests;

use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lessonId = $this->route('lesson') instanceof Lesson
            ? $this->route('lesson')->id
            : $this->route('lesson');

        return [
            'title' => 'required|string|max:255',
            'video_id' => [
                'nullable',
                'string',
                Rule::unique('lessons', 'video_id')->ignore($lessonId),
            ],
            'duration' => 'nullable|integer',
            'order' => 'nullable|integer',
            'is_preview' => 'nullable|boolean',
        ];
    }
}
