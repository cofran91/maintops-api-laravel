<?php

namespace Tests\Feature\Api\Workshops;

use App\Enums\SystemRole;
use App\Exporters\Workshops\WorkshopExporter;
use App\Models\VehicleSystem;
use App\Models\Workshop;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\Feature\Api\Workshops\Concerns\InteractsWithWorkshops;
use Tests\TestCase;

class ImportWorkshopsTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
    }

    public function test_admin_can_import_workshops_and_collect_row_errors(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'admin.workshop.import@example.com',
        ]);
        $existingManager = $this->userWithRole(SystemRole::WorkshopManager, [
            'email' => 'existing.manager.import@example.com',
        ]);
        $newManager = $this->userWithRole(SystemRole::WorkshopManager, [
            'email' => 'new.manager.import@example.com',
        ]);
        $technician = $this->userWithRole(SystemRole::Technician, [
            'email' => 'technician.import@example.com',
        ]);
        $advisor = $this->userWithRole(SystemRole::Advisor, [
            'email' => 'advisor.import@example.com',
        ]);
        $systems = VehicleSystem::query()->pluck('id', 'code');
        $this->workshopFor($existingManager, $this->vehicleSystems(1), [
            'name' => 'Existing Import Workshop',
            'code' => 'EXISTING-IMPORT-WORKSHOP',
        ]);

        $schedule = '{"monday":{"opens_at":"08:00","closes_at":"17:00"},"tuesday":{"opens_at":"08:00","closes_at":"17:00"}}';

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/workshops/import', [
                'file' => $this->workshopImportFile([
                    [
                        'Manager email',
                        'Name',
                        'Code',
                        'Active',
                        'Address',
                        'City',
                        'Phone',
                        'Workshop email',
                        'Vehicle system codes',
                        'Technician emails',
                        'Weekly schedule',
                    ],
                    [
                        'EXISTING.MANAGER.IMPORT@example.com',
                        'Existing Import Workshop Updated',
                        'existing import workshop',
                        'false',
                        'Updated address',
                        'Bogota',
                        '+57 300 111 1111',
                        'updated.workshop@example.com',
                        'BRAKES, ELECTRICAL',
                        'technician.import@example.com',
                        $schedule,
                    ],
                    [
                        'new.manager.import@example.com',
                        'New Import Workshop',
                        'new import workshop',
                        'true',
                        'New address',
                        'Medellin',
                        '+57 300 222 2222',
                        'new.workshop@example.com',
                        'ENGINE',
                        '',
                        $schedule,
                    ],
                    [
                        'advisor.import@example.com',
                        '',
                        '',
                        'maybe',
                        '',
                        '',
                        str_repeat('1', 51),
                        'not-an-email',
                        'UNKNOWN',
                        'advisor.import@example.com',
                        '{bad json',
                    ],
                    ['', '', '', '', '', '', '', '', '', '', ''],
                    [
                        'Manager email',
                        'Name',
                        'Code',
                        'Active',
                        'Address',
                        'City',
                        'Phone',
                        'Workshop email',
                        'Vehicle system codes',
                        'Technician emails',
                        'Weekly schedule',
                    ],
                ]),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Workshops import processed.')
            ->assertJsonPath('data.processed_rows', 4)
            ->assertJsonPath('data.rows_with_errors', 2)
            ->assertJsonPath('data.created_records', 1)
            ->assertJsonPath('data.updated_records', 1);

        $errors = $response->json('data.errors');

        $this->assertSame(4, $errors[0]['row']);
        $this->assertArrayHasKey('manager_email', $errors[0]['errors']);
        $this->assertArrayHasKey('name', $errors[0]['errors']);
        $this->assertArrayHasKey('code', $errors[0]['errors']);
        $this->assertArrayHasKey('is_active', $errors[0]['errors']);
        $this->assertArrayHasKey('phone', $errors[0]['errors']);
        $this->assertArrayHasKey('email', $errors[0]['errors']);
        $this->assertArrayHasKey('vehicle_system_codes.0', $errors[0]['errors']);
        $this->assertArrayHasKey('technician_emails.0', $errors[0]['errors']);
        $this->assertArrayHasKey('weekly_schedule', $errors[0]['errors']);
        $this->assertSame(6, $errors[1]['row']);
        $this->assertArrayHasKey('manager_email', $errors[1]['errors']);
        $this->assertArrayHasKey('is_active', $errors[1]['errors']);
        $this->assertArrayHasKey('vehicle_system_codes.0', $errors[1]['errors']);
        $this->assertArrayHasKey('technician_emails.0', $errors[1]['errors']);
        $this->assertArrayHasKey('weekly_schedule', $errors[1]['errors']);

        $existingWorkshop = Workshop::query()
            ->where('code', 'EXISTING-IMPORT-WORKSHOP')
            ->firstOrFail();
        $newWorkshop = Workshop::query()
            ->where('code', 'NEW-IMPORT-WORKSHOP')
            ->firstOrFail();

        $this->assertSame('Existing Import Workshop Updated', $existingWorkshop->name);
        $this->assertFalse((bool) $existingWorkshop->is_active);
        $this->assertSame('updated.workshop@example.com', $existingWorkshop->email);
        $this->assertDatabaseHas('vehicle_system_workshop', [
            'workshop_id' => $existingWorkshop->id,
            'vehicle_system_id' => $systems['BRAKES'],
        ]);
        $this->assertDatabaseHas('vehicle_system_workshop', [
            'workshop_id' => $existingWorkshop->id,
            'vehicle_system_id' => $systems['ELECTRICAL'],
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $technician->id,
            'workshop_id' => $existingWorkshop->id,
        ]);
        $this->assertSame($newManager->id, $newWorkshop->manager_user_id);
        $this->assertSame('New Import Workshop', $newWorkshop->name);
    }

    public function test_import_empty_workshop_file_returns_validation_error(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, [
            'email' => 'super.workshop.import.empty@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/workshops/import', [
                'file' => $this->workshopImportFile([
                    ['Manager email', 'Name', 'Code', 'Active', 'Address', 'City', 'Phone', 'Workshop email', 'Vehicle system codes', 'Technician emails', 'Weekly schedule'],
                    ['', '', '', '', '', '', '', '', '', '', ''],
                ]),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The uploaded workshops file is empty.')
            ->assertJsonValidationErrors('file');
    }

    public function test_non_system_admin_cannot_import_workshops(): void
    {
        $actor = $this->userWithRole(SystemRole::WorkshopManager, [
            'email' => 'manager.workshop.import.denied@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/workshops/import', [
                'file' => $this->workshopImportFile([
                    ['Manager email', 'Name', 'Code', 'Active'],
                    ['manager@example.com', 'Denied Workshop', 'DENIED-WORKSHOP', 'true'],
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
    private function workshopImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'workshops-import-');
        $xlsxPath = $path.'.xlsx';

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $xlsxPath,
            'workshops-import.xlsx',
            WorkshopExporter::CONTENT_TYPE,
            null,
            true,
        );
    }
}
