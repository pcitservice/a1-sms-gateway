<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gateways', function (Blueprint $t) {
            // Stable device identifier — usually the TRB140's IMEI or serial.
            $t->string('device_id')->nullable()->after('kind');
            // Per-device MQTT credentials. Password is encrypted at rest.
            $t->string('mqtt_username')->nullable()->after('modem_id');
            $t->text('mqtt_password')->nullable()->after('mqtt_username');
            // Last time the agent sent a heartbeat / connected.
            $t->timestamp('agent_last_seen_at')->nullable()->after('last_seen_at');
            // Free-text version string reported by the agent (e.g. "1.2.0-rutos").
            $t->string('agent_version')->nullable()->after('agent_last_seen_at');

            $t->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $t) {
            $t->dropColumn(['device_id', 'mqtt_username', 'mqtt_password', 'agent_last_seen_at', 'agent_version']);
        });
    }
};
