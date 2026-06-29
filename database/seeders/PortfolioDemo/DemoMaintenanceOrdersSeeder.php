<?php

namespace Database\Seeders\PortfolioDemo;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoMaintenanceOrdersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $vehiclesByLicensePlate = Vehicle::query()->get()->keyBy('license_plate');
        $userIdsByEmail = User::query()->pluck('id', 'email');
        $workshopIdsByCode = Workshop::query()->pluck('id', 'code');
        $tasksByCode = MaintenanceTask::query()->get()->keyBy('code');
        $planIdsByCode = MaintenancePlan::query()->pluck('id', 'code');

        foreach ($this->orders() as $attributes) {
            $items = $attributes['items'];
            $licensePlate = $attributes['license_plate'];
            $advisorEmail = $attributes['advisor_email'];
            $workshopCode = $attributes['workshop_code'] ?? null;
            $technicianEmail = $attributes['technician_email'] ?? null;

            unset(
                $attributes['items'],
                $attributes['license_plate'],
                $attributes['advisor_email'],
                $attributes['workshop_code'],
                $attributes['technician_email'],
            );

            $vehicle = $vehiclesByLicensePlate[$licensePlate];

            $order = MaintenanceOrder::withTrashed()->updateOrCreate(
                ['vehicle_id' => $vehicle->id],
                array_merge($attributes, [
                    'advisor_id' => $userIdsByEmail[$advisorEmail],
                    'workshop_id' => $workshopCode === null ? null : $workshopIdsByCode[$workshopCode],
                    'technician_id' => $technicianEmail === null ? null : $userIdsByEmail[$technicianEmail],
                ]),
            );

            if ($order->trashed()) {
                $order->restore();
            }

            foreach ($items as $itemAttributes) {
                $this->seedItem($order, $vehicle, $itemAttributes, $tasksByCode, $planIdsByCode);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  Collection<string, MaintenanceTask>  $tasksByCode
     * @param  Collection<string, int>  $planIdsByCode
     */
    private function seedItem(
        MaintenanceOrder $order,
        Vehicle $vehicle,
        array $attributes,
        Collection $tasksByCode,
        Collection $planIdsByCode,
    ): void {
        $taskCode = $attributes['task_code'];
        $planCode = $attributes['plan_code'] ?? null;

        unset($attributes['task_code'], $attributes['plan_code']);

        $task = $tasksByCode[$taskCode];

        $item = MaintenanceOrderItem::withTrashed()->updateOrCreate(
            [
                'maintenance_order_id' => $order->id,
                'maintenance_task_id' => $task->id,
            ],
            array_merge([
                'maintenance_plan_id' => $planCode === null ? null : $planIdsByCode[$planCode],
                'odometer_km' => $vehicle->odometer_km,
                'planned_duration_minutes' => $task->estimated_duration_minutes,
            ], $attributes),
        );

        if ($item->trashed()) {
            $item->restore();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orders(): array
    {
        $today = Carbon::today();

        return [
            [
                'license_plate' => 'DEMO101',
                'advisor_email' => 'advisor.north@maint.test',
                'status' => MaintenanceOrderStatus::Created->value,
                'scheduled_at' => null,
                'started_at' => null,
                'finished_at' => null,
                'delivered_at' => null,
                'cancelled_at' => null,
                'items' => [],
            ],
            [
                'license_plate' => 'DEMO201',
                'advisor_email' => 'advisor.north@maint.test',
                'status' => MaintenanceOrderStatus::PendingOwnerApproval->value,
                'scheduled_at' => null,
                'started_at' => null,
                'finished_at' => null,
                'delivered_at' => null,
                'cancelled_at' => null,
                'items' => [
                    [
                        'task_code' => 'DEMO-DIRECT-DEMO201-BRAKE-PEDAL',
                        'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                        'pending_owner_approval_at' => $today->copy()->subDay()->setTime(10, 15),
                    ],
                    [
                        'task_code' => 'DEMO-TASK-ENGINE-INSPECTION',
                        'plan_code' => 'DEMO-PLAN-CORE-PREVENTIVE',
                        'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                        'pending_owner_approval_at' => $today->copy()->subDay()->setTime(10, 15),
                    ],
                ],
            ],
            [
                'license_plate' => 'DEMO301',
                'advisor_email' => 'advisor.north@maint.test',
                'status' => MaintenanceOrderStatus::Approved->value,
                'scheduled_at' => null,
                'started_at' => null,
                'finished_at' => null,
                'delivered_at' => null,
                'cancelled_at' => null,
                'items' => [
                    [
                        'task_code' => 'DEMO-TASK-ENGINE-INSPECTION',
                        'plan_code' => 'DEMO-PLAN-CORE-PREVENTIVE',
                        'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(2)->setTime(9, 30),
                    ],
                    [
                        'task_code' => 'DEMO-TASK-BRAKES-INSPECTION',
                        'plan_code' => 'DEMO-PLAN-CORE-PREVENTIVE',
                        'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(2)->setTime(9, 30),
                    ],
                ],
            ],
            [
                'license_plate' => 'DEMO401',
                'advisor_email' => 'advisor.north@maint.test',
                'status' => MaintenanceOrderStatus::PartiallyApproved->value,
                'scheduled_at' => null,
                'started_at' => null,
                'finished_at' => null,
                'delivered_at' => null,
                'cancelled_at' => null,
                'items' => [
                    [
                        'task_code' => 'DEMO-DIRECT-DEMO401-BRAKE-VIBRATION',
                        'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(2)->setTime(14, 0),
                    ],
                    [
                        'task_code' => 'DEMO-TASK-ELECTRICAL-DIAGNOSTIC',
                        'plan_code' => 'DEMO-PLAN-CORE-PREVENTIVE',
                        'status' => MaintenanceOrderItemStatus::Rejected->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(2)->setTime(14, 0),
                        'rejected_at' => $today->copy()->subDay()->setTime(16, 10),
                    ],
                ],
            ],
            [
                'license_plate' => 'DEMO501',
                'advisor_email' => 'advisor.south@maint.test',
                'workshop_code' => 'DEMO-WORKSHOP-NORTH',
                'technician_email' => 'technician.engine@maint.test',
                'status' => MaintenanceOrderStatus::Scheduled->value,
                'scheduled_at' => $today->copy()->addDay()->setTime(9, 0),
                'started_at' => null,
                'finished_at' => null,
                'delivered_at' => null,
                'cancelled_at' => null,
                'items' => [
                    [
                        'task_code' => 'DEMO-DIRECT-DEMO501-ELECTRICAL-INTERMITTENT',
                        'status' => MaintenanceOrderItemStatus::Scheduled->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(3)->setTime(11, 0),
                        'scheduled_at' => $today->copy()->addDay()->setTime(9, 0),
                        'scheduled_ends_at' => $today->copy()->addDay()->setTime(10, 15),
                    ],
                    [
                        'task_code' => 'DEMO-TASK-COOLING-CHECK',
                        'plan_code' => 'DEMO-PLAN-CORE-PREVENTIVE',
                        'status' => MaintenanceOrderItemStatus::Scheduled->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(3)->setTime(11, 0),
                        'scheduled_at' => $today->copy()->addDay()->setTime(10, 15),
                        'scheduled_ends_at' => $today->copy()->addDay()->setTime(11, 0),
                    ],
                ],
            ],
            [
                'license_plate' => 'DEMO601',
                'advisor_email' => 'advisor.south@maint.test',
                'workshop_code' => 'DEMO-WORKSHOP-SOUTH',
                'technician_email' => 'technician.suspension@maint.test',
                'status' => MaintenanceOrderStatus::InProgress->value,
                'scheduled_at' => $today->copy()->setTime(8, 30),
                'started_at' => $today->copy()->setTime(8, 45),
                'finished_at' => null,
                'delivered_at' => null,
                'cancelled_at' => null,
                'items' => [
                    [
                        'task_code' => 'DEMO-DIRECT-DEMO601-SUSPENSION-NOISE',
                        'status' => MaintenanceOrderItemStatus::Completed->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(4)->setTime(9, 0),
                        'scheduled_at' => $today->copy()->setTime(8, 30),
                        'scheduled_ends_at' => $today->copy()->setTime(9, 40),
                        'started_at' => $today->copy()->setTime(8, 45),
                        'finished_at' => $today->copy()->setTime(9, 35),
                    ],
                    [
                        'task_code' => 'DEMO-TASK-TIRES-ROTATION-CHECK',
                        'plan_code' => 'DEMO-PLAN-CHASSIS-SAFETY',
                        'status' => MaintenanceOrderItemStatus::InProgress->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(4)->setTime(9, 0),
                        'scheduled_at' => $today->copy()->setTime(9, 45),
                        'scheduled_ends_at' => $today->copy()->setTime(10, 30),
                        'started_at' => $today->copy()->setTime(9, 50),
                    ],
                ],
            ],
            [
                'license_plate' => 'DEMO701',
                'advisor_email' => 'advisor.south@maint.test',
                'workshop_code' => 'DEMO-WORKSHOP-SOUTH',
                'technician_email' => 'technician.suspension@maint.test',
                'status' => MaintenanceOrderStatus::Delivered->value,
                'scheduled_at' => $today->copy()->subDays(6)->setTime(8, 0),
                'started_at' => $today->copy()->subDays(6)->setTime(8, 10),
                'finished_at' => $today->copy()->subDays(6)->setTime(11, 45),
                'delivered_at' => $today->copy()->subDays(6)->setTime(15, 30),
                'cancelled_at' => null,
                'items' => [
                    [
                        'task_code' => 'DEMO-TASK-FUEL-SYSTEM-CHECK',
                        'plan_code' => 'DEMO-PLAN-POWERTRAIN-FLUIDS',
                        'status' => MaintenanceOrderItemStatus::Completed->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(8)->setTime(13, 0),
                        'scheduled_at' => $today->copy()->subDays(6)->setTime(8, 0),
                        'scheduled_ends_at' => $today->copy()->subDays(6)->setTime(8, 50),
                        'started_at' => $today->copy()->subDays(6)->setTime(8, 10),
                        'finished_at' => $today->copy()->subDays(6)->setTime(8, 55),
                    ],
                    [
                        'task_code' => 'DEMO-TASK-HYDRAULIC-CHECK',
                        'plan_code' => 'DEMO-PLAN-POWERTRAIN-FLUIDS',
                        'status' => MaintenanceOrderItemStatus::Completed->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(8)->setTime(13, 0),
                        'scheduled_at' => $today->copy()->subDays(6)->setTime(9, 0),
                        'scheduled_ends_at' => $today->copy()->subDays(6)->setTime(10, 5),
                        'started_at' => $today->copy()->subDays(6)->setTime(9, 5),
                        'finished_at' => $today->copy()->subDays(6)->setTime(10, 10),
                    ],
                ],
            ],
            [
                'license_plate' => 'DEMO801',
                'advisor_email' => 'advisor.north@maint.test',
                'workshop_code' => 'DEMO-WORKSHOP-SOUTH',
                'technician_email' => 'technician.suspension@maint.test',
                'status' => MaintenanceOrderStatus::Cancelled->value,
                'scheduled_at' => $today->copy()->subDays(2)->setTime(13, 0),
                'started_at' => null,
                'finished_at' => null,
                'delivered_at' => null,
                'cancelled_at' => $today->copy()->subDay()->setTime(8, 30),
                'items' => [
                    [
                        'task_code' => 'DEMO-DIRECT-DEMO801-BODYWORK-DAMAGE',
                        'status' => MaintenanceOrderItemStatus::Cancelled->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(3)->setTime(10, 0),
                        'scheduled_at' => $today->copy()->subDays(2)->setTime(13, 0),
                        'scheduled_ends_at' => $today->copy()->subDays(2)->setTime(13, 55),
                        'cancelled_at' => $today->copy()->subDay()->setTime(8, 30),
                    ],
                    [
                        'task_code' => 'DEMO-TASK-TIRES-ROTATION-CHECK',
                        'plan_code' => 'DEMO-PLAN-CHASSIS-SAFETY',
                        'status' => MaintenanceOrderItemStatus::Cancelled->value,
                        'pending_owner_approval_at' => $today->copy()->subDays(3)->setTime(10, 0),
                        'scheduled_at' => $today->copy()->subDays(2)->setTime(13, 55),
                        'scheduled_ends_at' => $today->copy()->subDays(2)->setTime(14, 40),
                        'cancelled_at' => $today->copy()->subDay()->setTime(8, 30),
                    ],
                ],
            ],
        ];
    }
}
