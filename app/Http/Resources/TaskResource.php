<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'questions' => $this->questions,
            'pass_percentage' => $this->pass_percentage,
            'max_attempts' => $this->max_attempts,
        ];
    }
}
