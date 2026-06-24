<?php

namespace App\Http\Requests\Api\V1\MaintenanceOrders;

use App\Enums\MaintenanceOrderItemStatus;
use App\Enums\SystemRole;
use App\Models\MaintenanceOrderItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateStatusPermission($validator);
        });
    }

    private function validateStatusPermission(Validator $validator): void
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return;
        }

        $status = $this->input('status');

        $allowed = match (true) {
            $actor->hasRole([
                SystemRole::SuperAdmin->value,
                SystemRole::Admin->value,
            ]) => [
                MaintenanceOrderItemStatus::InProgress->value,
                MaintenanceOrderItemStatus::Completed->value,
                MaintenanceOrderItemStatus::Rejected->value,
                MaintenanceOrderItemStatus::Cancelled->value,
            ],
            $actor->hasRole(SystemRole::WorkshopManager->value) => [
                MaintenanceOrderItemStatus::Cancelled->value,
            ],
            $actor->hasRole(SystemRole::Advisor->value) => [
                MaintenanceOrderItemStatus::Rejected->value,
            ],
            $actor->hasRole(SystemRole::Technician->value)
                && ! $actor->hasRole(SystemRole::Advisor->value) => [
                    MaintenanceOrderItemStatus::InProgress->value,
                    MaintenanceOrderItemStatus::Completed->value,
                ],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            $validator->errors()->add('status', 'The authenticated role cannot apply this item status change.');
        }
    }
}
