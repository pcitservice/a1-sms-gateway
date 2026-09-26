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

        // Cashier gives us `checkoutCharge()` for one-off, non-subscription
        // purchases. Metadata drives the webhook grant so the amount stays
        // in Stripe's hands and never round-trips through the client.
        $session = $team->checkoutCharge(
            self::BUNDLE['price_cent'],
            self::BUNDLE['label'],
            1,
            [
                'mode'                 => 'payment',
                'currency'             => self::BUNDLE['currency'],
                'customer_email'       => $request->user()->email,
                'success_url'          => config('app.url').'/dashboard/billing?checkout=success',
                'cancel_url'           => config('app.url').'/dashboard/billing?checkout=cancelled',
                'payment_intent_data'  => [
                    'metadata' => [
                        'team_id'    => (string) $team->id,
                        'sms_credits' => (string) self::BUNDLE['sms'],
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
