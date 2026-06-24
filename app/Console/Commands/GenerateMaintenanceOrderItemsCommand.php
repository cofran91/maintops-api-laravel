<?php

namespace App\Console\Commands;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\MaintenanceOrderStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\Vehicle;
use App\States\MaintenanceOrders\OrderPendingOwnerApproval;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GenerateMaintenanceOrderItemsCommand extends Command
{
    protected $signature = 'maintenance-orders:generate-items';

    protected $description = 'Generate pending maintenance order items from due plans and direct vehicle tasks.';

    public function handle(): int
    {
        MaintenanceOrder::query()
            ->where('status', MaintenanceOrderStatus::Created->value)
            ->orderBy('id')
            ->chunkById(50, function ($orders): void {
                foreach ($orders as $order) {
                    $this->processOrder($order);
                }
            });

        return self::SUCCESS;
    }

    private function processOrder(MaintenanceOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = MaintenanceOrder::query()
                ->with('vehicle')
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->createPlanItems($order);
            $this->createDirectTaskItems($order);

            if (! $order->items()->exists()) {
                return;
            }

            $order->status->transitionTo(OrderPendingOwnerApproval::class);
        });
    }

    private function createPlanItems(MaintenanceOrder $order): void
    {
        $vehicle = $order->vehicle;

        MaintenancePlan::query()
            ->with('tasks')
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->each(function (MaintenancePlan $plan) use ($order, $vehicle): void {
                if (
                    $this->hasOpenPlanItemForVehicle($vehicle->id, $plan->id, $order->id)
                    || ! $this->planIsDue($vehicle, $plan)
                ) {
                    return;
                }

                $plan->tasks->each(function (MaintenanceTask $task) use ($order, $plan): void {
                    $this->createItem($order, $task, $plan);
                });
            });
    }

    private function createDirectTaskItems(MaintenanceOrder $order): void
    {
        $vehicle = $order->vehicle;

        MaintenanceTask::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', MaintenanceTaskStatus::Created->value)
            ->where('is_active', true)
            ->whereDoesntHave('maintenanceOrderItems', function (Builder $query) use ($vehicle): void {
                $query->whereHas('maintenanceOrder', function (Builder $query) use ($vehicle): void {
                    $query->where('vehicle_id', $vehicle->id);
                });
            })
            ->orderBy('id')
            ->get()
            ->each(function (MaintenanceTask $task) use ($order): void {
                $this->createItem($order, $task);
            });
    }

    private function createItem(MaintenanceOrder $order, MaintenanceTask $task, ?MaintenancePlan $plan = null): void
    {
        $order->items()->firstOrCreate(
            ['maintenance_task_id' => $task->id],
            [
                'maintenance_plan_id' => $plan?->id,
                'odometer_km' => $order->vehicle->odometer_km,
                'planned_duration_minutes' => $task->estimated_duration_minutes,
                'status' => MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                'pending_owner_approval_at' => now(),
            ],
        );
    }

    private function planIsDue(Vehicle $vehicle, MaintenancePlan $plan): bool
    {
        $lastCompletedItem = $this->lastCompletedPlanItem($vehicle->id, $plan->id);

        if ($lastCompletedItem === null) {
            return true;
        }

        return $this->planIsDueByDays($vehicle, $plan, $lastCompletedItem)
            || $this->planIsDueByKilometers($vehicle, $plan, $lastCompletedItem);
    }

    private function planIsDueByDays(
        Vehicle $vehicle,
        MaintenancePlan $plan,
        MaintenanceOrderItem $lastCompletedItem,
    ): bool {
        if ($plan->recommended_interval_days === null) {
            return false;
        }

        $baseline = $lastCompletedItem->finished_at ?? $vehicle->created_at;

        return $baseline->copy()->addDays($plan->recommended_interval_days)->lte(now());
    }

    private function planIsDueByKilometers(
        Vehicle $vehicle,
        MaintenancePlan $plan,
        MaintenanceOrderItem $lastCompletedItem,
    ): bool {
        if ($plan->recommended_interval_km === null || $lastCompletedItem->odometer_km === null) {
            return false;
        }

        return (int) $vehicle->odometer_km - (int) $lastCompletedItem->odometer_km >= $plan->recommended_interval_km;
    }

    private function lastCompletedPlanItem(int $vehicleId, int $planId): ?MaintenanceOrderItem
    {
        return MaintenanceOrderItem::query()
            ->where('maintenance_plan_id', $planId)
            ->where('status', MaintenanceOrderItemStatus::Completed->value)
            ->whereHas('maintenanceOrder', function (Builder $query) use ($vehicleId): void {
                $query->where('vehicle_id', $vehicleId);
            })
            ->latest('finished_at')
            ->latest('id')
            ->first();
    }

    private function hasOpenPlanItemForVehicle(int $vehicleId, int $planId, int $exceptOrderId): bool
    {
        return MaintenanceOrderItem::query()
            ->where('maintenance_plan_id', $planId)
            ->where('maintenance_order_id', '!=', $exceptOrderId)
            ->whereIn('status', [
                MaintenanceOrderItemStatus::PendingOwnerApproval->value,
                MaintenanceOrderItemStatus::Scheduled->value,
                MaintenanceOrderItemStatus::InProgress->value,
            ])
            ->whereHas('maintenanceOrder', function (Builder $query) use ($vehicleId): void {
                $query->where('vehicle_id', $vehicleId);
            })
            ->exists();
    }
}
