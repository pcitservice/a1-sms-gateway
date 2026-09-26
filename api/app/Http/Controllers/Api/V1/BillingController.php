<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

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
        'sms'        => 300,
        'price_cent' => 9900,  // 99.00 DKK
        'currency'   => 'dkk',
        'label'      => '300 SMS credits',
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
                'success_url'         => config('app.url').'/dashboard/billing?checkout=success&session_id={CHECKOUT_SESSION_ID}',
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

    /**
     * Frontend polls this on return from Stripe. It fetches the session from
     * Stripe, verifies it was paid and belongs to the caller's team, and
     * grants credits idempotently (unique index on stripe_session_id).
     *
     * Belt-and-braces companion to the Stripe webhook — this route keeps
     * top-ups working even when the webhook endpoint isn't wired yet.
     */
    public function verifyCheckout(Request $request)
    {
        $data = $request->validate(['session_id' => 'required|string|starts_with:cs_']);
        $team = app('current_team');

        $stripe  = new StripeClient(config('cashier.secret'));
        $session = $stripe->checkout->sessions->retrieve($data['session_id'], []);

        if (($session->payment_status ?? '') !== 'paid') {
            return response()->json([
                'granted' => false,
                'reason'  => 'not_paid',
                'status'  => $session->payment_status ?? 'unknown',
            ], 202);
        }

        $meta   = (array) ($session->metadata ?? []);
        $teamId = (int) ($meta['team_id']    ?? 0);
        $grant  = (int) ($meta['sms_credits'] ?? 0);
        if ($teamId !== (int) $team->id || $grant <= 0 || ($meta['kind'] ?? null) !== 'sms_credits_topup') {
            return response()->json(['granted' => false, 'reason' => 'metadata_mismatch'], 422);
        }

        // Insert-or-noop on the receipt row. Concurrent verifies (webhook +
        // frontend poll) both hit the unique index; only one wins the grant.
        $inserted = DB::table('credit_topups')->insertOrIgnore([
            'team_id'           => $team->id,
            'stripe_session_id' => $session->id,
            'sms_granted'       => $grant,
            'amount_cent'       => $session->amount_total ?? null,
            'currency'          => $session->currency     ?? null,
            'granted_at'        => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        if ($inserted) {
            $team->increment('sms_credits', $grant);
            Log::info('sms_credits topped up via verify-checkout', [
                'team_id'    => $team->id,
                'granted'    => $grant,
                'session_id' => $session->id,
            ]);
        }

        return response()->json([
            'granted'      => true,
            'newly_added'  => (bool) $inserted,
            'sms_granted'  => $grant,
            'credits_now'  => (int) $team->fresh()->sms_credits,
        ]);
    }
}
