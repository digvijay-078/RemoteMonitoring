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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('device_identifier', 64)->unique();
            $table->string('name', 120);
            $table->string('location', 200);
            $table->enum('status', ['pending_pair', 'online', 'warning', 'offline', 'disabled'])->default('pending_pair')->index();
            $table->enum('view_mode', ['single', 'cycle', 'split_2', 'split_4'])->default('single');
            $table->unsignedInteger('cycle_interval_seconds')->default(30);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('hardware_info')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
