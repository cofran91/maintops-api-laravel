<?php

namespace App\States\MaintenanceOrders;

use App\States\MaintenanceOrders\Transitions\ApproveMaintenanceOrder;
use App\States\MaintenanceOrders\Transitions\CancelMaintenanceOrder;
use App\States\MaintenanceOrders\Transitions\CompleteMaintenanceOrder;
use App\States\MaintenanceOrders\Transitions\DeliverMaintenanceOrder;
use App\States\MaintenanceOrders\Transitions\RejectMaintenanceOrder;
use App\States\MaintenanceOrders\Transitions\ScheduleMaintenanceOrder;
use App\States\MaintenanceOrders\Transitions\StartMaintenanceOrder;
use App\States\MaintenanceOrders\Transitions\SubmitMaintenanceOrderForOwnerApproval;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class MaintenanceOrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(OrderCreated::class)
            ->registerState([
                OrderCreated::class,
                OrderPendingOwnerApproval::class,
                OrderApproved::class,
                OrderPartiallyApproved::class,
                OrderRejected::class,
                OrderScheduled::class,
                OrderInProgress::class,
                OrderCompleted::class,
                OrderDelivered::class,
                OrderCancelled::class,
            ])
            ->allowTransition(OrderCreated::class, OrderPendingOwnerApproval::class, SubmitMaintenanceOrderForOwnerApproval::class)
            ->allowTransition(OrderCreated::class, OrderRejected::class, RejectMaintenanceOrder::class)
            ->allowTransition(OrderPendingOwnerApproval::class, OrderApproved::class, ApproveMaintenanceOrder::class)
            ->allowTransition(OrderPendingOwnerApproval::class, OrderPartiallyApproved::class, ApproveMaintenanceOrder::class)
            ->allowTransition(OrderPendingOwnerApproval::class, OrderRejected::class, RejectMaintenanceOrder::class)
            ->allowTransition(OrderApproved::class, OrderRejected::class, RejectMaintenanceOrder::class)
            ->allowTransition(OrderApproved::class, OrderScheduled::class, ScheduleMaintenanceOrder::class)
            ->allowTransition(OrderPartiallyApproved::class, OrderRejected::class, RejectMaintenanceOrder::class)
            ->allowTransition(OrderPartiallyApproved::class, OrderScheduled::class, ScheduleMaintenanceOrder::class)
            ->allowTransition(OrderScheduled::class, OrderRejected::class, RejectMaintenanceOrder::class)
            ->allowTransition(OrderScheduled::class, OrderCancelled::class, CancelMaintenanceOrder::class)
            ->allowTransition(OrderScheduled::class, OrderInProgress::class, StartMaintenanceOrder::class)
            ->allowTransition(OrderInProgress::class, OrderCancelled::class, CancelMaintenanceOrder::class)
            ->allowTransition(OrderInProgress::class, OrderCompleted::class, CompleteMaintenanceOrder::class)
            ->allowTransition(OrderCompleted::class, OrderDelivered::class, DeliverMaintenanceOrder::class);
    }
}
