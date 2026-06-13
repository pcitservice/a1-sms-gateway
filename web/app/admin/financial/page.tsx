'use client';

import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';

export default function AdminFinancial() {
  const { data } = useQuery<{ mrr_ore: number }>({
    queryKey: ['admin-mrr'],
    queryFn: () => api('/admin/financial/mrr', { token: getToken() }),
  });
  const { data: churn } = useQuery<{ churn_30d: number }>({
    queryKey: ['admin-churn'],
    queryFn: () => api('/admin/financial/churn', { token: getToken() }),
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Financial</h1>
      <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3">
        <Card>
          <div className="text-sm text-slate-500">MRR</div>
          <div className="mt-2 text-2xl font-semibold">
            {data ? `${(data.mrr_ore / 100).toLocaleString('da-DK')} DKK` : '—'}
          </div>
        </Card>
        <Card>
          <div className="text-sm text-slate-500">30-day churn</div>
          <div className="mt-2 text-2xl font-semibold">{churn?.churn_30d ?? '—'}%</div>
        </Card>
      </div>
    </div>
  );
}
