<?php

// FILE: app/Http/Requests/UpdateInstructorRequest.php

declare(strict_types=1);

namespace App\Http\Requests;

/** Same rules as creation, but `name` is optional on update. */
class UpdateInstructorRequest extends InstructorRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['name' => 'sometimes|string|max:255']);
    }
}
