'use client';

import { use, useEffect, useState } from 'react';

type Group = {
  slug: string;
  title: string;
  description: string | null;
  confirmation_message: string;
};

export default function JoinPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const [group,   setGroup]   = useState<Group | null>(null);
  const [state,   setState]   = useState<'loading' | 'ready' | 'not_found'>('loading');
  const [msisdn,  setMsisdn]  = useState('');
  const [name,    setName]    = useState('');
  const [consent, setConsent] = useState(false);
  const [busy,    setBusy]    = useState(false);
  const [done,    setDone]    = useState<string | null>(null);
  const [err,     setErr]     = useState<string | null>(null);

  useEffect(() => {
    fetch(`/api/v1/public/group/${encodeURIComponent(slug)}`)
      .then(r => r.ok ? r.json() : Promise.reject(r))
      .then((g: Group) => { setGroup(g); setState('ready'); })
      .catch(() => setState('not_found'));
  }, [slug]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setErr(null);
    try {
      const [first_name, ...rest] = name.trim().split(/\s+/);
      const res = await fetch(`/api/v1/public/opt-in/${encodeURIComponent(slug)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          msisdn,
          first_name: first_name || undefined,
          last_name:  rest.length ? rest.join(' ') : undefined,
          consent:    true,
        }),
      });
      const body = await res.json();
      if (!res.ok) throw body;
      setDone(body.message ?? group?.confirmation_message ?? 'Thanks — you\'re subscribed!');
    } catch (e: any) {
      const first = e?.errors ? Object.values(e.errors).flat()[0] : null;
      setErr((first as string) ?? e?.message ?? 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  if (state === 'loading') return <Shell><p className="text-slate-500">Loading…</p></Shell>;
  if (state === 'not_found') return (
    <Shell>
      <h1 className="text-2xl font-semibold">Signup unavailable</h1>
      <p className="mt-2 text-sm text-slate-600">This subscription link doesn't exist or is no longer active.</p>
    </Shell>
  );

  if (done) return (
    <Shell>
      <div className="text-5xl">✅</div>
      <h1 className="mt-4 text-2xl font-semibold">You're in</h1>
      <p className="mt-2 text-sm text-slate-600">{done}</p>
    </Shell>
  );

  return (
    <Shell>
      <h1 className="text-2xl font-semibold">{group!.title}</h1>
      {group!.description && (
        <p className="mt-2 whitespace-pre-wrap text-sm text-slate-600">{group!.description}</p>
      )}

      <form onSubmit={onSubmit} className="mt-6 space-y-4">
        <label className="block">
          <span className="text-sm">Phone number</span>
          <input
            type="tel"
            value={msisdn}
            onChange={e => setMsisdn(e.target.value)}
            required
            placeholder="+45 12 34 56 78"
            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
          />
        </label>
        <label className="block">
          <span className="text-sm">Your name (optional)</span>
          <input
            value={name}
            onChange={e => setName(e.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
          />
        </label>

        <label className="flex items-start gap-2 text-xs text-slate-600">
          <input
            type="checkbox"
            checked={consent}
            onChange={e => setConsent(e.target.checked)}
            required
            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
          />
          <span>
            I agree to receive SMS from this sender. I can unsubscribe at any time by replying STOP.
          </span>
        </label>

        {err && <p className="text-sm text-red-600">{err}</p>}

        <button
          type="submit"
          disabled={busy || !consent}
          className="w-full rounded-lg bg-brand-600 px-4 py-3 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        >
          {busy ? 'Subscribing…' : 'Subscribe'}
        </button>
      </form>
    </Shell>
  );
}

function Shell({ children }: { children: React.ReactNode }) {
  return (
    <main className="grid min-h-screen place-items-center bg-slate-50 p-4">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        {children}
        <p className="mt-8 text-center text-xs text-slate-400">
          Powered by A1TechFlow SMS
        </p>
      </div>
    </main>
  );
}
