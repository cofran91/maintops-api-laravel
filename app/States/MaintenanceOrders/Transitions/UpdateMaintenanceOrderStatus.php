<?php

namespace App\States\MaintenanceOrders\Transitions;

use App\Models\MaintenanceOrder;
use Spatie\ModelStates\DefaultTransition;
use Spatie\ModelStates\State;

class UpdateMaintenanceOrderStatus extends DefaultTransition
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        MaintenanceOrder $model,
        string $field,
        State $newState,
        protected readonly array $attributes = [],
    ) {
        parent::__construct($model, $field, $newState);
    }

    public function handle(): MaintenanceOrder
    {
        $this->model->fill($this->attributes);
        $this->model->{$this->field} = $this->newState;
        $this->model->save();

        return $this->model;
    }

    protected function currentStatus(): string
    {
        return $this->model->{$this->field}->getValue();
    }
}
