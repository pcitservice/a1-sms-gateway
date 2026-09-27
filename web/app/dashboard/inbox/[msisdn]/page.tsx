'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { formatDateTime } from '@/lib/datetime';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Msg = {
  id: string;
  direction: 'inbound' | 'outbound';
  from: string | null;
  to: string;
  body: string;
  status: string;
  created_at: string;
  received_at: string | null;
  sent_at: string | null;
  delivered_at: string | null;
};

type Page = { data: Msg[] };

export default function ThreadPage() {
  const params = useParams<{ msisdn: string }>();
  const msisdn = decodeURIComponent(params.msisdn);
  const qc = useQueryClient();
  const [reply, setReply] = useState('');
  const [err, setErr] = useState<string | null>(null);

  const { data, isLoading } = useQuery<Page>({
    queryKey: ['inbox-thread', msisdn],
    queryFn: () => api(`/inbox/threads/${encodeURIComponent(msisdn)}`, { token: getToken() }),
    refetchInterval: 10_000,
  });

  const send = useMutation({
    mutationFn: () =>
      api('/send-sms', {
        method: 'POST',
        body: JSON.stringify({ to: msisdn, message: reply }),
        token: getToken(),
      }),
    onSuccess: () => {
      setReply('');
      setErr(null);
      qc.invalidateQueries({ queryKey: ['inbox-thread', msisdn] });
    },
    onError: (e: any) => setErr(e.detail ?? e.title ?? 'Send failed'),
  });

  const msgs = data?.data ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <div className="mb-4 flex items-center gap-3">
        <Link href="/dashboard/inbox" className="text-sm text-slate-500 hover:underline">← Inbox</Link>
        <h1 className="text-xl font-semibold">{msisdn}</h1>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <ul className="divide-y divide-slate-200 dark:divide-slate-800 max-h-[65vh] overflow-y-auto">
            {msgs.map(m => {
              const isIn = m.direction === 'inbound';
              const stamp = m.received_at ?? m.sent_at ?? m.created_at;
              return (
                <li key={m.id} className={`p-4 flex ${isIn ? 'justify-start' : 'justify-end'}`}>
                  <div className={`max-w-[75%] rounded-2xl px-4 py-2 ${
                    isIn
                      ? 'bg-slate-100 dark:bg-slate-800'
                      : 'bg-brand-500 text-white'
                  }`}>
                    <div className="text-sm whitespace-pre-wrap">{m.body}</div>
                    <div className={`mt-1 text-[10px] ${isIn ? 'text-slate-500' : 'text-white/70'}`}>
                      {formatDateTime(stamp)} · {m.status}
                    </div>
                  </div>
                </li>
              );
            })}
            {msgs.length === 0 && <li className="p-6 text-sm text-slate-500">No messages yet.</li>}
          </ul>
        )}
      </Card>

      <form
        onSubmit={e => { e.preventDefault(); if (reply.trim()) send.mutate(); }}
        className="mt-4 flex gap-2"
      >
        <input
          value={reply}
          onChange={e => setReply(e.target.value)}
          placeholder={`Reply to ${msisdn}…`}
          className="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
        />
        <Button type="submit" disabled={send.isPending || !reply.trim()}>
          {send.isPending ? 'Sending…' : 'Send'}
        </Button>
      </form>
      {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
    </div>
  );
}
