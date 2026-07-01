<?php

namespace App\Http\Controllers\Api\V1\Workshops;

use App\Actions\Workshops\CreateWorkshopAction;
use App\Actions\Workshops\UpdateWorkshopAction;
use App\Exporters\Workshops\WorkshopExporter;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ImportRequest;
use App\Http\Requests\Api\V1\Workshops\WorkshopRequest;
use App\Http\Resources\Api\V1\Workshops\WorkshopResource;
use App\Importers\Workshops\WorkshopImporter;
use App\ModelFilters\WorkshopFilter;
use App\Models\Workshop;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Header;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Exceptions\SheetNotFoundException;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ExcelReaderException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class WorkshopController extends ApiController
{
    /**
     * List workshops.
     *
     * Returns workshops with their assigned manager and served vehicle systems.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         items: array<int, array{
     *             id: int,
     *             manager_user_id: int,
     *             manager: array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null},
     *             name: string,
     *             code: string,
     *             address: string|null,
     *             city: string|null,
     *             phone: string|null,
     *             email: string|null,
     *             weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *             vehicle_system_ids: array<int, int>,
     *             vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *             technician_user_ids: array<int, int>,
     *             technicians: array<int, array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null}>,
     *             is_active: bool,
     *             created_at: string|null,
     *             updated_at: string|null
     *         }>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     *     }
     * }, 200>
     */
    #[QueryParameter('search', description: 'Filters by partial match on name, code, city, email, phone, or manager fields.', type: 'string', example: 'north')]
    #[QueryParameter('name', description: 'Filters by partial match on workshop name.', type: 'string', example: 'North Workshop')]
    #[QueryParameter('code', description: 'Filters by partial match on internal workshop code.', type: 'string', example: 'NORTH')]
    #[QueryParameter('city', description: 'Filters by city.', type: 'string', example: 'Bogota')]
    #[QueryParameter('email', description: 'Filters by workshop email.', type: 'string', example: 'north@maint.test')]
    #[QueryParameter('phone', description: 'Filters by workshop phone.', type: 'string', example: '+57 300')]
    #[QueryParameter('is_active', description: 'Filters active or inactive workshops.', type: 'boolean', example: true)]
    #[QueryParameter('manager_user_id', description: 'Filters by assigned manager user ID.', type: 'integer', example: 12)]
    #[QueryParameter('vehicle_system_id', description: 'Filters workshops that serve a vehicle system ID.', type: 'integer', example: 1)]
    #[QueryParameter('created_from', description: 'Filters workshops created on or after this date.', type: 'date', example: '2026-06-01')]
    #[QueryParameter('created_to', description: 'Filters workshops created on or before this date.', type: 'date', example: '2026-06-30')]
    #[QueryParameter('page', description: 'Requested page number.', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Records per page. Minimum 1, maximum 100.', type: 'integer', example: 15)]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Workshop::class);

        $paginator = Workshop::query()
            ->with(['manager.roles', 'vehicleSystems', 'technicians.roles'])
            ->latest('id')
            ->filter($request->query())
            ->paginateFilter(WorkshopFilter::perPage($request));

        return $this->success(
            data: WorkshopFilter::paginatedResource($paginator, WorkshopResource::class, $request),
            message: __('api.messages.workshops.retrieved'),
        );
    }

    /**
     * Export workshops.
     *
     * Downloads workshop records as a localized Excel workbook.
     *
     * @return BinaryFileResponse<string, 200, array{'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control': 'no-store, no-cache, must-revalidate', 'Pragma': 'no-cache'}, 'attachment'>
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Localized workshops workbook in XLSX format.',
        mediaType: WorkshopExporter::CONTENT_TYPE,
    )]
    #[Header(
        name: 'Content-Disposition',
        description: 'Attachment filename generated as workshops-{download-date}.xlsx.',
        type: 'string',
        example: 'attachment; filename=workshops-2026-06-30.xlsx',
        status: 200,
    )]
    public function export(WorkshopExporter $exporter): BinaryFileResponse
    {
        Gate::authorize('export', Workshop::class);

        return $exporter->download($exporter->fileName(), Excel::XLSX, [
            'Content-Type' => WorkshopExporter::CONTENT_TYPE,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Import workshops.
     *
     * Processes the first worksheet in a workshops workbook. Invalid rows are
     * reported without stopping the rest of the import.
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
        description: 'Workshops XLSX or XLS workbook generated from the export template.',
        required: true,
        type: 'string',
        format: 'binary',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Workshop import summary including per-row validation errors.',
        type: 'array{success: bool, message: string, data: array{processed_rows: int, rows_with_errors: int, created_records: int, updated_records: int, errors: array<int, array{row: int, errors: array<string, array<int, string>>}>}}',
    )]
    public function import(ImportRequest $request, WorkshopImporter $importer): JsonResponse
    {
        Gate::authorize('import', Workshop::class);

        try {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $result = $importer->import($file);
        } catch (ExcelReaderException|FileNotFoundException|NoTypeDetectedException|PhpSpreadsheetException|SheetNotFoundException) {
            return $this->error(
                message: __('api.messages.workshops.import_invalid'),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['file' => [__('api.messages.workshops.import_invalid')]],
            );
        }

        if ($result['processed_rows'] === 0) {
            return $this->error(
                message: __('api.messages.workshops.import_empty'),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['file' => [__('api.messages.workshops.import_empty')]],
            );
        }

        return $this->success(
            data: $result,
            message: __('api.messages.workshops.imported'),
        );
    }

    /**
     * Create workshop.
     *
     * Creates a workshop and synchronizes the vehicle systems it serves.
     *
     * @bodyParam manager_user_id integer required Active user ID with the workshop_manager role. Example: 2
     * @bodyParam name string required Workshop commercial name. Example: North Workshop
     * @bodyParam code string required Unique workshop code. Stored uppercase and slugged. Example: NORTH-WORKSHOP
     * @bodyParam address string|null Workshop physical address. Example: 10 Main Street
     * @bodyParam city string|null City where the workshop operates. Example: Bogota
     * @bodyParam phone string|null Main workshop phone. Example: +57 300 123 4567
     * @bodyParam email string|null Operational workshop email. Example: north@maint.test
     * @bodyParam weekly_schedule object required Weekly schedule by day. Example: {"monday":{"opens_at":"08:00","closes_at":"17:00"}}
     * @bodyParam vehicle_system_ids integer[] required Vehicle system IDs served by the workshop. Example: [1,2,3]
     * @bodyParam technician_user_ids integer[] required Active technician user IDs assigned to the workshop. Send an empty array when none. Example: [15,16]
     * @bodyParam is_active boolean required Whether the workshop is available for operations. Example: true
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         manager_user_id: int,
     *         manager: array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null},
     *         name: string,
     *         code: string,
     *         address: string|null,
     *         city: string|null,
     *         phone: string|null,
     *         email: string|null,
     *         weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *         vehicle_system_ids: array<int, int>,
     *         vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *         technician_user_ids: array<int, int>,
     *         technicians: array<int, array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null}>,
     *         is_active: bool,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 201>
     */
    public function store(WorkshopRequest $request, CreateWorkshopAction $createWorkshopAction): JsonResponse
    {
        $workshop = $createWorkshopAction
            ->execute($request->validated())
            ->load(['manager.roles', 'vehicleSystems', 'technicians.roles']);

        return $this->success(
            data: (new WorkshopResource($workshop))->resolve($request),
            message: __('api.messages.workshops.created'),
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Show workshop.
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         manager_user_id: int,
     *         manager: array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null},
     *         name: string,
     *         code: string,
     *         address: string|null,
     *         city: string|null,
     *         phone: string|null,
     *         email: string|null,
     *         weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *         vehicle_system_ids: array<int, int>,
     *         vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *         technician_user_ids: array<int, int>,
     *         technicians: array<int, array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null}>,
     *         is_active: bool,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 200>
     */
    public function show(Request $request, Workshop $workshop): JsonResponse
    {
        Gate::authorize('view', $workshop);

        return $this->success(
            data: (new WorkshopResource($workshop->load(['manager.roles', 'vehicleSystems', 'technicians.roles'])))->resolve($request),
            message: __('api.messages.workshops.retrieved_one'),
        );
    }

    /**
     * Update workshop.
     *
     * Updates workshop data and replaces the served vehicle systems using the same required fields as create.
     *
     * @bodyParam manager_user_id integer required Active user ID with the workshop_manager role. Example: 2
     * @bodyParam name string required Workshop commercial name. Example: North Workshop
     * @bodyParam code string required Unique workshop code. Stored uppercase and slugged. Example: NORTH-WORKSHOP
     * @bodyParam address string|null Workshop physical address. Example: 10 Main Street
     * @bodyParam city string|null City where the workshop operates. Example: Bogota
     * @bodyParam phone string|null Main workshop phone. Example: +57 300 123 4567
     * @bodyParam email string|null Operational workshop email. Example: north@maint.test
     * @bodyParam weekly_schedule object required Weekly schedule by day. Example: {"monday":{"opens_at":"08:00","closes_at":"17:00"}}
     * @bodyParam vehicle_system_ids integer[] required Vehicle system IDs served by the workshop. Example: [1,2,3]
     * @bodyParam technician_user_ids integer[] required Replaces the active technician user IDs assigned to the workshop. Send an empty array when none. Example: [15,16]
     * @bodyParam is_active boolean required Whether the workshop is available for operations. Example: true
     *
     * @return JsonResponse<array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         id: int,
     *         manager_user_id: int,
     *         manager: array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null},
     *         name: string,
     *         code: string,
     *         address: string|null,
     *         city: string|null,
     *         phone: string|null,
     *         email: string|null,
     *         weekly_schedule: array<string, array{opens_at: string, closes_at: string}>,
     *         vehicle_system_ids: array<int, int>,
     *         vehicle_systems: array<int, array{id: int, code: string, name: string, created_at: string|null, updated_at: string|null}>,
     *         technician_user_ids: array<int, int>,
     *         technicians: array<int, array{id: int, name: string, email: string, preferred_locale: string, roles: array<int, string>, is_active: bool, phone: string|null, document_number: string|null, address: string|null, workshop_id: int|null, email_verified_at: string|null, created_at: string|null, updated_at: string|null}>,
     *         is_active: bool,
     *         created_at: string|null,
     *         updated_at: string|null
     *     }
     * }, 200>
     */
    public function update(
        WorkshopRequest $request,
        Workshop $workshop,
        UpdateWorkshopAction $updateWorkshopAction
    ): JsonResponse {
        $workshop = $updateWorkshopAction
            ->execute($workshop, $request->validated())
            ->load(['manager.roles', 'vehicleSystems', 'technicians.roles']);

        return $this->success(
            data: (new WorkshopResource($workshop))->resolve($request),
            message: __('api.messages.workshops.updated'),
        );
    }

    /**
     * Delete workshop.
     *
     * Soft deletes a workshop record.
     *
     * @return JsonResponse<array{success: bool, message: string}, 200>
     */
    public function destroy(Workshop $workshop): JsonResponse
    {
        Gate::authorize('delete', $workshop);

        $workshop->delete();

        return $this->success(message: __('api.messages.workshops.deleted'));
    }
}
