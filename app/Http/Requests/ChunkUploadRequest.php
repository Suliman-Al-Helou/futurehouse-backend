<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChunkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chunk' => ['required', 'file', 'max:51200'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'upload_id' => ['required', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'file_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_\-\. ]+$/'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'zoom_meeting_id' => ['nullable', 'integer', 'exists:zoom_meetings,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'in:recording,lesson'],
        ];
    }
}
