<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('url');
            $t->jsonb('events')->default('[]');
            $t->string('secret');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('failure_count')->default(0);
            $t->timestamp('disabled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $t->string('event');
            $t->jsonb('payload');
            $t->unsignedSmallInteger('attempt')->default(1);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->text('response_excerpt')->nullable();
            $t->string('status');                    // pending | success | failed | giving_up
            $t->timestamp('scheduled_at');
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index(['webhook_id', 'status']);
        });

        Schema::create('automations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->string('trigger_type');             // incoming_sms | keyword | delivery | failed
            $t->jsonb('trigger_config')->default('{}');
            $t->jsonb('actions');                    // [ { type: send_reply, body: "..." }, { type: webhook, url: "..." } ]
            $t->unsignedInteger('execution_count')->default(0);
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
            $t->index(['team_id', 'trigger_type']);
        });

        Schema::create('automation_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $t->ulid('message_id')->nullable();
            $t->foreign('message_id')->references('id')->on('sms_messages')->nullOnDelete();
            $t->string('status'); // success | failed
            $t->jsonb('result')->default('{}');
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->nullable()->index();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('action');                    // user.suspended, gateway.rebooted, key.created, etc.
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->jsonb('payload')->default('{}');
            $t->ipAddress('ip_address')->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
