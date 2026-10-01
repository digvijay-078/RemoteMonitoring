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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot_number');
            $table->string('name', 100);
            $table->foreignId('dashboard_id')->nullable()->constrained('dashboards')->nullOnDelete();
            $table->json('config_overrides')->nullable();
            $table->unsignedInteger('config_version')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['device_id', 'slot_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
