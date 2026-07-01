<?php

namespace Tests\Feature\Api\Vehicles;

use App\Enums\SystemRole;
use App\Exporters\Vehicles\VehicleExporter;
use Carbon\Carbon;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\Feature\Api\Vehicles\Concerns\InteractsWithVehicles;
use Tests\TestCase;

class ExportVehiclesTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, InteractsWithVehicles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_export_localized_vehicle_workbook(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'admin.vehicle.export@example.com',
            'preferred_locale' => 'es',
        ]);
        $owner = $this->owner([
            'name' => 'Vehicle Owner',
            'email' => 'vehicle.owner@example.com',
        ]);
        $this->vehicleFor($owner, [
            'license_plate' => 'ABC123',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'color' => 'Blanco',
            'odometer_km' => 15200,
        ]);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->get('/api/v1/vehicles/export');

        $response
            ->assertOk()
            ->assertHeader('content-type', VehicleExporter::CONTENT_TYPE)
            ->assertHeader('content-disposition', 'attachment; filename=vehicles-2026-06-30.xlsx');

        $spreadsheet = $this->spreadsheetFromResponse($response->baseResponse);
        $dataSheet = $spreadsheet->getSheet(0);
        $documentationSheet = $spreadsheet->getSheet(1);

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Vehículos', $dataSheet->getTitle());
        $this->assertSame('Documentación', $documentationSheet->getTitle());
        $this->assertSame('Correo del propietario', $dataSheet->getCell('A1')->getValue());
        $this->assertSame('Placa', $dataSheet->getCell('B1')->getValue());
        $this->assertSame('vehicle.owner@example.com', $dataSheet->getCell('A2')->getValue());
        $this->assertSame('ABC123', $dataSheet->getCell('B2')->getValue());
        $this->assertSame(15200, $dataSheet->getCell('G2')->getValue());
        $this->assertSame(VehicleExporter::REQUIRED_HEADER_COLOR, $dataSheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame(VehicleExporter::HEADER_COLOR, $dataSheet->getStyle('C1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('Requerido', $documentationSheet->getCell('B1')->getValue());
    }

    public function test_non_system_admin_cannot_export_vehicles(): void
    {
        $actor = $this->userWithRole(SystemRole::Advisor, [
            'email' => 'advisor.vehicle.export@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/vehicles/export')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    private function spreadsheetFromResponse(object $response): Spreadsheet
    {
        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        return IOFactory::load($response->getFile()->getPathname());
    }
}
