<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstructorRequest extends FormRequest
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
        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                Rule::unique('instructors', 'user_id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value || ! is_numeric($value)) {
                        return;
                    }
                    $user = User::find($value);
                    if ($user && $user->isAdmin()) {
                        $fail('لا يمكن ربط حساب مسؤول بدور مدرب.');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'avatar_url' => 'nullable|string|max:2048',
            'cover_url' => 'nullable|string',
            'specializations' => 'nullable|string',
            'years_experience' => 'nullable|integer|min:0',
            'rating' => 'nullable|numeric|min:0|max:5',
            'total_reviews' => 'nullable|integer|min:0',
            'students_count' => 'nullable|integer|min:0',
            'achievements' => 'nullable|array',
            'achievements.*' => 'string',
            'twitter' => 'nullable|string',
            'linkedin' => 'nullable|string',
            'youtube' => 'nullable|string',
            'courses' => 'nullable|array',
            'courses.*' => 'integer|exists:courses,id',
        ];
    }
}
