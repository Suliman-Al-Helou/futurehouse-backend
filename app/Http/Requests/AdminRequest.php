<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdminRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'level' => 'required|in:beginner,intermediate,advanced',
            'status' => 'required|in:draft,published,coming_soon',
            'total_duration' => 'nullable|integer',
            'what_you_learn' => 'nullable|array',
            'requirements' => 'nullable|array',
            'target_audience' => 'nullable|array',
            'cover_image' => 'nullable|string',
            'instructor_name' => 'nullable|string',
            'rating' => 'nullable|numeric|min:0|max:5',
            'is_popular' => 'nullable|boolean',
            'price' => 'nullable|numeric|min:0',
            'is_public' => 'sometimes|boolean',
            'instructor_id' => 'nullable|integer|exists:instructors,id',
        ];
    }
}
