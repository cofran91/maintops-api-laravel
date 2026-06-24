<?php

namespace Tests\Feature\Api\MaintenanceOrders;

use App\Enums\SystemRole;
use Database\Seeders\RolesAndAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Api\MaintenanceOrders\Concerns\InteractsWithMaintenanceOrders;
use Tests\TestCase;

class CreateMaintenanceOrderItemTest extends TestCase
{
    use InteractsWithMaintenanceOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndAdminUserSeeder::class);
    }

    public function test_order_item_creation_endpoint_is_not_available(): void
    {
        $admin = $this->userWithRole(SystemRole::Admin, ['email' => 'admin.item.create.disabled@example.com']);

        $this->withToken($admin->createToken('feature-test')->plainTextToken)
            ->postJson('/api/v1/maintenance-order-items', [])
            ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
