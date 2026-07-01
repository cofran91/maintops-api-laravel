<?php

namespace Tests\Feature\Api\Owners;

use App\Enums\SystemRole;
use App\Exporters\Owners\OwnerExporter;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Owners\Concerns\InteractsWithOwners;
use Tests\Feature\Api\Users\Concerns\InteractsWithUsers;
use Tests\TestCase;

class ImportOwnersTest extends TestCase
{
    use InteractsWithOwners, InteractsWithUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    public function test_admin_can_import_owners_and_collect_row_errors(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'admin.owner.import@example.com',
            'preferred_locale' => 'en',
        ]);
        $this->owner([
            'name' => 'Existing Owner',
            'email' => 'existing.owner@example.com',
            'is_active' => true,
            'phone' => '+57 300 000 0000',
            'document_number' => 'DOC-1',
            'address' => 'Old address',
        ]);
        $this->owner([
            'email' => 'taken.email@example.com',
            'document_number' => 'TAKEN-EMAIL-DOC',
        ]);
        $this->owner([
            'email' => 'taken.document@example.com',
            'document_number' => 'TAKEN-DOC',
        ]);

        $response = $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/owners/import', [
                'file' => $this->ownerImportFile([
                    ['Name', 'Email', 'Active', 'Phone', 'Document', 'Address'],
                    ['Existing Updated', 'EXISTING.OWNER@example.com', 'false', '+57 300 111 1111', 'DOC-1', 'New address'],
                    ['New Owner', 'new.owner@example.com', 'true', '+57 300 222 2222', 'NEW-DOC', 'Created address'],
                    ['', 'not-an-email', 'maybe', str_repeat('1', 51), str_repeat('2', 101), str_repeat('3', 501)],
                    ['Conflict Owner', 'taken.email@example.com', 'true', '', 'TAKEN-DOC', ''],
                    ['', '', '', '', '', ''],
                    ['Name', 'Email', 'Active', 'Phone', 'Document', 'Address'],
                ]),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Owners import processed.')
            ->assertJsonPath('data.processed_rows', 5)
            ->assertJsonPath('data.rows_with_errors', 3)
            ->assertJsonPath('data.created_records', 1)
            ->assertJsonPath('data.updated_records', 1);

        $errors = $response->json('data.errors');

        $this->assertCount(3, $errors);
        $this->assertSame(4, $errors[0]['row']);
        $this->assertArrayHasKey('name', $errors[0]['errors']);
        $this->assertArrayHasKey('email', $errors[0]['errors']);
        $this->assertArrayHasKey('is_active', $errors[0]['errors']);
        $this->assertArrayHasKey('phone', $errors[0]['errors']);
        $this->assertArrayHasKey('document_number', $errors[0]['errors']);
        $this->assertArrayHasKey('address', $errors[0]['errors']);
        $this->assertSame(5, $errors[1]['row']);
        $this->assertArrayHasKey('email', $errors[1]['errors']);
        $this->assertArrayHasKey('document_number', $errors[1]['errors']);
        $this->assertSame(7, $errors[2]['row']);
        $this->assertArrayHasKey('email', $errors[2]['errors']);
        $this->assertArrayHasKey('is_active', $errors[2]['errors']);

        $this->assertDatabaseHas('owners', [
            'email' => 'existing.owner@example.com',
            'document_number' => 'DOC-1',
            'name' => 'Existing Updated',
            'is_active' => false,
            'phone' => '+57 300 111 1111',
            'address' => 'New address',
        ]);
        $this->assertDatabaseHas('owners', [
            'email' => 'new.owner@example.com',
            'document_number' => 'NEW-DOC',
            'name' => 'New Owner',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('owners', [
            'email' => 'not-an-email',
        ]);
    }

    public function test_import_empty_file_returns_validation_error(): void
    {
        $actor = $this->userWithRole(SystemRole::SuperAdmin, [
            'email' => 'super.owner.import.empty@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/owners/import', [
                'file' => $this->ownerImportFile([
                    ['Nombre', 'Correo', 'Activo', 'Telefono', 'Documento', 'Direccion'],
                    ['', '', '', '', '', ''],
                ]),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The uploaded owners file is empty.')
            ->assertJsonValidationErrors('file');
    }

    #[DataProvider('nonImportingRoleProvider')]
    public function test_non_system_admin_roles_cannot_import_owners(SystemRole $role): void
    {
        $actor = $this->userWithRole($role, [
            'email' => $role->value.'.owner.import@example.com',
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/owners/import', [
                'file' => $this->ownerImportFile([
                    ['Name', 'Email', 'Active'],
                    ['Denied Owner', 'denied.owner@example.com', true],
                ]),
            ])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_inactive_admin_cannot_import_owners(): void
    {
        $actor = $this->userWithRole(SystemRole::Admin, [
            'email' => 'inactive.owner.import@example.com',
            'is_active' => false,
        ]);

        $this->withToken($actor->createToken('feature-test')->plainTextToken)
            ->post('/api/v1/owners/import', [
                'file' => $this->ownerImportFile([
                    ['Name', 'Email', 'Active'],
                    ['Inactive Admin Owner', 'inactive.admin.owner@example.com', true],
                ]),
            ])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_guest_cannot_import_owners(): void
    {
        $this->postJson('/api/v1/owners/import')->assertUnauthorized();
    }

    /**
     * @return iterable<string, array{SystemRole}>
     */
    public static function nonImportingRoleProvider(): iterable
    {
        yield 'workshop manager' => [SystemRole::WorkshopManager];
        yield 'advisor' => [SystemRole::Advisor];
        yield 'technician' => [SystemRole::Technician];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function ownerImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'owners-import-');
        $xlsxPath = $path.'.xlsx';

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $xlsxPath,
            'owners-import.xlsx',
            OwnerExporter::CONTENT_TYPE,
            null,
            true,
        );
    }
}
