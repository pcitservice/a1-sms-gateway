'use client';

import { useQuery } from '@tanstack/react-query';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';

type Me = {
  current_team: {
    name: string;
    trial_ends_at: string | null;
    trial_sms_used: number;
    trial_sms_limit: number;
    plan?: { name: string; price_ore: number; sms_included: number; currency: string } | null;
  };
};

export default function BillingPage() {
  const { data, isLoading } = useQuery<Me>({
    queryKey: ['me'],
    queryFn: () => api('/auth/me', { token: getToken() }),
  });

  if (isLoading || !data) return <p className="text-sm text-slate-500">Loading…</p>;
  const team = data.current_team;
  const trialActive = team.trial_ends_at && new Date(team.trial_ends_at) > new Date();

  return (
    <div>
      <h1 className="text-2xl font-semibold">Billing</h1>
      <p className="mt-1 text-sm text-slate-500">Workspace: {team.name}</p>

      <Card className="mt-6">
        <h3 className="font-semibold">Current plan</h3>
        <p className="mt-2 text-2xl">{team.plan?.name ?? 'Free trial'}</p>
        {team.plan && (
          <p className="text-sm text-slate-500">
            {team.plan.sms_included.toLocaleString()} SMS / month · {(team.plan.price_ore / 100).toFixed(2)} {team.plan.currency}
          </p>
        )}
      </Card>

      {trialActive && (
        <Card className="mt-4 border-amber-300 bg-amber-50 dark:bg-amber-950/30">
          <h3 className="font-semibold text-amber-900 dark:text-amber-200">Trial</h3>
          <p className="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
            {team.trial_sms_used} / {team.trial_sms_limit} trial SMS used · ends {new Date(team.trial_ends_at!).toLocaleDateString()}
          </p>
        </Card>
      )}
    </div>
  );
}
