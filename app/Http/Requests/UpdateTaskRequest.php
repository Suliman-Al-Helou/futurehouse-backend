<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questions' => ['required', 'array'],
            'pass_percentage' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'max_attempts' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'الأسئلة مطلوبة',
            'questions.array' => 'الأسئلة يجب أن تكون قائمة',
        ];
    }
}
