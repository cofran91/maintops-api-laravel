<?php

namespace App\Http\Controllers\Api\V1\Audits;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Audits\AuditResource;
use App\ModelFilters\AuditFilter;
use App\Models\Audit;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AuditController extends ApiController
{
    /**
     * List audits.
     *
     * Returns audit records from newest to oldest.
     * Only users with the `super_admin` role can query this resource.
     * Business workflows can write aggregate events such as `user created`, `workshop updated`,
     * or `maintenance plan updated` with old and new snapshots.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{
     *             id: int,
     *             event: string,
     *             actor: array{type: string|null, id: int|null, resource: mixed},
     *             auditable: array{type: string, id: int, resource: mixed},
     *             old_values: array<string, mixed>|null,
     *             new_values: array<string, mixed>|null,
     *             url: string|null,
     *             ip_address: string|null,
     *             user_agent: string|null,
     *             tags: string|null,
     *             created_at: string|null,
     *             updated_at: string|null
     *         }>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Searches by event, auditable type, actor type, URL, IP address, or tags.', type: 'string', example: 'user')]
    #[QueryParameter('event', description: 'Filters by exact event. Examples: created, updated, deleted, user created, user updated.', type: 'string', example: 'user updated')]
    #[QueryParameter('user_id', description: 'Filters by the actor ID that executed the change.', type: 'integer', example: 1)]
    #[QueryParameter('user_type', description: 'Filters by actor morph class.', type: 'string', example: 'App\\Models\\User')]
    #[QueryParameter('auditable_id', description: 'Filters by audited resource ID.', type: 'integer', example: 10)]
    #[QueryParameter('auditable_type', description: 'Filters by audited resource morph class.', type: 'string', example: 'App\\Models\\User')]
    #[QueryParameter('ip_address', description: 'Filters by exact IP address.', type: 'string', example: '127.0.0.1')]
    #[QueryParameter('url', description: 'Filters by partial registered URL match.', type: 'string', example: '/api/v1/users')]
    #[QueryParameter('tags', description: 'Filters by partial tag match.', type: 'string', example: 'users')]
    #[QueryParameter('created_from', description: 'Filters audits created from this date, inclusive.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('created_to', description: 'Filters audits created until this date, inclusive.', type: 'date', example: '2026-06-06')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Audit::class);

        $paginator = Audit::query()
            ->with(['user', 'auditable'])
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(AuditFilter::perPage($request));

        return $this->success(
            data: AuditFilter::paginatedResource($paginator, AuditResource::class, $request),
            message: 'Audits retrieved.',
        );
    }
}
