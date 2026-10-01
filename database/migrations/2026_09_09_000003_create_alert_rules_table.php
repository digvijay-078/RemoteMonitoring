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
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('metric', 64);
            $table->string('operator', 16); // >, >=, <, <=, ==, !=, STALE, OFFLINE
            $table->double('threshold_numeric')->nullable();
            $table->string('threshold_text', 255)->nullable();
            $table->boolean('threshold_boolean')->nullable();
            $table->enum('severity', ['INFO', 'WARNING', 'CRITICAL'])->default('WARNING');
            $table->unsignedInteger('duration_seconds')->default(0); // Debounce time
            $table->unsignedInteger('stale_after_seconds')->nullable(); // For STALE operator
            $table->unsignedInteger('cooldown_seconds')->default(0); // Post-resolution cooldown
            $table->boolean('enabled')->default(true);
            $table->json('device_scope')->nullable(); // null for all devices, or array of device IDs
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['metric', 'enabled'], 'idx_alert_rules_metric_enabled');
            $table->index('enabled', 'idx_alert_rules_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
