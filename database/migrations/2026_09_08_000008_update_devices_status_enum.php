<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `devices` MODIFY COLUMN `status` ENUM('pending_pair', 'online', 'warning', 'offline', 'disabled') NOT NULL DEFAULT 'pending_pair'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `devices` MODIFY COLUMN `status` ENUM('pending_pair', 'online', 'offline', 'disabled') NOT NULL DEFAULT 'pending_pair'");
        }
    }
};
