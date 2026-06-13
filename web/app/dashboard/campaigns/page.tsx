'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Campaign = {
  id: string; name: string; status: string; body: string;
  total_recipients: number; sent_count: number; failed_count: number;
  scheduled_at: string | null;
};

export default function CampaignsPage() {
  const qc = useQueryClient();
  const [name, setName] = useState('');
  const [body, setBody] = useState('');

  const { data, isLoading } = useQuery<{ data: Campaign[] }>({
    queryKey: ['campaigns'],
    queryFn: () => api('/campaigns', { token: getToken() }),
  });

  const create = useMutation({
    mutationFn: () => api('/campaigns', {
      method: 'POST',
      body: JSON.stringify({ name, body, targets: { contact_ids: [] } }),
      token: getToken(),
    }),
    onSuccess: () => { setName(''); setBody(''); qc.invalidateQueries({ queryKey: ['campaigns'] }); },
  });

  const launch = useMutation({
    mutationFn: (id: string) => api(`/campaigns/${id}/launch`, { method: 'POST', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['campaigns'] }),
  });

  const pause = useMutation({
    mutationFn: (id: string) => api(`/campaigns/${id}/pause`, { method: 'POST', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['campaigns'] }),
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Campaigns</h1>
      <p className="mt-1 text-sm text-slate-500">Send templated messages to contact groups, now or on a schedule.</p>

      <Card className="mt-6">
        <h3 className="font-semibold">New draft</h3>
        <form onSubmit={e => { e.preventDefault(); create.mutate(); }} className="mt-3 space-y-3">
          <Input placeholder="Campaign name" value={name} onChange={e => setName(e.target.value)} required />
          <textarea
            value={body} onChange={e => setBody(e.target.value)} required maxLength={1530} rows={3}
            placeholder="Message body — supports {{first_name}}"
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
          />
          <p className="text-xs text-slate-500">Pick recipients on the campaign detail page (targets API).</p>
          <Button type="submit" disabled={create.isPending}>Create draft</Button>
        </form>
      </Card>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500">
              <tr><th className="px-4 py-3">Name</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Sent / Total</th><th className="px-4 py-3"></th></tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {(data?.data ?? []).map(c => (
                <tr key={c.id}>
                  <td className="px-4 py-3">
                    <Link href={`/dashboard/campaigns/${c.id}`} className="hover:underline">{c.name}</Link>
                  </td>
                  <td className="px-4 py-3"><StatusPill status={c.status} /></td>
                  <td className="px-4 py-3">{c.sent_count} / {c.total_recipients}</td>
                  <td className="px-4 py-3 text-right space-x-2">
                    {(c.status === 'draft' || c.status === 'scheduled' || c.status === 'paused') && (
                      <Button variant="primary" onClick={() => launch.mutate(c.id)}>Launch</Button>
                    )}
                    {c.status === 'running' && (
                      <Button variant="secondary" onClick={() => pause.mutate(c.id)}>Pause</Button>
                    )}
                  </td>
                </tr>
              ))}
              {!data?.data?.length && <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-500">No campaigns yet.</td></tr>}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}

function StatusPill({ status }: { status: string }) {
  const color = {
    draft: 'bg-slate-100 text-slate-700',
    scheduled: 'bg-amber-100 text-amber-700',
    running: 'bg-emerald-100 text-emerald-700',
    paused: 'bg-slate-100 text-slate-700',
    completed: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-slate-100 text-slate-700';
  return <span className={`rounded-full px-2 py-0.5 text-xs ${color}`}>{status}</span>;
}
