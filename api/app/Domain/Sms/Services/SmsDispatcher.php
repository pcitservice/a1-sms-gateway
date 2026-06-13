<?php

namespace App\Domain\Sms\Services;

use App\Domain\Billing\Exceptions\InsufficientBalance;
use App\Domain\Sms\Jobs\SendSmsJob;
use App\Models\SmsMessage;
use App\Models\Team;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * The single entry-point for the rest of the app to send an SMS. Handles
 * validation, segmentation, billing-quota check, persistence, and job
 * dispatch on the `sms.outbound` queue.
 */
class SmsDispatcher
{
    public function __construct(protected SmsBilling $billing) {}

    public function dispatch(Team $team, array $payload, ?int $userId = null): SmsMessage
    {
        $to    = $this->normalize($payload['to'], $payload['country_hint'] ?? null);
        $body  = $this->renderBody($payload);
        $segments = $this->segmentCount($body);
        $cost  = $this->billing->priceFor($team, $segments);

        if (! $this->billing->canAfford($team, $cost)) {
            throw new InsufficientBalance(sprintf(
                'Sending requires %.2f %s; team balance is insufficient.',
                $cost / 100, config('sms.pricing.currency'),
            ));
        }

        $message = SmsMessage::create([
            'id'         => (string) Str::ulid(),
            'team_id'    => $team->id,
            'user_id'    => $userId,
            'batch_id'   => $payload['batch_id'] ?? null,
            'campaign_id'=> $payload['campaign_id'] ?? null,
            'direction'  => 'outbound',
            'from'       => $payload['from'] ?? null,
            'to'         => $to,
            'body'       => $body,
            'segments'   => $segments,
            'status'     => 'queued',
            'metadata'   => $payload['metadata'] ?? [],
            'cost_ore'   => $cost,
            'queued_at'  => now(),
            'gateway_id' => $payload['gateway_id'] ?? null,
        ]);

        $this->billing->record($team, segments: $segments);

        Bus::dispatch(
            (new SendSmsJob($message->id))->onQueue('sms.outbound')
        );

        return $message;
    }

    public function normalize(string $msisdn, ?string $countryHint = null): string
    {
        try {
            return (string) (new PhoneNumber($msisdn, $countryHint ? [$countryHint] : null))
                ->formatE164();
        } catch (\Throwable) {
            // Don't fail-hard on E164; assume the gateway will reject if invalid.
            return $msisdn;
        }
    }

    public function renderBody(array $payload): string
    {
        $body = $payload['message'] ?? $payload['body'] ?? '';
        foreach ($payload['variables'] ?? [] as $k => $v) {
            $body = str_replace('{{'.$k.'}}', (string) $v, $body);
        }
        return $body;
    }

    public function segmentCount(string $body): int
    {
        $isUnicode = preg_match('//u', $body) && (mb_strlen($body) !== strlen($body));
        $singleCap = $isUnicode ? 70 : 160;
        $multiCap  = $isUnicode ? 67 : 153;
        $len = mb_strlen($body);
        return $len <= $singleCap ? 1 : (int) ceil($len / $multiCap);
    }
}
