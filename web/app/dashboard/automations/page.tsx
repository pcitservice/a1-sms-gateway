'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Automation = {
  id: number; name: string; is_active: boolean; trigger_type: string;
  trigger_config: Record<string, unknown>; actions: any[]; execution_count: number;
};

export default function AutomationsPage() {
  const qc = useQueryClient();
  const [name, setName] = useState('');
  const [keyword, setKeyword] = useState('STOP');
  const [reply, setReply] = useState("Du er afmeldt. Send START for at modtage igen.");

  const { data, isLoading } = useQuery<Automation[]>({
    queryKey: ['automations'],
    queryFn: () => api('/automations', { token: getToken() }),
  });

  const create = useMutation({
    mutationFn: () =>
      api('/automations', {
        method: 'POST',
        body: JSON.stringify({
          name,
          is_active:      true,
          trigger_type:   'keyword',
          trigger_config: { keyword },
          actions:        [{ type: 'send_reply', body: reply }],
        }),
        token: getToken(),
      }),
    onSuccess: () => { setName(''); qc.invalidateQueries({ queryKey: ['automations'] }); },
  });

  const toggle = useMutation({
    mutationFn: ({ id, active }: { id: number; active: boolean }) =>
      api(`/automations/${id}`, { method: 'PATCH', body: JSON.stringify({ is_active: active }), token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['automations'] }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => api(`/automations/${id}`, { method: 'DELETE', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['automations'] }),
  });

  return (
    <div className="max-w-3xl">
      <h1 className="text-2xl font-semibold">Automations</h1>
      <p className="mt-1 text-sm text-slate-500">
        Trigger an action when an SMS keyword arrives — auto-reply, webhook, API call, or tag the contact.
      </p>

      <Card className="mt-6">
        <h3 className="font-semibold">New keyword automation</h3>
        <form onSubmit={e => { e.preventDefault(); create.mutate(); }} className="mt-3 space-y-3">
          <Input value={name} onChange={e => setName(e.target.value)} placeholder="Name (e.g. Unsubscribe handler)" required />
          <label className="block">
            <span className="text-sm">Keyword (first word, case-insensitive)</span>
            <Input value={keyword} onChange={e => setKeyword(e.target.value.toUpperCase())} required maxLength={20} />
          </label>
          <label className="block">
            <span className="text-sm">Auto-reply body</span>
            <textarea
              value={reply} onChange={e => setReply(e.target.value)} rows={3} required
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
            />
          </label>
          <Button type="submit" disabled={create.isPending}>Create automation</Button>
        </form>
      </Card>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <ul className="divide-y divide-slate-200 dark:divide-slate-800">
            {(data ?? []).map(a => (
              <li key={a.id} className="flex items-center justify-between p-4">
                <div>
                  <div className="font-medium">{a.name}</div>
                  <div className="text-xs text-slate-500">
                    Trigger: {a.trigger_type}{a.trigger_type === 'keyword' && a.trigger_config.keyword ? ` "${String(a.trigger_config.keyword)}"` : ''}
                    {' · '}
                    {a.execution_count} runs
                  </div>
                </div>
                <div className="space-x-2">
                  <Button variant="secondary" onClick={() => toggle.mutate({ id: a.id, active: !a.is_active })}>
                    {a.is_active ? 'Disable' : 'Enable'}
                  </Button>
                  <Button variant="danger" onClick={() => remove.mutate(a.id)}>Delete</Button>
                </div>
              </li>
            ))}
            {!data?.length && <li className="p-6 text-sm text-slate-500">No automations yet.</li>}
          </ul>
        )}
      </Card>
    </div>
  );
}
