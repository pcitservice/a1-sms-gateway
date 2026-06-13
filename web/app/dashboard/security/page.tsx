'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Me = { two_factor_confirmed_at: string | null };

export default function SecurityPage() {
  const qc = useQueryClient();
  const { data: me } = useQuery<Me>({
    queryKey: ['me'],
    queryFn: () => api('/auth/me', { token: getToken() }),
  });

  const [enabling, setEnabling] = useState<{ qr_url: string; secret: string } | null>(null);
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState<string[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const enable2fa = useMutation({
    mutationFn: () => api<{ qr_url: string; secret: string }>('/auth/2fa/enable', { method: 'POST', token: getToken() }),
    onSuccess: setEnabling,
  });

  const confirm2fa = useMutation({
    mutationFn: () => api<{ recovery_codes: string[] }>('/auth/2fa/confirm', {
      method: 'POST', body: JSON.stringify({ code }), token: getToken(),
    }),
    onSuccess: r => { setRecovery(r.recovery_codes); setEnabling(null); qc.invalidateQueries({ queryKey: ['me'] }); },
    onError:   (e: any) => setError(e.title ?? 'Invalid code'),
  });

  const disable2fa = useMutation({
    mutationFn: () => api('/auth/2fa', { method: 'DELETE', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['me'] }),
  });

  const enabled = !!me?.two_factor_confirmed_at;

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-semibold">Security</h1>

      <Card className="mt-6">
        <h3 className="font-semibold">Two-factor authentication</h3>
        <p className="mt-2 text-sm text-slate-500">
          Use Google Authenticator, 1Password, or any TOTP app. Required for browser sign-in once enabled.
        </p>

        {enabled && !enabling && (
          <div className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
            2FA is active.
            <Button variant="danger" className="ml-3" onClick={() => disable2fa.mutate()}>Disable</Button>
          </div>
        )}

        {!enabled && !enabling && (
          <Button className="mt-4" onClick={() => enable2fa.mutate()} disabled={enable2fa.isPending}>Enable 2FA</Button>
        )}

        {enabling && (
          <div className="mt-4 space-y-3">
            <p className="text-sm">Scan this URI in your authenticator app:</p>
            <code className="block break-all rounded bg-slate-100 p-2 font-mono text-xs dark:bg-slate-800">{enabling.qr_url}</code>
            <p className="text-xs text-slate-500">Or enter the secret manually: <span className="font-mono">{enabling.secret}</span></p>
            <Input value={code} onChange={e => setCode(e.target.value)} placeholder="6-digit code" maxLength={6} />
            {error && <p className="text-sm text-red-600">{error}</p>}
            <Button onClick={() => confirm2fa.mutate()} disabled={confirm2fa.isPending || code.length !== 6}>Confirm</Button>
          </div>
        )}

        {recovery && (
          <div className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            <div className="font-medium">Recovery codes — store these somewhere safe.</div>
            <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-xs">
              {recovery.map(c => <li key={c}>{c}</li>)}
            </ul>
          </div>
        )}
      </Card>
    </div>
  );
}
