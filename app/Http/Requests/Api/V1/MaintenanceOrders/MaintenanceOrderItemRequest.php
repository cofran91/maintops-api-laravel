<?php

namespace App\Http\Requests\Api\V1\MaintenanceOrders;

use App\Enums\MaintenanceOrderItemStatus;
use App\Models\MaintenanceOrderItem;
use App\Models\User;
use App\Rules\MaintenanceOrders\AllowedMaintenanceOrderItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return false;
        }

        $item = $this->route('maintenance_order_item');

        return $item instanceof MaintenanceOrderItem
            && $actor->can('update', $item);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    MaintenanceOrderItemStatus::InProgress->value,
                    MaintenanceOrderItemStatus::Completed->value,
                    MaintenanceOrderItemStatus::Rejected->value,
                    MaintenanceOrderItemStatus::Cancelled->value,
                ]),
                new AllowedMaintenanceOrderItemStatus($this->actor(), $this->maintenanceOrderItem()),
            ],
        ];
    }

    private function actor(): ?User
    {
        $user = $this->user();

        return $user instanceof User ? $user : null;
    }

    private function maintenanceOrderItem(): ?MaintenanceOrderItem
    {
        $item = $this->route('maintenance_order_item');

        return $item instanceof MaintenanceOrderItem ? $item : null;
    }
}
