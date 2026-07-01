<?php

namespace App\Http\Controllers\Api\V1\Owners;

use App\Exporters\Owners\OwnerExporter;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\HandlesImportsAndExports;
use App\Http\Requests\Api\V1\ImportRequest;
use App\Http\Requests\Api\V1\Owners\OwnerRequest;
use App\Http\Resources\Api\V1\Owners\OwnerResource;
use App\Importers\Owners\OwnerImporter;
use App\ModelFilters\OwnerFilter;
use App\Models\Owner;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OwnerController extends ApiController
{
    use HandlesImportsAndExports;

    /**
     * List owners.
     *
     * Returns registered vehicle owners managed outside the user account model.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by partial match on name, email, phone, or document number.', type: 'string', example: 'maria')]
    #[QueryParameter('is_active', description: 'Filters active or inactive owners.', type: 'boolean', example: true)]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Owner::class);

        return $this->paginatedResourceResponse(
            request: $request,
            query: Owner::query()->latest('id'),
            filter: OwnerFilter::class,
            resource: OwnerResource::class,
            message: __('api.messages.owners.retrieved'),
        );
    }

    /**
     * Create owner.
     *
     * Creates a vehicle owner contact. Owners are domain records and do not sign in.
     *
     * @bodyParam name string required Full owner name. Example: Maria Perez
     * @bodyParam email string required Unique contact email. Example: owner@example.com
     * @bodyParam is_active boolean required Whether the owner can be assigned to vehicles. Example: true
     * @bodyParam phone string|null Main phone number. Example: +57 300 123 4567
     * @bodyParam document_number string|null Unique document number when provided. Example: 123456789
     * @bodyParam address string|null Contact address. Example: 10 Main Street
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}
     * }, 201>
     */
    public function store(OwnerRequest $request): JsonResponse
    {
        $owner = Owner::query()->create($request->validated());

        return $this->createdResourceResponse(
            request: $request,
            resource: $owner,
            resourceClass: OwnerResource::class,
            message: __('api.messages.owners.created'),
        );
    }

    /**
     * Show owner.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}
     * }, 200>
     */
    public function show(Request $request, Owner $owner): JsonResponse
    {
        Gate::authorize('view', $owner);

        return $this->resourceResponse(
            request: $request,
            resource: $owner,
            resourceClass: OwnerResource::class,
            message: __('api.messages.owners.retrieved_one'),
        );
    }

    /**
     * Update owner.
     *
     * Updates an owner using the same required fields as create.
     *
     * @bodyParam name string required Full owner name. Example: Maria Perez
     * @bodyParam email string required Unique contact email. Example: owner@example.com
     * @bodyParam is_active boolean required Whether the owner can be assigned to vehicles. Example: true
     * @bodyParam phone string|null Main phone number. Example: +57 300 123 4567
     * @bodyParam document_number string|null Unique document number when provided. Example: 123456789
     * @bodyParam address string|null Contact address. Example: 10 Main Street
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{id: int, name: string, email: string, is_active: bool, phone: string|null, document_number: string|null, address: string|null, created_at: string|null, updated_at: string|null}
     * }, 200>
     */
    public function update(OwnerRequest $request, Owner $owner): JsonResponse
    {
        $owner->update($request->validated());

        return $this->resourceResponse(
            request: $request,
            resource: $owner->refresh(),
            resourceClass: OwnerResource::class,
            message: __('api.messages.owners.updated'),
        );
    }

    /**
     * Delete owner.
     *
     * Soft deletes an owner record.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(Owner $owner): JsonResponse
    {
        Gate::authorize('delete', $owner);

        return $this->deleteResourceAndRespond($owner, __('api.messages.owners.deleted'));
    }

    /**
     * Export owners.
     *
     * Downloads owner records as a localized Excel workbook. The workbook can be
     * used as exported data or as an import template.
     *
     * @return BinaryFileResponse<string, 200, array{'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control': 'no-store, no-cache, must-revalidate', 'Pragma': 'no-cache'}, 'attachment'>
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Localized owners workbook in XLSX format.',
        mediaType: OwnerExporter::CONTENT_TYPE,
    )]
    #[Header(
        name: 'Content-Disposition',
        description: 'Attachment filename generated as owners-{download-date}.xlsx.',
        type: 'string',
        example: 'attachment; filename=owners-2026-06-30.xlsx',
        status: 200,
    )]
    #[Header(
        name: 'Cache-Control',
        description: 'Prevents clients and intermediaries from caching exported owner data.',
        type: 'string',
        example: 'no-store, no-cache, must-revalidate',
        status: 200,
    )]
    #[Header(
        name: 'Pragma',
        description: 'Legacy no-cache directive.',
        type: 'string',
        example: 'no-cache',
        status: 200,
    )]
    public function export(OwnerExporter $exporter): BinaryFileResponse
    {
        Gate::authorize('export', Owner::class);

        return $this->downloadExport($exporter);
    }

    /**
     * Import owners.
     *
     * Processes the first worksheet in a localized owners workbook. Empty rows
     * and repeated header rows are ignored. Invalid rows are reported without
     * stopping the rest of the import.
     *
     * @requestMediaType multipart/form-data
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         processed_rows: int,
     *         rows_with_errors: int,
     *         created_records: int,
     *         updated_records: int,
     *         errors: array<int, array{row: int, errors: array<string, array<int, string>>}>
     *     }
     * }, 200>
     */
    #[BodyParameter(
        'file',
        description: 'Owners XLSX or XLS workbook generated from the export template.',
        required: true,
        type: 'string',
        format: 'binary',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Owner import summary including per-row validation errors.',
        type: 'array{success: bool, message: string, data: array{processed_rows: int, rows_with_errors: int, created_records: int, updated_records: int, errors: array<int, array{row: int, errors: array<string, array<int, string>>}>}}',
    )]
    public function import(ImportRequest $request, OwnerImporter $importer): JsonResponse
    {
        Gate::authorize('import', Owner::class);

        return $this->importFromUpload(
            request: $request,
            importer: $importer,
            invalidMessage: __('api.messages.owners.import_invalid'),
            emptyMessage: __('api.messages.owners.import_empty'),
            successMessage: __('api.messages.owners.imported'),
        );
    }
}
