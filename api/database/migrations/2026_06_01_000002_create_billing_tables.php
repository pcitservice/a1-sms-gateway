<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();          // free, starter, business, pro, enterprise
            $t->string('name');
            $t->unsignedInteger('price_ore')->default(0); // DKK cents
            $t->string('currency', 3)->default('DKK');
            $t->string('interval')->default('month');    // month | year | none
            $t->string('stripe_price_id')->nullable();
            $t->unsignedInteger('sms_included')->nullable(); // null = unlimited
            $t->unsignedInteger('rate_per_minute')->default(30);
            $t->jsonb('features')->default('{}');
            $t->boolean('is_public')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('stripe_id')->unique();
            $t->string('stripe_status');
            $t->string('stripe_price')->nullable();
            $t->integer('quantity')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->index(['team_id', 'stripe_status']);
        });

        Schema::create('subscription_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $t->string('stripe_id')->unique();
            $t->string('stripe_product');
            $t->string('stripe_price');
            $t->integer('quantity')->nullable();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->string('number')->unique();           // a1sms-2026-0001
            $t->string('stripe_invoice_id')->nullable()->unique();
            $t->string('status');                     // draft, open, paid, void
            $t->string('currency', 3)->default('DKK');
            $t->unsignedBigInteger('subtotal_ore');
            $t->unsignedBigInteger('vat_ore');
            $t->unsignedBigInteger('total_ore');
            $t->jsonb('line_items');
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->string('pdf_path')->nullable();
            $t->timestamps();
        });

        Schema::create('usage_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->cascadeOnDelete();
            $t->date('period');
            $t->unsignedInteger('messages_sent')->default(0);
            $t->unsignedInteger('messages_delivered')->default(0);
            $t->unsignedInteger('messages_failed')->default(0);
            $t->unsignedInteger('messages_received')->default(0);
            $t->unsignedInteger('segments_billed')->default(0);
            $t->boolean('reported_to_stripe')->default(false);
            $t->timestamps();
            $t->unique(['team_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
