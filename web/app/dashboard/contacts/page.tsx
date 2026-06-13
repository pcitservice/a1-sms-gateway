'use client';

import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Contact = { id: number; msisdn: string; first_name?: string; last_name?: string; email?: string; opt_in_status: string };
type Paged<T> = { data: T[]; total: number };

export default function ContactsPage() {
  const [q, setQ] = useState('');
  const { data, isLoading } = useQuery<Paged<Contact>>({
    queryKey: ['contacts', q],
    queryFn: () => api(`/contacts?q=${encodeURIComponent(q)}`, { token: getToken() }),
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Contacts</h1>
      <div className="mt-4 max-w-sm">
        <Input value={q} onChange={e => setQ(e.target.value)} placeholder="Search…" />
      </div>
      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500">
              <tr><th className="px-4 py-3">MSISDN</th><th className="px-4 py-3">Name</th><th className="px-4 py-3">Email</th><th className="px-4 py-3">Opt-in</th></tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {(data?.data ?? []).map(c => (
                <tr key={c.id}>
                  <td className="px-4 py-3 font-mono">{c.msisdn}</td>
                  <td className="px-4 py-3">{[c.first_name, c.last_name].filter(Boolean).join(' ') || '—'}</td>
                  <td className="px-4 py-3">{c.email ?? '—'}</td>
                  <td className="px-4 py-3">{c.opt_in_status}</td>
                </tr>
              ))}
              {!data?.data?.length && <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-500">No contacts yet.</td></tr>}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
