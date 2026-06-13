'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Webhook = { id: number; url: string; events: string[]; is_active: boolean; failure_count: number };

const EVENT_TYPES = [
  'message.queued', 'message.sent', 'message.delivered', 'message.failed',
  'message.received', 'gateway.online', 'gateway.offline', 'balance.low',
];

export default function WebhooksPage() {
  const qc = useQueryClient();
  const [url, setUrl] = useState('');
  const [events, setEvents] = useState<string[]>(['message.delivered']);
  const [secretReveal, setSecretReveal] = useState<string | null>(null);

  const { data, isLoading } = useQuery<Webhook[]>({
    queryKey: ['webhooks'],
    queryFn: () => api('/webhooks', { token: getToken() }),
  });

  const create = useMutation({
    mutationFn: () =>
      api<{ secret: string }>('/webhooks', {
        method: 'POST',
        body: JSON.stringify({ url, events }),
        token: getToken(),
      }),
    onSuccess: r => { setSecretReveal(r.secret); setUrl(''); qc.invalidateQueries({ queryKey: ['webhooks'] }); },
  });

  const remove = useMutation({
    mutationFn: (id: number) => api(`/webhooks/${id}`, { method: 'DELETE', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['webhooks'] }),
  });

  return (
    <div className="max-w-3xl">
      <h1 className="text-2xl font-semibold">Webhooks</h1>
      <p className="mt-1 text-sm text-slate-500">
        Receive HMAC-signed event POSTs whenever messages change state.
        Verify the <code className="font-mono">X-A1Sms-Signature</code> header on your end.
      </p>

      <Card className="mt-6">
        <form onSubmit={e => { e.preventDefault(); create.mutate(); }} className="space-y-3">
          <label className="block">
            <span className="text-sm">Endpoint URL</span>
            <Input type="url" value={url} onChange={e => setUrl(e.target.value)} placeholder="https://example.com/webhooks/a1sms" required />
          </label>
          <div>
            <span className="text-sm">Events</span>
            <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
              {EVENT_TYPES.map(ev => (
                <label key={ev} className="flex items-center gap-2 text-xs">
                  <input
                    type="checkbox"
                    checked={events.includes(ev)}
                    onChange={e =>
                      setEvents(e.target.checked ? [...events, ev] : events.filter(x => x !== ev))
                    }
                  />
                  <span>{ev}</span>
                </label>
              ))}
            </div>
          </div>
          <Button type="submit" disabled={create.isPending}>Create webhook</Button>
        </form>
        {secretReveal && (
          <div className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
            <div className="font-medium">Signing secret — store this; we won't show it again.</div>
            <code className="mt-2 block break-all font-mono text-xs">{secretReveal}</code>
          </div>
        )}
      </Card>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <ul className="divide-y divide-slate-200 dark:divide-slate-800">
            {(data ?? []).map(w => (
              <li key={w.id} className="flex items-center justify-between p-4">
                <div>
                  <div className="font-mono text-sm">{w.url}</div>
                  <div className="mt-1 text-xs text-slate-500">
                    {w.events.join(', ')} · {w.failure_count} fails · {w.is_active ? 'active' : 'disabled'}
                  </div>
                </div>
                <Button variant="danger" onClick={() => remove.mutate(w.id)}>Delete</Button>
              </li>
            ))}
            {!data?.length && <li className="p-6 text-sm text-slate-500">No webhooks yet.</li>}
          </ul>
        )}
      </Card>
    </div>
  );
}
