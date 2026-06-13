'use client';

import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';

type Financial = { mrr_ore: number; arr_ore: number; active_subs: number; active_trials: number; churn_30d: number };

export default function AdminOverview() {
  const { data } = useQuery<Financial>({
    queryKey: ['admin-financial'],
    queryFn: () => api('/admin/financial/dashboard', { token: getToken() }),
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Platform overview</h1>
      <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card><div className="text-sm text-slate-500">MRR</div><div className="mt-2 text-2xl font-semibold">{fmt(data?.mrr_ore)} DKK</div></Card>
        <Card><div className="text-sm text-slate-500">ARR</div><div className="mt-2 text-2xl font-semibold">{fmt(data?.arr_ore)} DKK</div></Card>
        <Card><div className="text-sm text-slate-500">Active subs</div><div className="mt-2 text-2xl font-semibold">{data?.active_subs ?? '—'}</div></Card>
        <Card><div className="text-sm text-slate-500">Trials</div><div className="mt-2 text-2xl font-semibold">{data?.active_trials ?? '—'}</div></Card>
      </div>
      <Card className="mt-6">
        <div className="text-sm text-slate-500">30-day churn</div>
        <div className="mt-2 text-2xl font-semibold">{data?.churn_30d ?? 0}%</div>
      </Card>
    </div>
  );
}

function fmt(ore?: number) {
  if (ore == null) return '—';
  return new Intl.NumberFormat('da-DK', { maximumFractionDigits: 0 }).format(Math.round(ore / 100));
}
