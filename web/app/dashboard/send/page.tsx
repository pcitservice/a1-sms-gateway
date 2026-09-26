'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { api, getToken } from '@/lib/api';

type Template = { id: number; name: string; body: string };

export default function SendPage() {
  const [to,      setTo]      = useState('');
  const [message, setMessage] = useState('');
  const [tplId,   setTplId]   = useState<string>('');
  const [busy,    setBusy]    = useState(false);
  const [result,  setResult]  = useState<{ ok: boolean; text: string } | null>(null);
  const [paywall, setPaywall] = useState(false);
  const [buying,  setBuying]  = useState(false);

  const { data: templates } = useQuery<Template[] | { data: Template[] }>({
    queryKey: ['templates'],
    queryFn: () => api('/templates', { token: getToken() }),
  });
  const tplList: Template[] = Array.isArray(templates) ? templates : (templates?.data ?? []);

  function applyTemplate(id: string) {
    setTplId(id);
    if (!id) return;
    const t = tplList.find(x => String(x.id) === id);
    if (t) setMessage(t.body);
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setResult(null); setPaywall(false);
    try {
      const res = await api<{ id: string; status: string }>('/send-sms', {
        method: 'POST',
        body: JSON.stringify({ to, message }),
        token: getToken(),
      });
      setResult({ ok: true, text: `Queued (${res.status}) · id ${res.id}` });
      setTo(''); setMessage('');
    } catch (e: any) {
      if (e?.status === 402) {
        // Out of credits — open the top-up flow immediately.
        setPaywall(true);
      } else {
        setResult({ ok: false, text: e.detail ?? e.title ?? e.message ?? 'Send failed' });
      }
    } finally {
      setBusy(false);
    }
  }

  async function buyCredits() {
    setBuying(true);
    try {
      const r = await api<{ url: string }>('/billing/checkout', { method: 'POST', token: getToken() });
      window.location.href = r.url;
    } catch (e: any) {
      setResult({ ok: false, text: e?.detail ?? e?.message ?? 'Could not open checkout.' });
      setBuying(false);
    }
  }

  const segments = countSegments(message);

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-semibold">Send SMS</h1>
      <p className="mt-1 text-sm text-slate-500">A single message; for bulk use Campaigns or the API.</p>

      <Card className="mt-6">
        <form onSubmit={onSubmit} className="space-y-4">
          <label className="block">
            <span className="text-sm">Recipient (E.164)</span>
            <Input value={to} onChange={e => setTo(e.target.value)} placeholder="+4512345678" required />
          </label>
          <label className="block">
            <div className="flex items-center justify-between">
              <span className="text-sm">Template (optional)</span>
              <Link href="/dashboard/templates" className="text-xs text-brand-600 hover:underline">Manage templates →</Link>
            </div>
            <select
              value={tplId}
              onChange={e => applyTemplate(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
            >
              <option value="">— Start from scratch —</option>
              {tplList.map(t => (
                <option key={t.id} value={t.id}>{t.name}</option>
              ))}
            </select>
            {tplList.length === 0 && (
              <p className="mt-1 text-xs text-slate-500">
                No templates yet. <Link href="/dashboard/templates" className="text-brand-600 hover:underline">Create one</Link> to reuse common messages.
              </p>
            )}
          </label>
          <label className="block">
            <span className="text-sm">Message</span>
            <textarea
              value={message}
              onChange={e => setMessage(e.target.value)}
              rows={5}
              required
              maxLength={1530}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
            />
            <span className="mt-1 block text-xs text-slate-500">
              {message.length} chars · {segments} segment{segments === 1 ? '' : 's'}
              {/\{\{\w+\}\}/.test(message) && <> · placeholders will send as-is</>}
            </span>
          </label>
          {result && (
            <p className={`text-sm ${result.ok ? 'text-emerald-600' : 'text-red-600'}`}>{result.text}</p>
          )}
          <Button type="submit" disabled={busy}>{busy ? 'Sending…' : 'Send'}</Button>
        </form>
      </Card>

      {paywall && (
        <div
          className="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4"
          onClick={() => !buying && setPaywall(false)}
        >
          <div
            className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900"
            onClick={e => e.stopPropagation()}
          >
            <div className="text-4xl">💳</div>
            <h2 className="mt-2 text-xl font-semibold">You're out of SMS credits</h2>
            <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
              Your free trial credit is used up. Top up to keep sending — no subscription, no auto-renew.
            </p>

            <div className="mt-5 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
              <div className="flex items-baseline justify-between">
                <div className="text-lg font-semibold">300 SMS credits</div>
                <div className="text-2xl font-bold">99 DKK</div>
              </div>
              <div className="mt-1 text-xs text-slate-500">0.33 DKK per SMS · one-off payment · never expires</div>
            </div>

            <div className="mt-5 flex gap-2">
              <Button onClick={buyCredits} disabled={buying} className="flex-1">
                {buying ? 'Redirecting…' : 'Pay with card'}
              </Button>
              <Button variant="ghost" onClick={() => setPaywall(false)} disabled={buying}>
                Not now
              </Button>
            </div>
            <p className="mt-3 text-center text-xs text-slate-500">Secure payment via Stripe.</p>
          </div>
        </div>
      )}
    </div>
  );
}

function countSegments(body: string) {
  if (!body) return 0;
  const isUnicode = /[^\x00-\x7F]/.test(body);
  const single = isUnicode ? 70 : 160;
  const multi  = isUnicode ? 67 : 153;
  return body.length <= single ? 1 : Math.ceil(body.length / multi);
}
