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
        Schema::create('device_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('token_prefix', 16);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['token_hash', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_credentials');
    }
};
