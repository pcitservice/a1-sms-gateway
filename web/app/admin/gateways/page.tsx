'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Gateway = {
  id: number; team_id: number | null; name: string; kind: string; host: string | null;
  status: string; health?: { signal_rssi?: number; operator?: string; sim_status?: string };
  last_seen_at: string | null;
};

export default function AdminGateways() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery<{ data: Gateway[] }>({
    queryKey: ['admin-gateways'],
    queryFn: () => api('/admin/gateways', { token: getToken() }),
    refetchInterval: 30_000,
  });

  const reboot = useMutation({
    mutationFn: (id: number) => api(`/admin/gateways/${id}/reboot`, { method: 'POST', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-gateways'] }),
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Gateways</h1>
      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500">
              <tr>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Kind</th>
                <th className="px-4 py-3">Host</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Signal</th>
                <th className="px-4 py-3">SIM</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {(data?.data ?? []).map(g => (
                <tr key={g.id}>
                  <td className="px-4 py-3">{g.name}</td>
                  <td className="px-4 py-3 font-mono">{g.kind}</td>
                  <td className="px-4 py-3 font-mono">{g.host ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs ${
                      g.status === 'online'
                        ? 'bg-emerald-100 text-emerald-700'
                        : g.status === 'degraded' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'
                    }`}>{g.status}</span>
                  </td>
                  <td className="px-4 py-3">{g.health?.signal_rssi ?? '—'} dBm</td>
                  <td className="px-4 py-3">{g.health?.sim_status ?? '—'}</td>
                  <td className="px-4 py-3 text-right">
                    <Button variant="secondary" onClick={() => reboot.mutate(g.id)}>Reboot</Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
