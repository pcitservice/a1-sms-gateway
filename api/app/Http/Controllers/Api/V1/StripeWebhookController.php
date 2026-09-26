<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\InvoiceGenerator;
use App\Models\Team;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function handleInvoicePaid(array $payload)
    {
        $stripeInvoiceId = $payload['data']['object']['id'] ?? null;
        $stripeCustomer  = $payload['data']['object']['customer'] ?? null;
        if (! $stripeInvoiceId || ! $stripeCustomer) {
            return $this->successMethod();
        }
        $team = Team::query()->where('stripe_id', $stripeCustomer)->first();
        if ($team) {
            app(InvoiceGenerator::class)->fromStripe($team, $payload['data']['object']);
        }
        return $this->successMethod();
    }

    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $stripeCustomer = $payload['data']['object']['customer'] ?? null;
        $team = Team::query()->where('stripe_id', $stripeCustomer)->first();
        if ($team) {
            $team->update(['plan_id' => \App\Models\Plan::where('slug', 'free')->value('id')]);
        }
        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * One-off SMS credit top-ups from BillingController::checkout land here.
     * We trust ONLY Stripe's own metadata + amount — the client never gets
     * to influence how many credits are granted.
     */
    public function handleCheckoutSessionCompleted(array $payload)
    {
        $object = $payload['data']['object'] ?? [];
        if (($object['payment_status'] ?? '') !== 'paid') {
            return $this->successMethod();
        }

        $meta = $object['metadata'] ?? [];
        if (($meta['kind'] ?? null) !== 'sms_credits_topup') {
            return $this->successMethod();
        }

        $teamId = (int) ($meta['team_id']     ?? 0);
        $grant  = (int) ($meta['sms_credits'] ?? 0);
        if (! $teamId || $grant <= 0) {
            return $this->successMethod();
        }

        $team = Team::query()->find($teamId);
        if ($team) {
            $team->increment('sms_credits', $grant);
            \Log::info('sms_credits topped up', [
                'team_id'    => $teamId,
                'granted'    => $grant,
                'session_id' => $object['id'] ?? null,
                'amount'     => $object['amount_total'] ?? null,
                'currency'   => $object['currency']     ?? null,
            ]);
        }
        return $this->successMethod();
    }
}
