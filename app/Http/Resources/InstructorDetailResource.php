<?php

// FILE: app/Http/Resources/InstructorDetailResource.php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

class InstructorDetailResource extends InstructorResource
{
    /** Base instructor payload plus the course summaries. */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'courses' => CourseSummaryResource::collection($this->whenLoaded('courses')),
        ]);
    }
}
