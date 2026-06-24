<?php

use App\Enums\MaintenanceOrderItemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_order_id')->constrained('maintenance_orders')->cascadeOnDelete();
            $table->foreignId('maintenance_task_id')->constrained('maintenance_tasks')->restrictOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained('maintenance_plans')->nullOnDelete();
            $table->string('status', 30)->default(MaintenanceOrderItemStatus::PendingOwnerApproval->value);
            $table->unsignedInteger('odometer_km')->nullable();
            $table->unsignedInteger('planned_duration_minutes')->nullable();
            $table->timestamp('pending_owner_approval_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('scheduled_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['maintenance_order_id', 'maintenance_task_id'], 'mo_items_order_task_unique');
            $table->index('maintenance_order_id');
            $table->index('maintenance_task_id');
            $table->index('maintenance_plan_id');
            $table->index('status');
            $table->index('pending_owner_approval_at');
            $table->index('scheduled_at');
            $table->index('scheduled_ends_at');
            $table->index('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_items');
    }
};
