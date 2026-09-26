<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_topups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('stripe_session_id')->unique(); // idempotency key
            $t->integer('sms_granted');
            $t->integer('amount_cent')->nullable();
            $t->string('currency', 3)->nullable();
            $t->timestamp('granted_at');
            $t->timestamps();
            $t->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_topups');
    }
};
