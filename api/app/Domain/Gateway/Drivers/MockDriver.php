<?php

namespace App\Domain\Gateway\Drivers;

use App\Domain\Gateway\Contracts\SmsGateway;
use App\Domain\Gateway\DTO\GatewayHealth;
use App\Domain\Gateway\DTO\IncomingMessage;
use App\Domain\Gateway\DTO\MessageStatus;
use App\Domain\Gateway\DTO\OutgoingMessage;
use App\Domain\Gateway\DTO\SendResult;
use App\Models\Gateway;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

/**
 * In-memory mock for local development, CI, and demo seeding. Persists state
 * to the shared cache so multiple workers see consistent results.
 */
class MockDriver implements SmsGateway
{
    public function __construct(
        protected Gateway         $row,
        protected Cache           $cache,
        protected LoggerInterface $logger,
    ) {}

    public function id(): int      { return $this->row->id; }
    public function kind(): string { return 'mock'; }

    public function send(OutgoingMessage $message): SendResult
    {
        $providerId = 'mock-'.bin2hex(random_bytes(8));
        $this->cache->put("mock:msg:{$providerId}", 'sent', 3600);
        $this->logger->info('mock.send', ['to' => $message->to, 'body' => $message->body]);

        // 5% simulated failure so the retry path gets exercised in dev.
        if (random_int(1, 100) <= 5) {
            return SendResult::failure('mock_random_failure', 'Simulated mock failure');
        }
        return SendResult::success($providerId, 1, ['mock' => true]);
    }

    public function pollIncoming(): iterable
    {
        $key = "mock:gateway:{$this->row->id}:inbox";
        $queue = $this->cache->get($key, []);
        if (! is_array($queue) || ! $queue) {
            return;
        }
        $this->cache->put($key, [], 3600);
        foreach ($queue as $item) {
            yield new IncomingMessage(
                providerId: (string) $item['id'],
                from:       (string) $item['from'],
                to:         (string) ($item['to'] ?? ''),
                body:       (string) $item['body'],
                receivedAt: new DateTimeImmutable($item['received_at']),
                modemId:    'mock-1',
            );
        }
    }

    public function status(string $providerId): MessageStatus
    {
        $state = $this->cache->get("mock:msg:{$providerId}", 'unknown');
        return new MessageStatus($providerId, $state);
    }

    public function health(): GatewayHealth
    {
        return new GatewayHealth(
            reachable:       true,
            connectionState: 'connected',
            signalRssi:      -65,
            signalRsrp:      -90,
            operator:        'Mock-Carrier',
            lteBand:         'B3',
            simStatus:       'ready',
            imei:            '000000000000000',
            uptimeSeconds:   3600,
        );
    }

    public function reboot(): void
    {
        $this->logger->info('mock.reboot', ['gateway_id' => $this->row->id]);
    }

    public function configure(array $config): void
    {
        $this->logger->info('mock.configure', ['gateway_id' => $this->row->id, 'config' => $config]);
    }

    /** Helper for tests/seeders: inject a fake inbound message. */
    public function injectIncoming(string $from, string $body): void
    {
        $key = "mock:gateway:{$this->row->id}:inbox";
        $queue = $this->cache->get($key, []);
        $queue[] = [
            'id'          => bin2hex(random_bytes(6)),
            'from'        => $from,
            'to'          => '+0000000000',
            'body'        => $body,
            'received_at' => (new DateTimeImmutable)->format(DATE_ATOM),
        ];
        $this->cache->put($key, $queue, 3600);
    }
}
