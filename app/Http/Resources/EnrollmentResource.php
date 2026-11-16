<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_confirmed' => $this->status === 'approved',
            'created_at' => $this->created_at,
            'student_name' => $this->user?->name,
            'student_email' => $this->user?->email,
            'course_title' => $this->course?->title,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'course' => $this->course ? [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'price' => $this->course->price ?? 0,
                'level' => $this->course->level ?? 'beginner',
            ] : null,
        ];
    }
}
