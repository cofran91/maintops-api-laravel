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
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
            $table->string('license_plate', 30)->unique();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 80)->nullable();
            $table->unsignedInteger('odometer_km')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index(['brand', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
