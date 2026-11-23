<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SubmitTaskRequest extends FormRequest
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
            'answers' => ['required', 'array'],
        ];
    }

    /**
     * Dynamically validate that an answer is provided for every question in the task.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lesson = $this->route('lesson');
            if (! $lesson) {
                return;
            }

            $task = $lesson->task ?? $lesson->loadMissing('task')->task;
            if (! $task || empty($task->questions)) {
                return;
            }

            $userAnswers = $this->input('answers');
            if (! is_array($userAnswers)) {
                return;
            }

            $missing = false;
            foreach ($task->questions as $index => $q) {
                $qId = (string) ($q['id'] ?? $index + 1);

                $hasAnswer = array_key_exists($qId, $userAnswers)
                    || (is_numeric($qId) && array_key_exists((int) $qId, $userAnswers));

                if (! $hasAnswer) {
                    $missing = true;
                    break;
                }

                $ans = $userAnswers[$qId] ?? $userAnswers[(int) $qId] ?? null;

                // Zero-Index Option Guard & Blank Protection:
                // Answer must not be null, must not be empty string, and must be numeric or valid string
                if (is_null($ans) || $ans === '' || (! is_numeric($ans) && ! is_string($ans))) {
                    $missing = true;
                    break;
                }
            }

            if ($missing) {
                $validator->errors()->add('answers', 'يجب الإجابة على جميع الأسئلة لتسليم المهمة');
            }
        });
    }

    /**
     * Format validation response to match the unified Arabic message contract.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException(
            $validator,
            response()->json([
                'message' => 'يجب الإجابة على جميع الأسئلة لتسليم المهمة',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
