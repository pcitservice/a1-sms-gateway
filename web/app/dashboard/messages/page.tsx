'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { formatDateTime } from '@/lib/datetime';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Message = {
  id: string;
  direction: 'inbound' | 'outbound';
  from: string | null;
  to: string;
  body: string;
  status: string;
  segments: number;
  cost_ore: number;
  sent_at: string | null;
  delivered_at: string | null;
  received_at: string | null;
  created_at: string;
  error_code: string | null;
  error_message: string | null;
};
type Paged<T> = { data: T[]; total: number; current_page: number; last_page: number };

type Filter = 'all' | 'outbound' | 'inbound' | 'delivered' | 'failed';

export default function MessagesPage() {
  const [filter, setFilter] = useState<Filter>('all');
  const [q,      setQ]      = useState('');
  const [page,   setPage]   = useState(1);

  const params = new URLSearchParams();
  params.set('page',     String(page));
  params.set('per_page', '25');
  if (filter === 'outbound' || filter === 'inbound') params.set('direction', filter);
  if (filter === 'delivered' || filter === 'failed') params.set('status',    filter);
  if (q.trim()) params.set('q', q.trim());

  const { data, isLoading, isFetching } = useQuery<Paged<Message>>({
    queryKey: ['messages', filter, q, page],
    queryFn: () => api(`/messages?${params.toString()}`, { token: getToken() }),
    refetchInterval: 15_000,
  });

  const rows = data?.data ?? [];

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Messages</h1>
          <p className="mt-1 text-sm text-slate-500">Everything sent and received. Auto-refreshes every 15 s.</p>
        </div>
        <div className="text-xs text-slate-500">
          {isFetching && <span className="animate-pulse">Refreshing…</span>}
        </div>
      </div>

      {/* Filter tabs */}
      <div className="mt-6 flex flex-wrap gap-2">
        {(['all', 'outbound', 'inbound', 'delivered', 'failed'] as Filter[]).map(f => (
          <button
            key={f}
            onClick={() => { setFilter(f); setPage(1); }}
            className={`rounded-full px-3 py-1 text-xs font-medium capitalize transition ${
              filter === f
                ? 'bg-brand-600 text-white'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
            }`}
          >
            {f}
          </button>
        ))}
        <div className="ml-auto w-56">
          <Input
            value={q}
            onChange={e => { setQ(e.target.value); setPage(1); }}
            placeholder="Search recipient…"
          />
        </div>
      </div>

      <Card className="mt-4 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500 border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="px-4 py-3 w-20">Dir</th>
                <th className="px-4 py-3 w-40">When</th>
                <th className="px-4 py-3 w-40">Peer</th>
                <th className="px-4 py-3">Message</th>
                <th className="px-4 py-3 w-24">Status</th>
                <th className="px-4 py-3 w-16 text-right">Cost</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {rows.map(m => {
                const when = m.direction === 'outbound'
                  ? (m.delivered_at ?? m.sent_at ?? m.created_at)
                  : (m.received_at ?? m.created_at);
                const peer = m.direction === 'outbound' ? m.to : (m.from ?? '?');
                return (
                  <tr key={m.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 align-top">
                    <td className="px-4 py-3 text-xs">
                      <DirectionIcon direction={m.direction} />
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                      {formatDateTime(when)}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs">
                      <Link href={`/dashboard/inbox/${encodeURIComponent(peer)}`}
                            className="hover:underline">
                        {peer}
                      </Link>
                    </td>
                    <td className="px-4 py-3 text-slate-800 dark:text-slate-100">
                      <div className="line-clamp-2">{m.body}</div>
                      {m.error_message && (
                        <div className="mt-1 text-xs text-red-600">{m.error_message}</div>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <StatusPill status={m.status} />
                    </td>
                    <td className="px-4 py-3 text-right text-xs text-slate-500">
                      {m.direction === 'outbound' && m.cost_ore > 0
                        ? `${(m.cost_ore / 100).toFixed(2)} DKK`
                        : '—'}
                    </td>
                  </tr>
                );
              })}
              {!rows.length && (
                <tr><td colSpan={6} className="px-4 py-12 text-center text-slate-500">
                  No messages match this filter.
                </td></tr>
              )}
            </tbody>
          </table>
        )}
      </Card>

      {data && data.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-xs text-slate-500">
          <span>{data.total} messages · page {data.current_page} of {data.last_page}</span>
          <div className="flex gap-2">
            <Button variant="ghost" disabled={page <= 1} onClick={() => setPage(p => Math.max(1, p - 1))}>← Prev</Button>
            <Button variant="ghost" disabled={page >= data.last_page} onClick={() => setPage(p => p + 1)}>Next →</Button>
          </div>
        </div>
      )}
    </div>
  );
}

function DirectionIcon({ direction }: { direction: 'inbound' | 'outbound' }) {
  return direction === 'outbound' ? (
    <span className="inline-flex items-center gap-1 text-xs font-medium text-brand-700 dark:text-brand-300">↗ out</span>
  ) : (
    <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">↙ in</span>
  );
}

function StatusPill({ status }: { status: string }) {
  const styles: Record<string, string> = {
    delivered: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    sent:      'bg-blue-100    text-blue-800    dark:bg-blue-900/40    dark:text-blue-200',
    queued:    'bg-blue-100    text-blue-800    dark:bg-blue-900/40    dark:text-blue-200',
    scheduled: 'bg-amber-100   text-amber-800   dark:bg-amber-900/40   dark:text-amber-200',
    failed:    'bg-red-100     text-red-700     dark:bg-red-900/40     dark:text-red-200',
    cancelled: 'bg-slate-100   text-slate-600   dark:bg-slate-800      dark:text-slate-400',
    received:  'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  };
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${styles[status] ?? 'bg-slate-100 text-slate-700'}`}>
      {status}
    </span>
  );
}
