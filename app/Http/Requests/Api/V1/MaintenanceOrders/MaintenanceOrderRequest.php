<?php

namespace App\Http\Requests\Api\V1\MaintenanceOrders;

use App\Enums\MaintenanceOrderStatus;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Rules\MaintenanceOrders\ActiveMaintenanceOrderAdvisor;
use App\Rules\MaintenanceOrders\AllowedMaintenanceOrderStatus;
use App\Rules\MaintenanceOrders\VehicleWithoutOpenMaintenanceOrder;
use App\Support\MaintenanceOrders\MaintenanceOrderAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @ignoreSchema
 */
class MaintenanceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->actor();

        if (! $actor instanceof User) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', MaintenanceOrder::class);
        }

        $maintenanceOrder = $this->route('maintenance_order');

        return $maintenanceOrder instanceof MaintenanceOrder
            && $actor->can('update', $maintenanceOrder);
    }

    protected function prepareForValidation(): void
    {
        $actor = $this->actor();

        if (! $this->isMethod('post') || ! $actor instanceof User) {
            return;
        }

        if (! $this->has('advisor_id') || $this->input('advisor_id') === '' || MaintenanceOrderAccess::isPrimaryAdvisor($actor)) {
            $this->merge(['advisor_id' => $actor->getKey()]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->isMethod('post')
            ? $this->createRules()
            : $this->updateRules();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function createRules(): array
    {
        return [
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->whereNull('deleted_at'),
                new VehicleWithoutOpenMaintenanceOrder,
            ],
            'advisor_id' => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                new ActiveMaintenanceOrderAdvisor,
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function updateRules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    MaintenanceOrderStatus::Approved->value,
                    MaintenanceOrderStatus::Cancelled->value,
                    MaintenanceOrderStatus::Delivered->value,
                    MaintenanceOrderStatus::Rejected->value,
                ]),
                new AllowedMaintenanceOrderStatus($this->actor(), $this->maintenanceOrder()),
            ],
        ];
    }

    private function actor(): ?User
    {
        $actor = $this->user();

        return $actor instanceof User ? $actor : null;
    }

    private function maintenanceOrder(): ?MaintenanceOrder
    {
        $maintenanceOrder = $this->route('maintenance_order');

        return $maintenanceOrder instanceof MaintenanceOrder ? $maintenanceOrder : null;
    }
}
