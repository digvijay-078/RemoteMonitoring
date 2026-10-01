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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('metric', 64);
            $table->enum('severity', ['INFO', 'WARNING', 'CRITICAL']);
            $table->enum('state', ['ACTIVE', 'ACKNOWLEDGED', 'RESOLVED'])->default('ACTIVE');
            $table->double('observed_value_numeric')->nullable();
            $table->string('observed_value_text', 255)->nullable();
            $table->boolean('observed_value_boolean')->nullable();
            $table->double('threshold_numeric')->nullable();
            $table->string('message', 255);
            $table->dateTime('first_violated_at')->nullable();
            $table->dateTime('triggered_at');
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('resolution_source', ['system', 'manual'])->nullable();
            $table->dateTime('last_observed_at')->nullable();
            $table->unsignedInteger('trigger_count')->default(1);
            $table->json('metadata')->nullable();

            // Mandatory Safeguard 1: Database-enforced unresolved alert uniqueness
            // Set to "{alert_rule_id}_{device_id}" when unresolved; set to NULL when resolved.
            // In MySQL, unique indexes allow multiple NULLs but strictly 1 non-null key.
            $table->string('unresolved_key', 64)->nullable()->unique('uniq_unresolved_alert');

            $table->timestamps();

            $table->index(['device_id', 'state'], 'idx_alerts_device_state');
            $table->index(['alert_rule_id', 'device_id', 'state'], 'idx_alerts_rule_device_state');
            $table->index(['severity', 'state'], 'idx_alerts_severity_state');
            $table->index('triggered_at', 'idx_alerts_triggered');
            $table->index('state', 'idx_alerts_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
