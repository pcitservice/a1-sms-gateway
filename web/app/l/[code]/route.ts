import { NextRequest, NextResponse } from 'next/server';

/**
 * Public short-link resolver. Hit by SMS recipients:
 *   https://sms.a1techflow.com/l/aB3xQ9
 *
 * We forward the click info to Laravel (server-side, so the target URL never
 * touches the client) and 302 to the returned target.
 */
export const dynamic = 'force-dynamic';

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ code: string }> },
) {
  const { code } = await params;

  const forwarded = req.headers.get('x-forwarded-for') ?? '';
  const ip        = forwarded.split(',')[0].trim() || req.headers.get('x-real-ip') || '';

  try {
    const upstream = await fetch(`http://api:8000/api/v1/short-links/${encodeURIComponent(code)}/click`, {
      method: 'POST',
      headers: {
        'Content-Type':      'application/json',
        'Accept':            'application/json',
        'X-Forwarded-For':   ip,
        'User-Agent':        req.headers.get('user-agent') ?? '',
        'Referer':           req.headers.get('referer')    ?? '',
      },
      // Body is empty; the header set carries all the click metadata.
      body: '{}',
      cache: 'no-store',
    });
    if (upstream.status === 404) {
      return new NextResponse('Link not found', { status: 404 });
    }
    const body = await upstream.json();
    if (!body.url) {
      return new NextResponse('Link error', { status: 500 });
    }
    return NextResponse.redirect(body.url, { status: 302 });
  } catch {
    return new NextResponse('Redirect failed', { status: 502 });
  }
}
