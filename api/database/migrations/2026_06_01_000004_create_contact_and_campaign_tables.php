<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('msisdn');
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
            $t->jsonb('attributes')->default('{}');
            $t->string('opt_in_status')->default('opted_in'); // opted_in | opted_out | pending
            $t->timestamp('opt_in_at')->nullable();
            $t->timestamp('opt_out_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['team_id', 'msisdn']);
        });

        Schema::create('contact_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('color', 16)->nullable();
            $t->jsonb('rules')->default('{}'); // dynamic group rules
            $t->timestamps();
        });

        Schema::create('contact_group_contact', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['contact_group_id', 'contact_id']);
        });

        Schema::create('contact_tags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unique(['team_id', 'name']);
        });

        Schema::create('contact_tag_contact', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contact_tag_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $t->unique(['contact_tag_id', 'contact_id']);
        });

        Schema::create('templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('body');
            $t->jsonb('variables')->default('[]');
            $t->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('status')->default('draft');     // draft | scheduled | running | paused | completed | failed
            $t->text('body');
            $t->jsonb('targets')->default('{}');         // group_ids, tag_ids, contact_ids
            $t->string('timezone')->default('Europe/Copenhagen');
            $t->timestamp('scheduled_at')->nullable();
            $t->string('recurrence')->nullable();        // RRULE
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->unsignedInteger('total_recipients')->default(0);
            $t->unsignedInteger('sent_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->timestamps();
            $t->index(['team_id', 'status']);
        });

        Schema::create('campaign_messages', function (Blueprint $t) {
            $t->id();
            $t->ulid('campaign_id');
            $t->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $t->ulid('message_id')->nullable();
            $t->foreign('message_id')->references('id')->on('sms_messages')->nullOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $t->string('to');
            $t->string('status')->default('pending');
            $t->timestamps();
            $t->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_messages');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('contact_tag_contact');
        Schema::dropIfExists('contact_tags');
        Schema::dropIfExists('contact_group_contact');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('contacts');
    }
};
