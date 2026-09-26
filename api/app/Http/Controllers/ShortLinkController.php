<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use Illuminate\Http\Request;

/**
 * Public redirect for /l/{code}. Counts the click, records a row for
 * analytics, then 302s to the target URL. Deliberately NOT under
 * /api/v1 — this URL sits inside outgoing SMS bodies and must be as
 * short as possible.
 */
class ShortLinkController extends Controller
{
    public function redirect(Request $request, string $code)
    {
        $link = $this->recordClick($request, $code);
        if (! $link) return response('Link not found', 404);
        return redirect()->away($link->target_url, 302);
    }

    /**
     * Called by the Next.js /l/{code} route handler. Returns the target so
     * Next can issue the 302, while we persist the click server-side.
     */
    public function clickAndResolve(Request $request, string $code)
    {
        $link = $this->recordClick($request, $code);
        if (! $link) return response()->json(['error' => 'not_found'], 404);
        return response()->json(['url' => $link->target_url]);
    }

    private function recordClick(Request $request, string $code): ?ShortLink
    {
        $link = ShortLink::query()->withoutGlobalScopes()->where('code', $code)->first();
        if (! $link) return null;

        try {
            ShortLinkClick::create([
                'short_link_id' => $link->id,
                'ip'            => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 300),
                'referrer'      => substr((string) $request->headers->get('referer'), 0, 300),
                'clicked_at'    => now(),
            ]);
            $link->increment('click_count');
            $link->forceFill(['last_clicked_at' => now()])->save();
        } catch (\Throwable) {
            // Analytics is best-effort; don't 500 the redirect.
        }
        return $link;
    }
}
