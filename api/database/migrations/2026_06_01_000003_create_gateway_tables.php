<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gateways', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('kind');                              // trb140, huawei, mock
            $t->string('host')->nullable();
            $t->unsignedSmallInteger('port')->default(80);
            $t->string('protocol', 8)->default('http');      // http | https
            $t->string('username')->nullable();
            $t->text('password')->nullable();                // encrypted at rest
            $t->string('modem_id')->nullable();
            $t->boolean('ssh_enabled')->default(false);
            $t->text('ssh_key_ref')->nullable();
            $t->unsignedSmallInteger('rate_per_minute')->default(6);
            $t->unsignedInteger('daily_cap')->nullable();
            $t->string('status')->default('online');         // online, offline, degraded
            $t->jsonb('health')->default('{}');              // last known signal, sim, uptime
            $t->timestamp('last_seen_at')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['team_id', 'status']);
        });

        Schema::create('sims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gateway_id')->constrained()->cascadeOnDelete();
            $t->string('iccid')->nullable()->unique();
            $t->string('msisdn')->nullable();
            $t->string('imsi')->nullable();
            $t->string('carrier')->nullable();
            $t->string('country', 2)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('balance_ore')->nullable();
            $t->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('gateway_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sim_id')->nullable()->constrained()->nullOnDelete();
            $t->ulid('batch_id')->nullable()->index();
            $t->ulid('campaign_id')->nullable()->index();
            $t->string('direction', 8);                      // outbound | inbound
            $t->string('from')->nullable();
            $t->string('to');
            $t->text('body');
            $t->unsignedSmallInteger('segments')->default(1);
            $t->string('encoding', 16)->default('GSM7');
            $t->string('status');                            // queued | sent | delivered | failed | received
            $t->string('provider_id')->nullable();
            $t->string('error_code')->nullable();
            $t->string('error_message')->nullable();
            $t->jsonb('metadata')->default('{}');
            $t->unsignedInteger('cost_ore')->default(0);
            $t->timestamp('queued_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
            $t->index(['team_id', 'created_at']);
            $t->index(['team_id', 'status']);
            $t->index(['team_id', 'direction', 'to']);
        });

        Schema::create('sms_message_events', function (Blueprint $t) {
            $t->id();
            $t->ulid('message_id');
            $t->foreign('message_id')->references('id')->on('sms_messages')->cascadeOnDelete();
            $t->string('type');                              // queued, attempted, sent, delivered, failed
            $t->jsonb('payload')->default('{}');
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['message_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_message_events');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sims');
        Schema::dropIfExists('gateways');
    }
};
