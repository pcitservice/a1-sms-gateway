<?php

namespace App\Domain\Sms\Services;

use App\Domain\Billing\Exceptions\InsufficientBalance;
use App\Domain\Sms\Jobs\SendSmsJob;
use App\Models\SmsMessage;
use App\Models\Team;
// LinkShortener is autoloaded from the same namespace, no import needed.
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
    public function __construct(
        protected SmsBilling $billing,
        protected LinkShortener $shortener,
    ) {}

    public function dispatch(Team $team, array $payload, ?int $userId = null): SmsMessage
    {
        $to      = $this->normalize($payload['to'], $payload['country_hint'] ?? null);
        $body    = $this->renderBody($payload);
        // Shorten URLs FIRST so segmentation reflects the shorter body.
        $trackLinks = (bool) ($payload['track_links'] ?? false);
        $messageId  = (string) Str::ulid();
        if ($trackLinks) {
            $body = $this->shortener->shorten($body, $team, $messageId, $payload['campaign_id'] ?? null);
        }
        $segments = $this->segmentCount($body);
        $cost     = $this->billing->priceFor($team, $segments);

        if (! $this->billing->canAfford($team, $cost)) {
            throw new InsufficientBalance(sprintf(
                'Sending requires %.2f %s; team balance is insufficient.',
                $cost / 100, config('sms.pricing.currency'),
            ));
        }

        // Future-dated send? Park it as 'scheduled'; the scheduler will
        // flip it to 'queued' and dispatch when send_at <= now().
        $sendAt = ! empty($payload['send_at'])
            ? \Carbon\CarbonImmutable::parse($payload['send_at'])
            : null;
        $scheduled = $sendAt && $sendAt->isFuture();

        $message = SmsMessage::create([
            'id'         => $messageId,
            'team_id'    => $team->id,
            'user_id'    => $userId,
            'batch_id'   => $payload['batch_id'] ?? null,
            'campaign_id'=> $payload['campaign_id'] ?? null,
            'direction'  => 'outbound',
            'from'       => $payload['from'] ?? null,
            'to'         => $to,
            'body'       => $body,
            'segments'   => $segments,
            'status'     => $scheduled ? 'scheduled' : 'queued',
            'metadata'   => $payload['metadata'] ?? [],
            'cost_ore'   => $cost,
            'queued_at'  => $scheduled ? null : now(),
            'send_at'    => $sendAt,
            'gateway_id' => $payload['gateway_id'] ?? null,
        ]);

        $this->billing->record($team, segments: $segments);

        if (! $scheduled) {
            Bus::dispatch(
                (new SendSmsJob($message->id))->onQueue('sms.outbound')
            );
        }

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
