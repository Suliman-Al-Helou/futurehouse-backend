<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateCourseRequest extends StoreCourseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // كل field "sometimes" عشان ما نطلبه كل مرة
        foreach ($rules as $key => &$rule) {
            if (is_array($rule)) {
                $rule = array_merge(['sometimes'], $rule);
            } else {
                $rule = "sometimes|$rule";
            }
        }
        unset($rule);

        $rules['instructor_id'] = ['sometimes', 'nullable', 'integer', 'exists:instructors,id'];

        return $rules;
    }
}
