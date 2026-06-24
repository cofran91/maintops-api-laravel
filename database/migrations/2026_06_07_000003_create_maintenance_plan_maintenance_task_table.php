<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maintenance_plan_maintenance_task', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained('maintenance_plans')->cascadeOnDelete();
            $table->foreignId('maintenance_task_id')->constrained('maintenance_tasks')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['maintenance_plan_id', 'maintenance_task_id'], 'maintenance_plan_task_unique');
            $table->index('maintenance_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_maintenance_task');
    }
};
