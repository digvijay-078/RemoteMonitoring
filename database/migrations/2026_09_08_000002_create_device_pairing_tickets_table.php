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
        Schema::create('device_pairing_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('pairing_code', 16)->unique();
            $table->string('pairing_token', 64)->unique();
            $table->enum('status', ['active', 'used', 'expired', 'revoked'])->default('active')->index();
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pairing_code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_pairing_tickets');
    }
};
