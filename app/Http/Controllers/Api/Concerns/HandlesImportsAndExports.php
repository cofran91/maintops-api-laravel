<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Exporters\BaseExporter;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ImportRequest;
use App\Importers\BaseImporter;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Exceptions\SheetNotFoundException;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ExcelReaderException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin ApiController
 */
trait HandlesImportsAndExports
{
    protected function downloadExport(BaseExporter $exporter): BinaryFileResponse
    {
        return $exporter->download($exporter->fileName(), Excel::XLSX, [
            'Content-Type' => BaseExporter::CONTENT_TYPE,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    protected function importFromUpload(
        ImportRequest $request,
        BaseImporter $importer,
        string $invalidMessage,
        string $emptyMessage,
        string $successMessage
    ): JsonResponse {
        try {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $result = $importer->import($file);
        } catch (ExcelReaderException|FileNotFoundException|NoTypeDetectedException|PhpSpreadsheetException|SheetNotFoundException) {
            return $this->error(
                message: $invalidMessage,
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['file' => [$invalidMessage]],
            );
        }

        if ($result['processed_rows'] === 0) {
            return $this->error(
                message: $emptyMessage,
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['file' => [$emptyMessage]],
            );
        }

        return $this->success(
            data: $result,
            message: $successMessage,
        );
    }
}
