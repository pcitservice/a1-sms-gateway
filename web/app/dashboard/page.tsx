'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { UsageChart } from '@/components/UsageChart';

type Usage = {
  totals: { sent: number; delivered: number; failed: number; received: number; segments: number };
  series: Array<{
    period: string;
    messages_sent: number; messages_delivered: number;
    messages_failed: number; messages_received: number;
  }>;
};
type BillingSummary = {
  credits: number;
  trial: { in_trial: boolean; remaining: number; limit: number; ends_at: string | null };
  offer: { sms: number; price_cent: number; currency: string };
};

export default function DashboardHome() {
  const { data, isLoading } = useQuery<Usage>({
    queryKey: ['usage'],
    queryFn: () => api<Usage>('/reports/usage', { token: getToken() }),
  });
  const { data: billing } = useQuery<BillingSummary>({
    queryKey: ['billing-summary'],
    queryFn: () => api('/billing/summary', { token: getToken() }),
  });

  const cards: Array<[string, number]> = [
    ['Sent',      data?.totals.sent      ?? 0],
    ['Delivered', data?.totals.delivered ?? 0],
    ['Failed',    data?.totals.failed    ?? 0],
    ['Received',  data?.totals.received  ?? 0],
  ];

  return (
    <div>
      <h1 className="text-2xl font-semibold">Overview</h1>
      <p className="mt-1 text-sm text-slate-500">Last 30 days.</p>

      {/* Credits banner */}
      {billing && (
        <Card className="mt-6 flex flex-wrap items-center justify-between gap-4">
          <div>
            <div className="text-sm text-slate-500">Available to send</div>
            <div className="mt-1 text-3xl font-semibold">
              {(billing.credits + (billing.trial.in_trial ? billing.trial.remaining : 0)).toLocaleString()} SMS
            </div>
            <div className="mt-1 text-xs text-slate-500">
              {billing.credits} prepaid
              {billing.trial.in_trial && (
                <> · {billing.trial.remaining} of {billing.trial.limit} trial remaining</>
              )}
            </div>
          </div>
          <Link href="/dashboard/billing"><Button>Top up</Button></Link>
        </Card>
      )}

      <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        {cards.map(([label, n]) => (
          <Card key={label}>
            <div className="text-sm text-slate-500">{label}</div>
            <div className="mt-2 text-3xl font-semibold">{isLoading ? '…' : n.toLocaleString()}</div>
            {label === 'Delivered' && data && data.totals.sent > 0 && (
              <div className="mt-1 text-xs text-emerald-600">
                {((data.totals.delivered / data.totals.sent) * 100).toFixed(1)}% delivery rate
              </div>
            )}
          </Card>
        ))}
      </div>

      <Card className="mt-8">
        <div className="flex items-baseline justify-between">
          <h3 className="text-lg font-semibold">Daily traffic</h3>
          <div className="text-xs text-slate-500">{data?.totals.segments ?? 0} segments billed</div>
        </div>
        <div className="mt-4">
          {isLoading ? <div className="grid h-64 place-items-center text-sm text-slate-500">Loading…</div> : <UsageChart data={data?.series ?? []} />}
        </div>
      </Card>
    </div>
  );
}
