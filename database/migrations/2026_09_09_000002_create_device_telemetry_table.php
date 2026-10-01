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
        Schema::create('device_telemetry', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('metric', 64);
            $table->double('value_numeric')->nullable();
            $table->string('value_text', 255)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('unit', 32)->nullable();
            $table->dateTime('captured_at', 3);
            $table->dateTime('received_at', 3);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            // Compound indexes for high-throughput queries & idempotency
            $table->unique(['device_id', 'sequence'], 'uniq_device_sequence');
            $table->index(['device_id', 'captured_at'], 'idx_device_captured');
            $table->index(['device_id', 'metric', 'captured_at'], 'idx_device_metric_captured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_telemetry');
    }
};
