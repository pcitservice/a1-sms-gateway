<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique(); // /l/{code}
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->char('message_id', 26)->nullable()->index(); // FK to sms_messages
            $t->char('campaign_id', 26)->nullable()->index();
            $t->text('target_url');
            $t->unsignedInteger('click_count')->default(0);
            $t->timestamp('last_clicked_at')->nullable();
            $t->timestamps();
            $t->index('team_id');
        });

        Schema::create('short_link_clicks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('short_link_id')->constrained()->cascadeOnDelete();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 300)->nullable();
            $t->string('referrer', 300)->nullable();
            $t->timestamp('clicked_at');
            $t->index(['short_link_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_link_clicks');
        Schema::dropIfExists('short_links');
    }
};
