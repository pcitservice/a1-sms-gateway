'use client';

import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';

type Queue = { redis: Record<string, number | null>; failed: number };

export default function AdminSystem() {
  const { data, isLoading } = useQuery<Queue>({
    queryKey: ['admin-queue'],
    queryFn: () => api('/admin/system/queue', { token: getToken() }),
    refetchInterval: 10_000,
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">System</h1>
      <Card className="mt-6">
        <h3 className="font-semibold">Queues</h3>
        {isLoading ? (
          <p className="mt-2 text-sm text-slate-500">Loading…</p>
        ) : (
          <ul className="mt-3 space-y-1 text-sm font-mono">
            {Object.entries(data?.redis ?? {}).map(([k, v]) => (
              <li key={k} className="flex justify-between"><span>{k}</span><span>{v ?? '—'}</span></li>
            ))}
            <li className="flex justify-between border-t pt-2 mt-2"><span>failed_jobs</span><span>{data?.failed}</span></li>
          </ul>
        )}
      </Card>
    </div>
  );
}
