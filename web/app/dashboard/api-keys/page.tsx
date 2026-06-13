'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Token = { id: number; name: string; last_used_at?: string; expires_at?: string };

export default function ApiKeysPage() {
  const qc = useQueryClient();
  const [name, setName] = useState('');
  const [reveal, setReveal] = useState<string | null>(null);

  const { data, isLoading } = useQuery<Token[]>({
    queryKey: ['api-keys'],
    queryFn: () => api('/api-keys', { token: getToken() }),
  });

  const createKey = useMutation({
    mutationFn: () =>
      api<{ id: number; token: string }>('/api-keys', {
        method: 'POST', body: JSON.stringify({ name }), token: getToken(),
      }),
    onSuccess: r => { setReveal(r.token); setName(''); qc.invalidateQueries({ queryKey: ['api-keys'] }); },
  });

  const revokeKey = useMutation({
    mutationFn: (id: number) => api(`/api-keys/${id}`, { method: 'DELETE', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['api-keys'] }),
  });

  return (
    <div className="max-w-3xl">
      <h1 className="text-2xl font-semibold">API keys</h1>
      <p className="mt-1 text-sm text-slate-500">Use these to call the REST API. Treat like passwords.</p>

      <Card className="mt-6">
        <form onSubmit={e => { e.preventDefault(); createKey.mutate(); }} className="flex gap-3">
          <Input placeholder="Key name (e.g. CRM integration)" value={name} onChange={e => setName(e.target.value)} required />
          <Button type="submit" disabled={createKey.isPending}>Create</Button>
        </form>
        {reveal && (
          <div className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
            <div className="font-medium">Copy this now — you won't see it again.</div>
            <code className="mt-2 block break-all font-mono text-xs">{reveal}</code>
          </div>
        )}
      </Card>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <ul className="divide-y divide-slate-200 dark:divide-slate-800">
            {data?.map(t => (
              <li key={t.id} className="flex items-center justify-between p-4">
                <div>
                  <div className="font-medium">{t.name}</div>
                  <div className="text-xs text-slate-500">
                    last used {t.last_used_at ? new Date(t.last_used_at).toLocaleString() : 'never'}
                    {t.expires_at ? ` · expires ${new Date(t.expires_at).toLocaleDateString()}` : ''}
                  </div>
                </div>
                <Button variant="danger" onClick={() => revokeKey.mutate(t.id)}>Revoke</Button>
              </li>
            ))}
            {!data?.length && <li className="p-6 text-sm text-slate-500">No keys yet.</li>}
          </ul>
        )}
      </Card>
    </div>
  );
}
