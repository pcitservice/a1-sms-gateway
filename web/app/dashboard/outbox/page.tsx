'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';

type OutboundRow = {
  id: string;
  to: string;
  body: string;
  status: 'scheduled' | 'queued' | 'sending';
  send_at: string | null;
  queued_at: string | null;
  created_at: string;
};
type Paged<T> = { data: T[]; total: number; current_page: number; last_page: number };

export default function OutboxPage() {
  const qc = useQueryClient();

  const { data, isLoading } = useQuery<Paged<OutboundRow>>({
    queryKey: ['outbox'],
    queryFn: () => api('/messages/outbox', { token: getToken() }),
    refetchInterval: 10_000,
  });

  const cancel = useMutation({
    mutationFn: (id: string) => api(`/messages/${id}`, { method: 'DELETE', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['outbox'] }),
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Outbox</h1>
      <p className="mt-1 text-sm text-slate-500">
        Messages waiting to be sent — scheduled, queued, or in flight. Cancel a scheduled message before it fires and the credit is refunded.
      </p>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500 border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="px-4 py-3 w-28">Status</th>
                <th className="px-4 py-3 w-40">Fires</th>
                <th className="px-4 py-3 w-40">To</th>
                <th className="px-4 py-3">Message</th>
                <th className="px-4 py-3 w-24 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {(data?.data ?? []).map(m => (
                <tr key={m.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 align-top">
                  <td className="px-4 py-3">
                    <StatusPill status={m.status} />
                  </td>
                  <td className="px-4 py-3 text-xs">
                    {m.send_at ? new Date(m.send_at).toLocaleString() :
                     m.queued_at ? <em className="text-slate-500">now (queued)</em> :
                     <em className="text-slate-500">now</em>}
                  </td>
                  <td className="px-4 py-3 font-mono text-xs">{m.to}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-200 line-clamp-2">{m.body}</td>
                  <td className="px-4 py-3 text-right">
                    {m.status !== 'sending' && (
                      <button
                        className="text-xs text-red-600 hover:underline disabled:opacity-40"
                        disabled={cancel.isPending}
                        onClick={() => { if (confirm('Cancel this message? The credit will be refunded.')) cancel.mutate(m.id); }}
                      >Cancel</button>
                    )}
                  </td>
                </tr>
              ))}
              {!data?.data?.length && (
                <tr><td colSpan={5} className="px-4 py-12 text-center text-slate-500">
                  Nothing pending. New sends will appear here while they wait.
                </td></tr>
              )}
            </tbody>
          </table>
        )}
      </Card>

      {data && data.total > 0 && (
        <p className="mt-3 text-xs text-slate-500">
          {data.total} pending · updates every 10 s
        </p>
      )}
    </div>
  );
}

function StatusPill({ status }: { status: string }) {
  const styles: Record<string, string> = {
    scheduled: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    queued:    'bg-blue-100  text-blue-800  dark:bg-blue-900/40  dark:text-blue-200',
    sending:   'bg-slate-100 text-slate-800 dark:bg-slate-800    dark:text-slate-200',
  };
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${styles[status] ?? 'bg-slate-100'}`}>
      {status}
    </span>
  );
}
