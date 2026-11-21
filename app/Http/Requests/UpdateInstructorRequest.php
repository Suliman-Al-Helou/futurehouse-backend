<?php

// FILE: app/Http/Requests/UpdateInstructorRequest.php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Validation\Rule;

/** Same rules as creation, but `name` is optional on update. */
class UpdateInstructorRequest extends InstructorRequest
{
    public function rules(): array
    {
        $routeParam = $this->route('instructor') ?? $this->route('id');
        $instructorId = $routeParam instanceof Instructor ? $routeParam->id : $routeParam;

        return array_merge(parent::rules(), [
            'name' => 'sometimes|string|max:255',
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                Rule::unique('instructors', 'user_id')->ignore($instructorId),
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
        ]);
    }
}
