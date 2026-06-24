<?php

namespace App\States\MaintenanceTasks;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class MaintenanceTaskState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(TaskCreated::class)
            ->registerState([
                TaskCreated::class,
                TaskScheduled::class,
                TaskStarted::class,
                TaskCancelled::class,
                TaskCompleted::class,
                TaskRejected::class,
            ])
            ->allowTransition(TaskCreated::class, TaskScheduled::class)
            ->allowTransition(TaskCreated::class, TaskCancelled::class)
            ->allowTransition(TaskCreated::class, TaskRejected::class)
            ->allowTransition(TaskScheduled::class, TaskStarted::class)
            ->allowTransition(TaskScheduled::class, TaskCancelled::class)
            ->allowTransition(TaskScheduled::class, TaskRejected::class)
            ->allowTransition(TaskStarted::class, TaskCancelled::class)
            ->allowTransition(TaskStarted::class, TaskCompleted::class)
            ->allowTransition(TaskCompleted::class, TaskCancelled::class)
            ->allowTransition(TaskRejected::class, TaskCancelled::class);
    }
}
