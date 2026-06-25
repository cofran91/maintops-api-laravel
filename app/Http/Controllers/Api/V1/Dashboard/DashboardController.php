<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Dashboard\DashboardResource;
use App\Models\MaintenanceOrder;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends ApiController
{
    /**
     * Show scoped operational dashboard.
     *
     * Returns transactional dashboard data from Laravel: order counts by status, operational metrics,
     * today's schedules, and upcoming schedules. The response is scoped with the same role visibility
     * used by maintenance orders.
     */
    public function __invoke(Request $request, DashboardService $dashboardService): JsonResponse
    {
        Gate::authorize('viewAny', MaintenanceOrder::class);

        return $this->success(
            data: (new DashboardResource($dashboardService->forActor($request->user())))->resolve($request),
            message: 'Dashboard retrieved.',
        );
    }
}
