<?php

namespace Tests\Feature\Api\Owners;

use App\Enums\SystemRole;
use App\Exporters\Owners\OwnerExporter;
use Carbon\Carbon;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ExportOwnersTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, RefreshDatabase;

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

    public function test_admin_can_export_localized_owner_workbook(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'admin.owner.export@example.com',
            'preferred_locale' => 'es',
        ]);
        $this->owner([
            'name' => 'Maria Owner',
            'email' => 'maria.owner@example.com',
            'is_active' => true,
            'phone' => '+57 300 123 4567',
            'document_number' => 'MARIA-OWNER',
            'address' => 'Calle 10 #20-30',
        ]);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->get('/api/v1/owners/export');

        $response
            ->assertOk()
            ->assertHeader('content-type', OwnerExporter::CONTENT_TYPE)
            ->assertHeader('content-disposition', 'attachment; filename=owners-2026-06-30.xlsx');

        $spreadsheet = $this->spreadsheetFromResponse($response->baseResponse);
        $dataSheet = $spreadsheet->getSheet(0);
        $documentationSheet = $spreadsheet->getSheet(1);

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Propietarios', $dataSheet->getTitle());
        $this->assertSame('Documentación', $documentationSheet->getTitle());
        $this->assertSame('Nombre', $dataSheet->getCell('A1')->getValue());
        $this->assertSame('Correo', $dataSheet->getCell('B1')->getValue());
        $this->assertSame('Maria Owner', $dataSheet->getCell('A2')->getValue());
        $this->assertSame('maria.owner@example.com', $dataSheet->getCell('B2')->getValue());
        $this->assertTrue((bool) $dataSheet->getCell('C2')->getValue());
        $this->assertSame(OwnerExporter::REQUIRED_HEADER_COLOR, $dataSheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame(OwnerExporter::HEADER_COLOR, $dataSheet->getStyle('D1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('Requerido', $documentationSheet->getCell('B1')->getValue());
        $this->assertSame('Descripción', $documentationSheet->getCell('C1')->getValue());
    }

    public function test_super_admin_can_export_empty_english_template(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, [
            'email' => 'super.owner.export@example.com',
            'preferred_locale' => 'en',
        ]);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->get('/api/v1/owners/export');

        $response->assertOk();

        $spreadsheet = $this->spreadsheetFromResponse($response->baseResponse);
        $dataSheet = $spreadsheet->getSheet(0);
        $documentationSheet = $spreadsheet->getSheet(1);

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Owners', $dataSheet->getTitle());
        $this->assertSame('Documentation', $documentationSheet->getTitle());
        $this->assertSame('A1:F1', $dataSheet->calculateWorksheetDimension());
        $this->assertSame('Name', $dataSheet->getCell('A1')->getValue());
        $this->assertSame('Email', $dataSheet->getCell('B1')->getValue());
        $this->assertSame('Required', $documentationSheet->getCell('B1')->getValue());
        $this->assertSame('Description', $documentationSheet->getCell('C1')->getValue());
    }

    #[DataProvider('nonExportingRoleProvider')]
    public function test_non_system_admin_roles_cannot_export_owners(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, [
            'email' => $role->value.'.owner.export@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/owners/export')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_inactive_admin_cannot_export_owners(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'inactive.owner.export@example.com',
            'is_active' => false,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->getJson('/api/v1/owners/export')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_export_owners(): void
    {
        $this->getJson('/api/v1/owners/export')->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonExportingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }

    private function spreadsheetFromResponse(object $response): Spreadsheet
    {
        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        return IOFactory::load($response->getFile()->getPathname());
    }
}
