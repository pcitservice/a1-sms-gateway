<?php

namespace App\Domain\Sms\Services;

use App\Models\ShortLink;
use App\Models\Team;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Rewrites http(s) URLs in outbound SMS bodies to trackable short links.
 *
 *   Input : "Hi Umer, book at https://example.com/very/long/path?utm=x"
 *   Output: "Hi Umer, book at https://sms.a1techflow.com/l/aB3xQ9"
 *
 * Every match creates a short_links row scoped to the workspace and, when
 * known, the message id. The /l/{code} redirect (ShortLinkController) then
 * counts clicks and forwards to the original URL.
 */
class LinkShortener
{
    // Base62 for URL-safe short codes — 6 chars gives ~57 billion codes,
    // ample for retry-on-collision on the unique index.
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function shorten(string $body, Team $team, ?string $messageId = null, ?string $campaignId = null): string
    {
        return preg_replace_callback(
            '~\bhttps?://\S+~i',
            function ($m) use ($team, $messageId, $campaignId) {
                $url = rtrim($m[0], ".,;:)]!?");
                $tail = substr($m[0], strlen($url));

                // Don't re-shorten our own short links; that would double-count.
                if (str_contains($url, '/l/') && str_starts_with($url, (string) config('app.url'))) {
                    return $m[0];
                }

                $link = $this->create($team, $url, $messageId, $campaignId);
                return rtrim(config('app.url'), '/') . '/l/' . $link->code . $tail;
            },
            $body,
        ) ?? $body;
    }

    public function create(Team $team, string $url, ?string $messageId = null, ?string $campaignId = null): ShortLink
    {
        // Retry-on-collision — the unique index on `code` is authoritative.
        for ($i = 0; $i < 5; $i++) {
            try {
                return ShortLink::query()->create([
                    'code'       => $this->randomCode(),
                    'team_id'    => $team->id,
                    'message_id' => $messageId,
                    'campaign_id'=> $campaignId,
                    'target_url' => $url,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($i === 4) throw $e;
                // Collision — try again with a fresh code.
            }
        }
        throw new \RuntimeException('Could not allocate a short link code.');
    }

    private function randomCode(int $len = 6): string
    {
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return $out;
    }
}
