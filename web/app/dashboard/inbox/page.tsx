'use client';

import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';

type Thread = { msisdn: string; last_at: string; count: number };

export default function InboxPage() {
  const { data, isLoading } = useQuery<{ data: Thread[] }>({
    queryKey: ['inbox-threads'],
    queryFn: () => api('/inbox/threads', { token: getToken() }),
    refetchInterval: 15_000,
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Inbox</h1>
      <p className="mt-1 text-sm text-slate-500">Replies and incoming messages from your customers.</p>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <ul className="divide-y divide-slate-200 dark:divide-slate-800">
            {(data?.data ?? []).map(t => (
              <li key={t.msisdn} className="flex items-center justify-between p-4">
                <div>
                  <div className="font-medium">{t.msisdn}</div>
                  <div className="text-xs text-slate-500">{t.count} message{t.count === 1 ? '' : 's'}</div>
                </div>
                <div className="text-xs text-slate-500">{new Date(t.last_at).toLocaleString()}</div>
              </li>
            ))}
            {!data?.data?.length && <li className="p-6 text-sm text-slate-500">Inbox is empty.</li>}
          </ul>
        )}
      </Card>
    </div>
  );
}
