<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $t) {
            // Prepaid pay-as-you-go SMS credits, on top of trial/plan quota.
            // Topped up via Stripe Checkout (see BillingController::checkout
            // and StripeWebhookController::handleCheckoutSessionCompleted).
            $t->integer('sms_credits')->default(0)->after('trial_sms_limit');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $t) {
            $t->dropColumn('sms_credits');
        });
    }
};
