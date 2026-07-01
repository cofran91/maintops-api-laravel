<?php

namespace Tests\Feature\Api\Vehicles;

use App\Enums\SystemRole;
use App\Exporters\Vehicles\VehicleExporter;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\Feature\Api\Vehicles\Concerns\InteractsWithVehicles;
use Tests\TestCase;

class ImportVehiclesTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, InteractsWithVehicles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    public function test_admin_can_import_vehicles_and_collect_row_errors(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'admin.vehicle.import@example.com',
        ]);
        $existingOwner = $this->owner(['email' => 'existing.vehicle.owner@example.com']);
        $newOwner = $this->owner(['email' => 'new.vehicle.owner@example.com']);
        $inactiveOwner = $this->owner([
            'email' => 'inactive.vehicle.owner@example.com',
            'is_active' => false,
        ]);
        $this->vehicleFor($existingOwner, [
            'license_plate' => 'EXI123',
            'brand' => 'Old brand',
            'model' => 'Old model',
            'year' => 2020,
            'color' => 'Old color',
            'odometer_km' => 100,
        ]);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/vehicles/import', [
                'file' => $this->vehicleImportFile([
                    ['Owner email', 'License plate', 'Brand', 'Model', 'Year', 'Color', 'Odometer km'],
                    ['EXISTING.VEHICLE.OWNER@example.com', 'exi123', 'Toyota', 'Hilux', 2024, 'White', 15200],
                    ['new.vehicle.owner@example.com', 'new123', 'Mazda', 'CX-5', 2023, 'Blue', 5000],
                    ['inactive.vehicle.owner@example.com', '', str_repeat('B', 101), str_repeat('M', 101), 1899, str_repeat('C', 81), -1],
                    ['', '', '', '', '', '', ''],
                    ['Owner email', 'License plate', 'Brand', 'Model', 'Year', 'Color', 'Odometer km'],
                ]),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Vehicles import processed.')
            ->assertJsonPath('data.processed_rows', 4)
            ->assertJsonPath('data.rows_with_errors', 2)
            ->assertJsonPath('data.created_records', 1)
            ->assertJsonPath('data.updated_records', 1);

        $errors = $response->json('data.errors');

        $this->assertSame(4, $errors[0]['row']);
        $this->assertArrayHasKey('owner_email', $errors[0]['errors']);
        $this->assertArrayHasKey('license_plate', $errors[0]['errors']);
        $this->assertArrayHasKey('brand', $errors[0]['errors']);
        $this->assertArrayHasKey('model', $errors[0]['errors']);
        $this->assertArrayHasKey('year', $errors[0]['errors']);
        $this->assertArrayHasKey('color', $errors[0]['errors']);
        $this->assertArrayHasKey('odometer_km', $errors[0]['errors']);
        $this->assertSame(6, $errors[1]['row']);
        $this->assertArrayHasKey('owner_email', $errors[1]['errors']);
        $this->assertArrayHasKey('odometer_km', $errors[1]['errors']);

        $this->assertDatabaseHas('vehicles', [
            'owner_id' => $existingOwner->id,
            'license_plate' => 'EXI123',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'color' => 'White',
            'odometer_km' => 15200,
        ]);
        $this->assertDatabaseHas('vehicles', [
            'owner_id' => $newOwner->id,
            'license_plate' => 'NEW123',
            'brand' => 'Mazda',
            'model' => 'CX-5',
            'year' => 2023,
            'color' => 'Blue',
            'odometer_km' => 5000,
        ]);
        $this->assertDatabaseMissing('vehicles', [
            'owner_id' => $inactiveOwner->id,
        ]);
    }

    public function test_import_empty_vehicle_file_returns_validation_error(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, [
            'email' => 'super.vehicle.import.empty@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/vehicles/import', [
                'file' => $this->vehicleImportFile([
                    ['Owner email', 'License plate', 'Brand', 'Model', 'Year', 'Color', 'Odometer km'],
                    ['', '', '', '', '', '', ''],
                ]),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The uploaded vehicles file is empty.')
            ->assertJsonValidationErrors('file');
    }

    public function test_non_system_admin_cannot_import_vehicles(): void
    {
        $actor = $this->userWithRole(SystemRole::Advisor, [
            'email' => 'advisor.vehicle.import@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/vehicles/import', [
                'file' => $this->vehicleImportFile([
                    ['Owner email', 'License plate'],
                    ['owner@example.com', 'ABC123'],
                ]),
            ])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function vehicleImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'vehicles-import-');
        $xlsxPath = $path.'.xlsx';

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $xlsxPath,
            'vehicles-import.xlsx',
            VehicleExporter::CONTENT_TYPE,
            null,
            true,
        );
    }
}
