// Lightweight liveness probe for the Next.js container itself.
export const dynamic = 'force-dynamic';

export function GET() {
  return Response.json({ status: 'ok', time: new Date().toISOString() });
}
