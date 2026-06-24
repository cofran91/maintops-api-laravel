<?php

namespace App\States\MaintenanceOrderItems;

use App\States\MaintenanceOrderItems\Transitions\CancelMaintenanceOrderItem;
use App\States\MaintenanceOrderItems\Transitions\CompleteMaintenanceOrderItem;
use App\States\MaintenanceOrderItems\Transitions\RejectMaintenanceOrderItem;
use App\States\MaintenanceOrderItems\Transitions\StartMaintenanceOrderItem;
use App\States\MaintenanceOrderItems\Transitions\UpdateMaintenanceOrderItemStatus;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class MaintenanceOrderItemState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(OrderItemPendingOwnerApproval::class)
            ->registerState([
                OrderItemPendingOwnerApproval::class,
                OrderItemScheduled::class,
                OrderItemInProgress::class,
                OrderItemCompleted::class,
                OrderItemRejected::class,
                OrderItemCancelled::class,
            ])
            ->allowTransition(OrderItemPendingOwnerApproval::class, OrderItemScheduled::class, UpdateMaintenanceOrderItemStatus::class)
            ->allowTransition(OrderItemPendingOwnerApproval::class, OrderItemRejected::class, RejectMaintenanceOrderItem::class)
            ->allowTransition(OrderItemScheduled::class, OrderItemRejected::class, RejectMaintenanceOrderItem::class)
            ->allowTransition(OrderItemScheduled::class, OrderItemCancelled::class, CancelMaintenanceOrderItem::class)
            ->allowTransition(OrderItemScheduled::class, OrderItemInProgress::class, StartMaintenanceOrderItem::class)
            ->allowTransition(OrderItemInProgress::class, OrderItemCancelled::class, CancelMaintenanceOrderItem::class)
            ->allowTransition(OrderItemInProgress::class, OrderItemCompleted::class, CompleteMaintenanceOrderItem::class);
    }
}
