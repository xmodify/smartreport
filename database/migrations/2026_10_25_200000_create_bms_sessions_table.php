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
        Schema::create('bms_sessions', function (Blueprint $table) {
            $table->id();

            // Client device/browser identifier (from cookie or generated UUID)
            $table->string('device_key', 64)->index();

            // Unique BMS Session ID from HOSxP URL parameter
            $table->string('bms_session_id', 120)->unique();

            // Tunnel URL of the hospital (e.g. https://xxx.tunnel.hosxp.net)
            $table->string('bms_url', 255);
            $table->string('bms_direct_url', 255)->nullable();

            // Access Token for hospital gateway (encrypted in database)
            $table->text('bms_session_code');

            // User and Hospital contextual metadata
            $table->string('hospital_code', 20)->nullable()->index();
            $table->string('hospital_name', 255)->nullable();
            $table->string('user_id', 50)->nullable()->index();
            $table->string('user_name', 255)->nullable();
            $table->string('department_id', 30)->nullable();
            $table->string('department_name', 255)->nullable();

            // Recent patient context
            $table->string('last_hn', 20)->nullable();
            $table->string('last_vn', 30)->nullable();

            // Network telemetry
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Expiration timestamp (used for choosing session with max remaining time)
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_active_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bms_sessions');
    }
};
