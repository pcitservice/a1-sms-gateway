<?php

namespace App\Domain\Gateway\Drivers;

use App\Domain\Gateway\Contracts\SmsGateway;
use App\Domain\Gateway\DTO\GatewayHealth;
use App\Domain\Gateway\DTO\IncomingMessage;
use App\Domain\Gateway\DTO\MessageStatus;
use App\Domain\Gateway\DTO\OutgoingMessage;
use App\Domain\Gateway\DTO\SendResult;
use App\Domain\Gateway\Exceptions\GatewayException;
use App\Models\Gateway;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

/**
 * Huawei HiLink stub — covers the E3372 / B525 family.
 *
 * HiLink speaks XML at /api/sms/send-sms, /api/sms/sms-list, etc., with
 * a CSRF-style session token in cookies. We implement the surface enough
 * that the abstraction is exercised, but production users should validate
 * against their specific model — Huawei firmware varies wildly.
 */
class HuaweiDriver implements SmsGateway
{
    public function __construct(
        protected Gateway         $row,
        protected LoggerInterface $logger,
    ) {}

    public function id(): int      { return $this->row->id; }
    public function kind(): string { return 'huawei'; }

    public function send(OutgoingMessage $message): SendResult
    {
        $xml = sprintf(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<request>".
            "<Index>-1</Index><Phones><Phone>%s</Phone></Phones>".
            "<Sca/><Content>%s</Content><Length>%d</Length>".
            "<Reserved>1</Reserved><Date>%s</Date></request>",
            htmlspecialchars($message->to, ENT_XML1),
            htmlspecialchars($message->body, ENT_XML1),
            mb_strlen($message->body),
            (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        );

        $ch = curl_init(sprintf('%s://%s:%d/api/sms/send-sms',
            $this->row->protocol, $this->row->host, $this->row->port));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/xml'],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return SendResult::failure('transport', $err ?: 'unknown curl error');
        }
        try {
            $doc = new SimpleXMLElement($body);
            if ((string) $doc === 'OK') {
                return SendResult::success(
                    providerId: 'huawei-'.bin2hex(random_bytes(8)),
                );
            }
            return SendResult::failure(
                code:   (string) ($doc->code ?? 'unknown'),
                message:(string) ($doc->message ?? 'Huawei rejected the message'),
            );
        } catch (\Throwable $e) {
            return SendResult::failure('parse', $e->getMessage());
        }
    }

    public function pollIncoming(): iterable
    {
        // Implementation parses /api/sms/sms-list with a paginated XML request.
        // Kept minimal here so the contract is honoured; production rollouts
        // should validate against the firmware on the actual device.
        $this->logger->info('huawei.poll noop (stub)', ['gateway_id' => $this->row->id]);
        return [];
    }

    public function status(string $providerId): MessageStatus
    {
        return new MessageStatus($providerId, 'sent');
    }

    public function health(): GatewayHealth
    {
        $ch = curl_init(sprintf('%s://%s:%d/api/device/signal',
            $this->row->protocol, $this->row->host, $this->row->port));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $body = curl_exec($ch);
        $rc   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rc !== 200 || $body === false) {
            return new GatewayHealth(reachable: false);
        }
        try {
            $doc = new SimpleXMLElement($body);
            return new GatewayHealth(
                reachable:  true,
                signalRssi: isset($doc->rssi) ? (int) $doc->rssi : null,
                signalRsrp: isset($doc->rsrp) ? (int) $doc->rsrp : null,
                lteBand:    (string) ($doc->band ?? '') ?: null,
                raw:        ['signal' => (array) $doc],
            );
        } catch (\Throwable) {
            return new GatewayHealth(reachable: true);
        }
    }

    public function reboot(): void
    {
        throw new GatewayException('Huawei reboot via HiLink is firmware-specific; configure manually.');
    }

    public function configure(array $config): void
    {
        $this->logger->info('huawei.configure (stub)', ['gateway_id' => $this->row->id]);
    }
}
