<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ModelFilters\ModelFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    protected function success(
        array|object|null $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message ?? __('api.messages.ok'),
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function error(
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<ModelFilter>  $filter
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginatedResourceResponse(
        Request $request,
        Builder $query,
        string $filter,
        string $resource,
        string $message
    ): JsonResponse {
        $paginator = $query
            ->filter($request->query())
            ->paginateFilter($filter::perPage($request));

        return $this->success(
            data: $filter::paginatedResource($paginator, $resource, $request),
            message: $message,
        );
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function resourceResponse(
        Request $request,
        mixed $resource,
        string $resourceClass,
        string $message,
        int $status = Response::HTTP_OK
    ): JsonResponse {
        return $this->success(
            data: (new $resourceClass($resource))->resolve($request),
            message: $message,
            status: $status,
        );
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function createdResourceResponse(
        Request $request,
        mixed $resource,
        string $resourceClass,
        string $message
    ): JsonResponse {
        return $this->resourceResponse(
            request: $request,
            resource: $resource,
            resourceClass: $resourceClass,
            message: $message,
            status: Response::HTTP_CREATED,
        );
    }

    protected function deleteResourceAndRespond(Model $resource, string $message): JsonResponse
    {
        $resource->delete();

        return $this->success(message: $message);
    }
}
