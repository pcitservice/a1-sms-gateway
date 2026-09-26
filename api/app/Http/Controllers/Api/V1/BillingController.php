<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Pay-as-you-go SMS credit purchases.
 *
 * The single top-up bundle right now is 100 SMS for €10.00. Sold via a
 * Stripe Checkout session. The `sms_credits` counter on the team is
 * incremented by the webhook handler once Stripe confirms the payment
 * (see StripeWebhookController::handleCheckoutSessionCompleted).
 */
class BillingController extends Controller
{
    // Keeping the offer inline so a swap to plan-based pricing is one edit.
    private const BUNDLE = [
        'sms'        => 100,
        'price_cent' => 1000, // €10.00
        'currency'   => 'eur',
        'label'      => '100 SMS credits',
    ];

    public function summary(Request $request)
    {
        $team = app('current_team');
        return response()->json([
            'trial'   => [
                'in_trial'  => $team->inTrial(),
                'used'      => (int) $team->trial_sms_used,
                'limit'     => (int) $team->trial_sms_limit,
                'remaining' => $team->inTrial() ? $team->trialRemaining() : 0,
                'ends_at'   => $team->trial_ends_at,
            ],
            'credits' => (int) ($team->sms_credits ?? 0),
            'offer'   => self::BUNDLE,
        ]);
    }

    public function checkout(Request $request)
    {
        $team = app('current_team');

        // Explicit line_items lets us bill in EUR regardless of the workspace-
        // wide CASHIER_CURRENCY. Cashier's checkoutCharge() would generate a
        // DKK Price and mismatch our EUR session.
        $session = $team->checkout(
            [
                [
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => self::BUNDLE['currency'],
                        'unit_amount'  => self::BUNDLE['price_cent'],
                        'product_data' => [
                            'name'        => self::BUNDLE['label'],
                            'description' => sprintf('%d prepaid SMS credits for %s.', self::BUNDLE['sms'], config('app.name')),
                        ],
                    ],
                ],
            ],
            [
                'mode'                => 'payment',
                'success_url'         => config('app.url').'/dashboard/billing?checkout=success',
                'cancel_url'          => config('app.url').'/dashboard/billing?checkout=cancelled',
                // Automatic tax is on for the Stripe account, so Checkout has
                // to be told to collect + persist a billing address on the
                // Stripe customer for tax calculation to work.
                'billing_address_collection' => 'required',
                'customer_update'           => ['address' => 'auto', 'name' => 'auto'],
                'payment_intent_data' => [
                    'metadata' => [
                        'team_id'     => (string) $team->id,
                        'sms_credits' => (string) self::BUNDLE['sms'],
                        'kind'        => 'sms_credits_topup',
                    ],
                ],
                'metadata' => [
                    'team_id'     => (string) $team->id,
                    'sms_credits' => (string) self::BUNDLE['sms'],
                    'kind'        => 'sms_credits_topup',
                ],
            ],
        );

        return response()->json([
            'url' => $session->url,
            'id'  => $session->id,
        ]);
    }
}
