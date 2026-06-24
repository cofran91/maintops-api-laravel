<?php

namespace App\States\MaintenanceOrderItems\Transitions;

use App\Models\MaintenanceOrderItem;
use App\States\MaintenanceOrderItems\OrderItemScheduled;
use App\States\MaintenanceTasks\TaskCreated;
use App\States\MaintenanceTasks\TaskScheduled;
use Spatie\ModelStates\DefaultTransition;
use Spatie\ModelStates\State;

class UpdateMaintenanceOrderItemStatus extends DefaultTransition
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        MaintenanceOrderItem $model,
        string $field,
        State $newState,
        protected readonly array $attributes = [],
    ) {
        parent::__construct($model, $field, $newState);
    }

    public function handle(): MaintenanceOrderItem
    {
        $this->model->fill($this->attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        $this->scheduleLinkedVehicleTask();

        return $this->model;
    }

    protected function currentStatus(): string
    {
        return $this->model->{$this->field}->getValue();
    }

    private function scheduleLinkedVehicleTask(): void
    {
        if (! $this->newState->equals(OrderItemScheduled::class)) {
            return;
        }

        $task = $this->model->maintenanceTask()->first();

        if ($task === null || $task->vehicle_id === null || ! $task->status->equals(TaskCreated::class)) {
            return;
        }

        $task->status->transitionTo(TaskScheduled::class);
    }
}
