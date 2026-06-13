'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { api, getToken } from '@/lib/api';

export default function SendPage() {
  const [to,      setTo]      = useState('');
  const [message, setMessage] = useState('');
  const [busy,    setBusy]    = useState(false);
  const [result,  setResult]  = useState<{ ok: boolean; text: string } | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setResult(null);
    try {
      const res = await api<{ id: string; status: string }>('/send-sms', {
        method: 'POST',
        body: JSON.stringify({ to, message }),
        token: getToken(),
      });
      setResult({ ok: true, text: `Queued (${res.status}) · id ${res.id}` });
      setTo(''); setMessage('');
    } catch (e: any) {
      setResult({ ok: false, text: e.detail ?? e.title ?? 'Send failed' });
    } finally {
      setBusy(false);
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
            <span className="text-sm">Message</span>
            <textarea
              value={message}
              onChange={e => setMessage(e.target.value)}
              rows={5}
              required
              maxLength={1530}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
            />
            <span className="mt-1 block text-xs text-slate-500">{message.length} chars · {segments} segment{segments === 1 ? '' : 's'}</span>
          </label>
          {result && (
            <p className={`text-sm ${result.ok ? 'text-emerald-600' : 'text-red-600'}`}>{result.text}</p>
          )}
          <Button type="submit" disabled={busy}>{busy ? 'Sending…' : 'Send'}</Button>
        </form>
      </Card>
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
