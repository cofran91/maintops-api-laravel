<?php

namespace Tests\Feature\Api\Workshops;

use App\Enums\SystemRole;
use App\Exporters\Workshops\WorkshopExporter;
use Carbon\Carbon;
use Database\Seeders\RolesAndAdminUserSeeder;
use Database\Seeders\VehicleSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\Feature\Api\Workshops\Concerns\InteractsWithWorkshops;
use Tests\TestCase;

class ExportWorkshopsTest extends TestCase
{
    use InteractsWithUsers, InteractsWithWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
        $this->seed(VehicleSystemSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_export_localized_workshop_workbook(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'admin.workshop.export@example.com',
            'preferred_locale' => 'es',
        ]);
        $manager = $this->userWithRole(SystemRole::WorkshopManager, [
            'email' => 'manager.workshop.export@example.com',
        ]);
        $technician = $this->userWithRole(SystemRole::Technician, [
            'email' => 'technician.workshop.export@example.com',
        ]);
        $workshop = $this->workshopFor($manager, $this->vehicleSystems(2), [
            'name' => 'Export Workshop',
            'code' => 'EXPORT-WORKSHOP',
            'email' => 'export.workshop@example.com',
        ]);
        $technician->update(['workshop_id' => $workshop->id]);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->get('/api/v1/workshops/export');

        $response
            ->assertOk()
            ->assertHeader('content-type', WorkshopExporter::CONTENT_TYPE)
            ->assertHeader('content-disposition', 'attachment; filename=workshops-2026-06-30.xlsx');

        $spreadsheet = $this->spreadsheetFromResponse($response->baseResponse);
        $dataSheet = $spreadsheet->getSheet(0);
        $documentationSheet = $spreadsheet->getSheet(1);

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Talleres', $dataSheet->getTitle());
        $this->assertSame('Documentación', $documentationSheet->getTitle());
        $this->assertSame('Correo del jefe', $dataSheet->getCell('A1')->getValue());
        $this->assertSame('Nombre', $dataSheet->getCell('B1')->getValue());
        $this->assertSame('manager.workshop.export@example.com', $dataSheet->getCell('A2')->getValue());
        $this->assertSame('Export Workshop', $dataSheet->getCell('B2')->getValue());
        $this->assertSame('EXPORT-WORKSHOP', $dataSheet->getCell('C2')->getValue());
        $this->assertStringContainsString('ENGINE', (string) $dataSheet->getCell('I2')->getValue());
        $this->assertSame('technician.workshop.export@example.com', $dataSheet->getCell('J2')->getValue());
        $this->assertStringContainsString('"monday"', (string) $dataSheet->getCell('K2')->getValue());
        $this->assertSame(WorkshopExporter::REQUIRED_HEADER_COLOR, $dataSheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame(WorkshopExporter::HEADER_COLOR, $dataSheet->getStyle('E1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('Requerido', $documentationSheet->getCell('B1')->getValue());
    }

    public function test_non_system_admin_cannot_export_workshops(): void
    {
        $actor = $this->userWithRole(SystemRole::WorkshopManager, [
            'email' => 'manager.workshop.export.denied@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/workshops/export')
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
