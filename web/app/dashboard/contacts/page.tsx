'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Contact = {
  id: number;
  msisdn: string;
  first_name?: string;
  last_name?: string;
  email?: string;
  opt_in_status: string;
};
type Paged<T> = { data: T[]; total: number; current_page: number; last_page: number };

export default function ContactsPage() {
  const qc = useQueryClient();
  const [q, setQ] = useState('');
  const [addOpen, setAddOpen] = useState(false);
  const [importing, setImporting] = useState(false);
  const [flash, setFlash] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const { data, isLoading } = useQuery<Paged<Contact>>({
    queryKey: ['contacts', q],
    queryFn: () => api(`/contacts?q=${encodeURIComponent(q)}`, { token: getToken() }),
  });

  const del = useMutation({
    mutationFn: (id: number) => api(`/contacts/${id}`, { method: 'DELETE', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['contacts'] }),
  });

  async function onImport(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setImporting(true); setFlash(null);
    try {
      const form = new FormData();
      form.append('file', file);
      const res = await fetch('/api/v1/contacts/import', {
        method: 'POST',
        headers: { Authorization: `Bearer ${getToken()}`, Accept: 'application/json' },
        body: form,
      });
      const body = await res.json();
      if (!res.ok) throw body;
      setFlash(`Imported ${body.imported} contact${body.imported === 1 ? '' : 's'}.`);
      qc.invalidateQueries({ queryKey: ['contacts'] });
    } catch (err: any) {
      setFlash(err?.message ?? 'Import failed. CSV must have an "msisdn" header column.');
    } finally {
      setImporting(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  }

  function downloadExport() {
    fetch('/api/v1/contacts/export', {
      headers: { Authorization: `Bearer ${getToken()}` },
    }).then(r => r.blob()).then(blob => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'contacts.csv';
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Contacts</h1>
          <p className="mt-1 text-sm text-slate-500">Your address book — reuse in sends, campaigns and automations.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button onClick={() => setAddOpen(true)}>+ Add contact</Button>
          <Button variant="secondary" onClick={() => fileRef.current?.click()} disabled={importing}>
            {importing ? 'Importing…' : 'Import CSV'}
          </Button>
          <input ref={fileRef} type="file" accept=".csv,.txt,.xlsx,.xls" className="hidden" onChange={onImport} />
          <Button variant="ghost" onClick={downloadExport}>Export CSV</Button>
        </div>
      </div>

      {flash && (
        <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200">
          {flash}
        </div>
      )}

      <div className="mt-4 max-w-sm">
        <Input value={q} onChange={e => setQ(e.target.value)} placeholder="Search by number, name or email…" />
      </div>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500 border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="px-4 py-3">Number</th>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Email</th>
                <th className="px-4 py-3">Opt-in</th>
                <th className="px-4 py-3 w-24 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {(data?.data ?? []).map(c => (
                <tr key={c.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td className="px-4 py-3 font-mono">{c.msisdn}</td>
                  <td className="px-4 py-3">{[c.first_name, c.last_name].filter(Boolean).join(' ') || '—'}</td>
                  <td className="px-4 py-3">{c.email ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={
                      c.opt_in_status === 'opted_in'  ? 'text-emerald-600' :
                      c.opt_in_status === 'opted_out' ? 'text-red-600'     :
                      'text-slate-500'
                    }>{c.opt_in_status}</span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      className="text-xs text-red-600 hover:underline disabled:opacity-40"
                      disabled={del.isPending}
                      onClick={() => { if (confirm(`Delete ${c.msisdn}?`)) del.mutate(c.id); }}
                    >Delete</button>
                  </td>
                </tr>
              ))}
              {!data?.data?.length && (
                <tr><td colSpan={5} className="px-4 py-10 text-center text-slate-500">
                  No contacts yet. Add one above, or import a CSV with an <code>msisdn</code> column.
                </td></tr>
              )}
            </tbody>
          </table>
        )}
      </Card>

      {data && data.total > 0 && (
        <p className="mt-3 text-xs text-slate-500">
          {data.total} contact{data.total === 1 ? '' : 's'} · page {data.current_page} of {data.last_page}
        </p>
      )}

      {addOpen && <AddContactModal onClose={() => setAddOpen(false)} />}
    </div>
  );
}

function AddContactModal({ onClose }: { onClose: () => void }) {
  const qc = useQueryClient();
  const [msisdn,    setMsisdn]    = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName,  setLastName]  = useState('');
  const [email,     setEmail]     = useState('');
  const [err,       setErr]       = useState<string | null>(null);

  const create = useMutation({
    mutationFn: () =>
      api('/contacts', {
        method: 'POST',
        body: JSON.stringify({
          msisdn,
          first_name: firstName || undefined,
          last_name:  lastName  || undefined,
          email:      email     || undefined,
        }),
        token: getToken(),
      }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['contacts'] }); onClose(); },
    onError: (e: any) => setErr(Object.values(e?.errors ?? {}).flat()[0] as string ?? e?.message ?? 'Failed'),
  });

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4" onClick={onClose}>
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900" onClick={e => e.stopPropagation()}>
        <h2 className="text-xl font-semibold">Add contact</h2>
        <form
          onSubmit={e => { e.preventDefault(); setErr(null); create.mutate(); }}
          className="mt-4 space-y-3"
        >
          <label className="block">
            <span className="text-sm">Phone (E.164)</span>
            <Input value={msisdn} onChange={e => setMsisdn(e.target.value)} placeholder="+4531139345" required />
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-sm">First name</span>
              <Input value={firstName} onChange={e => setFirstName(e.target.value)} />
            </label>
            <label className="block">
              <span className="text-sm">Last name</span>
              <Input value={lastName} onChange={e => setLastName(e.target.value)} />
            </label>
          </div>
          <label className="block">
            <span className="text-sm">Email (optional)</span>
            <Input type="email" value={email} onChange={e => setEmail(e.target.value)} />
          </label>
          {err && <p className="text-sm text-red-600">{err}</p>}
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" type="button" onClick={onClose}>Cancel</Button>
            <Button type="submit" disabled={create.isPending}>
              {create.isPending ? 'Saving…' : 'Add contact'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
