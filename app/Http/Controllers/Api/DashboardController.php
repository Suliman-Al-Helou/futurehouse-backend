<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentDashboardResource;
use App\Models\User;
use App\Queries\StudentDashboardQuery;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(private readonly StudentDashboardQuery $query) {}

    public function stats(Request $request): StudentDashboardResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return new StudentDashboardResource($this->query->forUser($user));
    }
}
