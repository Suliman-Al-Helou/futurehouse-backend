<?php

namespace App\Http\Requests;

class UpdateFaqRequest extends FaqRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $key => &$rule) {
            if (is_array($rule)) {
                $rule = array_merge(['sometimes'], $rule);
            } else {
                $rule = "sometimes|$rule";
            }
        }

        return $rules;
    }
}
