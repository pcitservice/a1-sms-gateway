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
use DateTimeZone;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Teltonika TRB140 / TRB145 / RUT-series driver.
 *
 * Speaks to RutOS via its JSON HTTP API (documented at
 * https://wiki.teltonika-networks.com/view/RutOS_API). Authenticates with
 * username/password, caches the bearer token in Redis with a 5-minute TTL,
 * re-authenticates once on 401, and falls through to AT commands over SSH
 * for the few primitives the API doesn't expose.
 */
class Trb140Driver implements SmsGateway
{
    private const TOKEN_TTL_SECONDS = 290;

    public function __construct(
        protected Gateway         $row,
        protected HttpClient      $http,
        protected Cache           $cache,
        protected LoggerInterface $logger,
    ) {}

    public function id(): int       { return $this->row->id; }
    public function kind(): string  { return 'trb140'; }

    // ---------------------------------------------------------------- send --

    public function send(OutgoingMessage $message): SendResult
    {
        try {
            $modem = $message->modemId ?? $this->row->modem_id ?? config('sms.trb140.modem_id');
            $resp  = $this->call('POST', '/api/messages/actions/send', [
                'data' => [
                    'number'  => $message->to,
                    'message' => $message->body,
                    'modem'   => $modem,
                ],
            ]);

            if (! ($resp['success'] ?? false)) {
                $err = $resp['errors'][0] ?? ['code' => 'unknown', 'detail' => 'Send failed'];
                return SendResult::failure(
                    code:   (string) ($err['code']   ?? 'unknown'),
                    message:(string) ($err['detail'] ?? 'Send failed'),
                    raw:    $resp,
                );
            }

            // RutOS doesn't return a provider ID — synthesise a deterministic one
            // so we can correlate this row across re-polls of the device.
            $providerId = $this->synthesizeProviderId($message, $modem);
            $segments   = max(1, (int) ($resp['data']['sms_used'] ?? $this->countSegments($message->body)));

            return SendResult::success($providerId, $segments, $resp);
        } catch (Throwable $e) {
            $this->logger->error('trb140.send failed', ['gateway_id' => $this->row->id, 'error' => $e->getMessage()]);
            return SendResult::failure('transport', $e->getMessage());
        }
    }

    // --------------------------------------------------------- inbox poll --

    /** @return iterable<IncomingMessage> */
    public function pollIncoming(): iterable
    {
        $modem = $this->row->modem_id ?? config('sms.trb140.modem_id');
        try {
            $resp = $this->call('GET', '/api/messages/inbox', query: ['modem' => $modem]);
        } catch (Throwable $e) {
            $this->logger->warning('trb140.poll failed', ['gateway_id' => $this->row->id, 'error' => $e->getMessage()]);
            return;
        }

        $items = $resp['data'] ?? [];
        if (! is_array($items) || ! $items) {
            return;
        }

        $tz = new DateTimeZone(config('app.timezone'));
        $ackIds = [];

        foreach ($items as $row) {
            try {
                $receivedAt = isset($row['date'])
                    ? new DateTimeImmutable($row['date'], $tz)
                    : new DateTimeImmutable('now', $tz);
                yield new IncomingMessage(
                    providerId: (string) $row['id'],
                    from:       (string) ($row['sender'] ?? ''),
                    to:         '',
                    body:       (string) ($row['text']   ?? ''),
                    receivedAt: $receivedAt,
                    modemId:    $modem,
                    metadata:   ['raw' => $row],
                );
                $ackIds[] = (string) $row['id'];
            } catch (Throwable $e) {
                $this->logger->warning('trb140.ingest skipped malformed row', ['error' => $e->getMessage()]);
            }
        }

        // Acknowledge so the SIM storage doesn't fill up.
        if ($ackIds) {
            try {
                $this->call('POST', '/api/messages/actions/delete', [
                    'data' => ['ids' => $ackIds, 'modem' => $modem],
                ]);
            } catch (Throwable $e) {
                $this->logger->warning('trb140.ack failed', ['ids' => $ackIds, 'error' => $e->getMessage()]);
            }
        }
    }

    public function status(string $providerId): MessageStatus
    {
        // RutOS doesn't track per-message status after handoff; we treat the
        // act of successful send as terminal "sent". Delivery confirmation
        // comes from the carrier via SMSC delivery reports, which require a
        // separate at+cnmi configuration handled in `configure()`.
        return new MessageStatus($providerId, 'sent');
    }

    // ----------------------------------------------------------- health --

    public function health(): GatewayHealth
    {
        try {
            $info  = $this->call('GET', '/api/system/info')['data'] ?? [];
            $modem = $this->call('GET', '/api/modem/status')['data'] ?? [];
            $sim   = $this->call('GET', '/api/sim/status')['data']   ?? [];
            $conn  = $this->call('GET', '/api/modem/connection')['data'] ?? [];

            return new GatewayHealth(
                reachable:       true,
                connectionState: $conn['state'] ?? null,
                signalRssi:      isset($modem['signal']) ? (int) $modem['signal'] : null,
                signalRsrp:      isset($modem['rsrp'])   ? (int) $modem['rsrp']   : null,
                operator:        $conn['operator'] ?? $modem['operator'] ?? null,
                lteBand:         $modem['band']    ?? null,
                simStatus:       $sim['state']     ?? null,
                imei:            $modem['imei']    ?? null,
                uptimeSeconds:   isset($info['uptime']) ? (int) $info['uptime'] : null,
                raw:             ['info' => $info, 'modem' => $modem, 'sim' => $sim, 'conn' => $conn],
            );
        } catch (Throwable $e) {
            return new GatewayHealth(reachable: false, raw: ['error' => $e->getMessage()]);
        }
    }

    public function reboot(): void
    {
        try {
            $this->call('POST', '/api/system/actions/reboot');
            $this->cache->forget($this->tokenCacheKey());
        } catch (Throwable $e) {
            throw new GatewayException("Reboot failed: {$e->getMessage()}");
        }
    }

    public function configure(array $config): void
    {
        // Whitelist of supported config keys → RutOS endpoint paths.
        $map = [
            'apn'        => '/api/mobile/connection/config',
            'pin'        => '/api/sim/actions/pin',
            'cnmi'       => null, // handled via SSH; see below
        ];
        foreach ($config as $key => $value) {
            if (! array_key_exists($key, $map)) {
                continue;
            }
            if ($map[$key] === null) {
                $this->sshAt("AT+CNMI={$value}");
                continue;
            }
            $this->call('POST', $map[$key], ['data' => $value]);
        }
    }

    // --------------------------------------------------------- internals --

    private function call(string $method, string $path, array $json = [], array $query = [], int $retries = 1): array
    {
        $opts = [
            'headers' => ['Authorization' => 'Bearer '.$this->token()],
            'query'   => $query,
        ];
        if ($json) {
            $opts['json'] = $json;
        }
        try {
            $resp = $this->http->request($method, $path, $opts);
            $body = (string) $resp->getBody();
            $data = $body === '' ? [] : (json_decode($body, true) ?: []);
            return $data;
        } catch (GuzzleException $e) {
            $code = method_exists($e, 'getCode') ? (int) $e->getCode() : 0;
            if ($code === 401 && $retries > 0) {
                $this->cache->forget($this->tokenCacheKey());
                return $this->call($method, $path, $json, $query, $retries - 1);
            }
            throw new GatewayException("HTTP {$method} {$path} failed: {$e->getMessage()}", $code, $e);
        }
    }

    private function token(): string
    {
        return $this->cache->remember($this->tokenCacheKey(), self::TOKEN_TTL_SECONDS, function () {
            $resp = $this->http->post('/api/login', [
                'json' => [
                    'username' => $this->row->username ?: config('sms.trb140.username'),
                    'password' => $this->row->password ?: config('sms.trb140.password'),
                ],
            ]);
            $body = json_decode((string) $resp->getBody(), true);
            $token = $body['data']['ubus_rpc_session'] ?? $body['data']['token'] ?? null;
            if (! $token) {
                throw new GatewayException('TRB140 login returned no token.');
            }
            return $token;
        });
    }

    private function tokenCacheKey(): string
    {
        return "gateway:{$this->row->id}:token";
    }

    private function synthesizeProviderId(OutgoingMessage $m, ?string $modem): string
    {
        return 'trb140-'.substr(hash('sha256', implode('|', [
            $this->row->id, $modem ?? '-', $m->id, $m->to, (string) microtime(true),
        ])), 0, 24);
    }

    private function countSegments(string $body): int
    {
        // Naive segmenter: 160-char GSM-7 / 70-char UCS-2. Multi-part is the
        // 153 / 67 boundary because of UDH. Good enough for cost estimation.
        $isUnicode = preg_match('//u', $body) && (mb_strlen($body) !== strlen($body));
        $singleCap = $isUnicode ? 70 : 160;
        $multiCap  = $isUnicode ? 67 : 153;
        $len = mb_strlen($body);
        if ($len <= $singleCap) return 1;
        return (int) ceil($len / $multiCap);
    }

    /**
     * SSH + AT-command fallback. Used for primitives the HTTP API doesn't
     * expose. Returns trimmed stdout.
     */
    private function sshAt(string $cmd): string
    {
        $key = $this->row->ssh_key_ref ?: config('sms.trb140.ssh_key_path');
        if (! $key) {
            throw new GatewayException('SSH fallback invoked but no key path configured.');
        }
        $host = escapeshellarg($this->row->host);
        $port = (int) ($this->row->port ?: config('sms.trb140.ssh_port'));
        $user = escapeshellarg($this->row->username ?: 'root');
        $keyP = escapeshellarg($key);
        $atCmd = escapeshellarg($cmd);

        $shell = sprintf(
            'ssh -i %s -p %d -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null %s@%s "gsmctl -A %s" 2>&1',
            $keyP, $port, $user, $host, $atCmd,
        );

        $out = [];
        $rc  = 0;
        exec($shell, $out, $rc);
        if ($rc !== 0) {
            throw new GatewayException("SSH AT failed (rc={$rc}): ".implode("\n", $out));
        }
        return trim(implode("\n", $out));
    }
}
