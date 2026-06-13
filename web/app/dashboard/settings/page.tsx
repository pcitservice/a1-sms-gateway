'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Me = {
  id: number; name: string; email: string; phone: string | null; locale: string;
  current_team?: { id: number; name: string; country: string | null; vat_number: string | null; timezone: string };
};

export default function SettingsPage() {
  const qc = useQueryClient();
  const { data: me } = useQuery<Me>({
    queryKey: ['me'],
    queryFn: () => api('/auth/me', { token: getToken() }),
  });

  const [name, setName]     = useState('');
  const [phone, setPhone]   = useState('');
  const [teamName, setTeamName]   = useState('');
  const [country, setCountry]     = useState('');
  const [vat, setVat]             = useState('');
  const [tz, setTz]               = useState('');

  useEffect(() => {
    if (!me) return;
    setName(me.name); setPhone(me.phone ?? '');
    setTeamName(me.current_team?.name ?? '');
    setCountry(me.current_team?.country ?? '');
    setVat(me.current_team?.vat_number ?? '');
    setTz(me.current_team?.timezone ?? 'Europe/Copenhagen');
  }, [me]);

  const saveProfile = useMutation({
    mutationFn: () => api('/me', { method: 'PATCH', body: JSON.stringify({ name, phone }), token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['me'] }),
  });

  const saveTeam = useMutation({
    mutationFn: () => api('/me/team', { method: 'PATCH', body: JSON.stringify({ name: teamName, country, vat_number: vat, timezone: tz }), token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['me'] }),
  });

  const [cur, setCur] = useState(''); const [next, setNext] = useState(''); const [conf, setConf] = useState('');
  const [pwMsg, setPwMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const changePassword = useMutation({
    mutationFn: () => api('/me/change-password', {
      method: 'POST',
      body: JSON.stringify({ current_password: cur, new_password: next, new_password_confirmation: conf }),
      token: getToken(),
    }),
    onSuccess: () => { setCur(''); setNext(''); setConf(''); setPwMsg({ ok: true, text: 'Password changed.' }); },
    onError:   (e: any) => setPwMsg({ ok: false, text: e.title ?? 'Could not change password.' }),
  });

  return (
    <div className="max-w-3xl space-y-8">
      <h1 className="text-2xl font-semibold">Settings</h1>

      <Card>
        <h3 className="font-semibold">Profile</h3>
        <form onSubmit={e => { e.preventDefault(); saveProfile.mutate(); }} className="mt-4 space-y-3">
          <label className="block"><span className="text-sm">Name</span>
            <Input value={name} onChange={e => setName(e.target.value)} required />
          </label>
          <label className="block"><span className="text-sm">Phone</span>
            <Input value={phone} onChange={e => setPhone(e.target.value)} />
          </label>
          <Button type="submit" disabled={saveProfile.isPending}>Save profile</Button>
        </form>
      </Card>

      <Card>
        <h3 className="font-semibold">Workspace</h3>
        <form onSubmit={e => { e.preventDefault(); saveTeam.mutate(); }} className="mt-4 space-y-3">
          <label className="block"><span className="text-sm">Workspace name</span>
            <Input value={teamName} onChange={e => setTeamName(e.target.value)} required />
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block"><span className="text-sm">Country (2-letter)</span>
              <Input value={country} onChange={e => setCountry(e.target.value.toUpperCase())} maxLength={2} />
            </label>
            <label className="block"><span className="text-sm">VAT number</span>
              <Input value={vat} onChange={e => setVat(e.target.value)} />
            </label>
          </div>
          <label className="block"><span className="text-sm">Timezone</span>
            <Input value={tz} onChange={e => setTz(e.target.value)} />
          </label>
          <Button type="submit" disabled={saveTeam.isPending}>Save workspace</Button>
        </form>
      </Card>

      <Card>
        <h3 className="font-semibold">Change password</h3>
        <form onSubmit={e => { e.preventDefault(); changePassword.mutate(); }} className="mt-4 space-y-3">
          <label className="block"><span className="text-sm">Current password</span>
            <Input type="password" value={cur} onChange={e => setCur(e.target.value)} required />
          </label>
          <label className="block"><span className="text-sm">New password</span>
            <Input type="password" value={next} onChange={e => setNext(e.target.value)} required />
          </label>
          <label className="block"><span className="text-sm">Confirm new password</span>
            <Input type="password" value={conf} onChange={e => setConf(e.target.value)} required />
          </label>
          {pwMsg && <p className={`text-sm ${pwMsg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{pwMsg.text}</p>}
          <Button type="submit" disabled={changePassword.isPending}>Update password</Button>
        </form>
      </Card>

      <Card>
        <h3 className="font-semibold">Data export (GDPR)</h3>
        <p className="mt-2 text-sm text-slate-500">Download every piece of data we hold for you and your workspace.</p>
        <a
          href="/api/v1/me/export"
          target="_blank" rel="noreferrer"
          className="mt-4 inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700"
        >Download JSON export</a>
      </Card>
    </div>
  );
}
