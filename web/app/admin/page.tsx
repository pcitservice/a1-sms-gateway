'use client';

import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';

type Financial = { mrr_ore: number; arr_ore: number; active_subs: number; active_trials: number; churn_30d: number };
type Queue     = { redis: Record<string, number | null>; failed: number };
type Device    = { id: number; name: string; kind: string; status: string; last_seen_at: string | null };

export default function AdminOverview() {
  const { data: fin }  = useQuery<Financial>({
    queryKey: ['admin-financial'],
    queryFn: () => api('/admin/financial/dashboard', { token: getToken() }),
  });
  const { data: q }    = useQuery<Queue>({
    queryKey: ['admin-queue'],
    queryFn: () => api('/admin/system/queue', { token: getToken() }),
    refetchInterval: 15_000,
  });
  const { data: devs } = useQuery<Device[]>({
    queryKey: ['admin-devices'],
    queryFn: () => api('/admin/system/devices', { token: getToken() }),
    refetchInterval: 30_000,
  });

  const gatewaysOnline = (devs ?? []).filter(d => d.status === 'online').length;
  const gatewaysTotal  = (devs ?? []).length;
  const outboundQueue  = q?.redis['sms.outbound'] ?? 0;
  const failedJobs     = q?.failed ?? 0;

  return (
    <div>
      <h1 className="text-2xl font-semibold">Platform overview</h1>

      {/* Business metrics */}
      <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card><div className="text-sm text-slate-500">MRR</div><div className="mt-2 text-2xl font-semibold">{fmt(fin?.mrr_ore)} DKK</div></Card>
        <Card><div className="text-sm text-slate-500">ARR</div><div className="mt-2 text-2xl font-semibold">{fmt(fin?.arr_ore)} DKK</div></Card>
        <Card><div className="text-sm text-slate-500">Active subs</div><div className="mt-2 text-2xl font-semibold">{fin?.active_subs ?? '—'}</div></Card>
        <Card><div className="text-sm text-slate-500">Trials</div><div className="mt-2 text-2xl font-semibold">{fin?.active_trials ?? '—'}</div></Card>
      </div>

      {/* System health strip */}
      <h2 className="mt-10 text-lg font-semibold">System health</h2>
      <div className="mt-3 grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card>
          <div className="text-sm text-slate-500">Gateways</div>
          <div className={`mt-2 text-2xl font-semibold ${gatewaysOnline === 0 && gatewaysTotal > 0 ? 'text-red-600' : gatewaysOnline < gatewaysTotal ? 'text-amber-600' : 'text-emerald-600'}`}>
            {gatewaysOnline}/{gatewaysTotal}
          </div>
          <div className="mt-1 text-xs text-slate-500">online / total</div>
        </Card>
        <Card>
          <div className="text-sm text-slate-500">Outbound queue</div>
          <div className={`mt-2 text-2xl font-semibold ${outboundQueue > 100 ? 'text-amber-600' : ''}`}>
            {outboundQueue.toLocaleString()}
          </div>
          <div className="mt-1 text-xs text-slate-500">jobs pending</div>
        </Card>
        <Card>
          <div className="text-sm text-slate-500">Failed jobs</div>
          <div className={`mt-2 text-2xl font-semibold ${failedJobs > 0 ? 'text-red-600' : 'text-emerald-600'}`}>
            {failedJobs.toLocaleString()}
          </div>
          <div className="mt-1 text-xs text-slate-500">need attention</div>
        </Card>
        <Card>
          <div className="text-sm text-slate-500">30-day churn</div>
          <div className="mt-2 text-2xl font-semibold">{fin?.churn_30d ?? 0}%</div>
        </Card>
      </div>

      {/* Gateway detail */}
      {devs && devs.length > 0 && (
        <Card className="mt-6 p-0">
          <div className="border-b border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-800">Gateways</div>
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500">
              <tr>
                <th className="px-4 py-2">Name</th>
                <th className="px-4 py-2">Kind</th>
                <th className="px-4 py-2">Status</th>
                <th className="px-4 py-2">Last seen</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {devs.map(d => (
                <tr key={d.id}>
                  <td className="px-4 py-2 font-medium">{d.name}</td>
                  <td className="px-4 py-2 text-xs text-slate-500">{d.kind}</td>
                  <td className="px-4 py-2">
                    <span className={`text-xs font-medium ${d.status === 'online' ? 'text-emerald-600' : 'text-slate-500'}`}>{d.status}</span>
                  </td>
                  <td className="px-4 py-2 text-xs text-slate-500">
                    {d.last_seen_at ? new Date(d.last_seen_at).toLocaleString() : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  );
}

function fmt(ore?: number) {
  if (ore == null) return '—';
  return new Intl.NumberFormat('da-DK', { maximumFractionDigits: 0 }).format(Math.round(ore / 100));
}
