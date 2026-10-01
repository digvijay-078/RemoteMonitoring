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
        // 1. Drop obsolete tables safely
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('device_telemetry');
        Schema::dropIfExists('monitors');
        Schema::dropIfExists('dashboards');

        // 2. Extend devices table with screen mirroring columns
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'device_type')) {
                $table->enum('device_type', ['desktop', 'tablet'])->default('tablet')->after('uuid');
            }
            if (!Schema::hasColumn('devices', 'stream_status')) {
                $table->enum('stream_status', ['idle', 'streaming', 'offline'])->default('offline')->after('status');
            }
            if (!Schema::hasColumn('devices', 'active_viewers_count')) {
                $table->unsignedInteger('active_viewers_count')->default(0)->after('stream_status');
            }
            if (!Schema::hasColumn('devices', 'screen_resolution')) {
                $table->string('screen_resolution', 32)->nullable()->after('active_viewers_count');
            }
            if (!Schema::hasColumn('devices', 'fps')) {
                $table->unsignedInteger('fps')->default(30)->after('screen_resolution');
            }
        });

        // 3. Create desktop_tablet_mappings table (Many-to-Many authorization)
        if (!Schema::hasTable('desktop_tablet_mappings')) {
            Schema::create('desktop_tablet_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tablet_id')->constrained('devices')->cascadeOnDelete();
                $table->foreignId('desktop_id')->constrained('devices')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tablet_id', 'desktop_id'], 'uniq_tablet_desktop');
                $table->index('tablet_id', 'idx_mapping_tablet');
                $table->index('desktop_id', 'idx_mapping_desktop');
            });
        }

        // 4. Create webrtc_sessions table (Live session tracking and audit)
        if (!Schema::hasTable('webrtc_sessions')) {
            Schema::create('webrtc_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64)->unique();
                $table->foreignId('desktop_id')->constrained('devices')->cascadeOnDelete();
                $table->foreignId('tablet_id')->constrained('devices')->cascadeOnDelete();
                $table->enum('status', ['initiating', 'connected', 'terminated', 'failed'])->default('initiating');
                $table->dateTime('started_at');
                $table->dateTime('connected_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                $table->string('termination_reason', 128)->nullable();
                $table->timestamps();

                $table->index(['desktop_id', 'status'], 'idx_session_desktop');
                $table->index(['tablet_id', 'status'], 'idx_session_tablet');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webrtc_sessions');
        Schema::dropIfExists('desktop_tablet_mappings');

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'device_type',
                'stream_status',
                'active_viewers_count',
                'screen_resolution',
                'fps',
            ]);
        });
    }
};
