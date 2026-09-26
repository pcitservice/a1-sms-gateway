'use client';

import { useMutation, useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'next/navigation';
import { Suspense } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Summary = {
  trial:   { in_trial: boolean; used: number; limit: number; remaining: number; ends_at: string | null };
  credits: number;
  offer:   { sms: number; price_cent: number; currency: string; label: string };
};

export default function BillingPage() {
  return (
    <Suspense fallback={<p className="text-sm text-slate-500">Loading…</p>}>
      <BillingInner />
    </Suspense>
  );
}

function BillingInner() {
  const params = useSearchParams();
  const flash  = params.get('checkout');
  const sessionId = params.get('session_id');

  const { data, isLoading, refetch } = useQuery<Summary>({
    queryKey: ['billing-summary'],
    queryFn: () => api('/billing/summary', { token: getToken() }),
  });

  // On return from Stripe with a session_id, verify + grant credits.
  // This is idempotent; if the webhook already fired it just no-ops.
  if (typeof window !== 'undefined' && flash === 'success' && sessionId && !(window as any).__verifiedSession) {
    (window as any).__verifiedSession = sessionId;
    api('/billing/verify-checkout', {
      method: 'POST',
      body: JSON.stringify({ session_id: sessionId }),
      token: getToken(),
    }).then(() => refetch()).catch(() => {});
  }

  const checkout = useMutation({
    mutationFn: () => api<{ url: string }>('/billing/checkout', { method: 'POST', token: getToken() }),
    onSuccess: (r) => { window.location.href = r.url; },
  });

  if (isLoading || !data) return <p className="text-sm text-slate-500">Loading…</p>;

  const totalRemaining = data.credits + (data.trial.in_trial ? data.trial.remaining : 0);
  const priceLabel     = `€${(data.offer.price_cent / 100).toFixed(2)}`;

  return (
    <div className="max-w-3xl">
      <h1 className="text-2xl font-semibold">Billing</h1>
      <p className="mt-1 text-sm text-slate-500">Pay-as-you-go SMS credits.</p>

      {flash === 'success' && (
        <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200">
          ✅ Payment received. Credits added to your workspace.
        </div>
      )}
      {flash === 'cancelled' && (
        <div className="mt-4 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300">
          Checkout cancelled — nothing was charged.
        </div>
      )}

      {/* Balance */}
      <Card className="mt-6">
        <div className="text-sm text-slate-500">Available to send</div>
        <div className="mt-2 text-4xl font-semibold">{totalRemaining.toLocaleString()} SMS</div>
        <div className="mt-3 flex gap-6 text-sm text-slate-500">
          <span>Prepaid credits: <strong className="text-slate-900 dark:text-slate-100">{data.credits}</strong></span>
          {data.trial.in_trial && (
            <span>
              Trial: <strong className="text-slate-900 dark:text-slate-100">{data.trial.remaining}</strong> of {data.trial.limit} remaining
              {data.trial.ends_at && <> · ends {new Date(data.trial.ends_at).toLocaleDateString()}</>}
            </span>
          )}
        </div>
      </Card>

      {/* Buy */}
      <Card className="mt-6">
        <h3 className="font-semibold">Top up</h3>
        <p className="mt-1 text-sm text-slate-500">One-off purchase. No subscription.</p>

        <div className="mt-4 rounded-lg border border-slate-200 p-4 dark:border-slate-800">
          <div className="flex items-center justify-between">
            <div>
              <div className="text-lg font-semibold">{data.offer.label}</div>
              <div className="text-xs text-slate-500">€{(data.offer.price_cent / 100 / data.offer.sms).toFixed(3)} per SMS</div>
            </div>
            <div className="text-2xl font-semibold">{priceLabel}</div>
          </div>
          <Button
            onClick={() => checkout.mutate()}
            disabled={checkout.isPending}
            className="mt-4 w-full"
          >
            {checkout.isPending ? 'Redirecting to Stripe…' : `Buy ${data.offer.sms} SMS for ${priceLabel}`}
          </Button>
          {checkout.error && (
            <p className="mt-2 text-sm text-red-600">
              {(checkout.error as any)?.message ?? 'Could not start checkout.'}
            </p>
          )}
          <p className="mt-3 text-xs text-slate-500">Secure payment via Stripe. You'll be redirected off-site.</p>
        </div>
      </Card>
    </div>
  );
}
