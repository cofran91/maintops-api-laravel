<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_event_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 120);
            $table->string('aggregate_type', 80);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload');
            $table->json('targets');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id'], 'operational_event_outboxes_aggregate_index');
            $table->index(['published_at', 'id'], 'operational_event_outboxes_publish_index');
            $table->index('event_type', 'operational_event_outboxes_event_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_event_outboxes');
    }
};
