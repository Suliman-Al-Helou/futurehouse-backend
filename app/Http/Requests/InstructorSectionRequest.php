<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class InstructorSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Course|null $course */
        $course = $this->route('course');
        /** @var Section|null $section */
        $section = $this->route('section');

        if ($course instanceof Course) {
            return Gate::allows('manageAsInstructor', $course);
        }

        if ($section instanceof Section) {
            $parentCourse = $section->course;
            if (! $parentCourse) {
                abort(404, 'الكورس المرتبط بالقسم غير موجود');
            }

            return Gate::allows('manageAsInstructor', $parentCourse);
        }

        return false;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'min:2', 'max:255'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الفصل مطلوب',
            'title.min' => 'يجب أن يتكون عنوان الفصل من حرفين على الأقل',
            'title.max' => 'يجب ألا يتجاوز عنوان الفصل 255 حرفاً',
            'order.integer' => 'ترتيب الفصل يجب أن يكون رقماً صحيحاً',
            'order.min' => 'ترتيب الفصل يجب ألا يقل عن 0',
        ];
    }
}
