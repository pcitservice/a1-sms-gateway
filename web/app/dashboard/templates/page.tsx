'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Template = { id: number; name: string; body: string; variables: string[] };

export default function TemplatesPage() {
  const qc = useQueryClient();
  const [name, setName] = useState('');
  const [body, setBody] = useState('');

  const { data, isLoading } = useQuery<Template[]>({
    queryKey: ['templates'],
    queryFn: () => api('/templates', { token: getToken() }),
  });

  const create = useMutation({
    mutationFn: () => api('/templates', {
      method: 'POST',
      body: JSON.stringify({ name, body, variables: extractVars(body) }),
      token: getToken(),
    }),
    onSuccess: () => { setName(''); setBody(''); qc.invalidateQueries({ queryKey: ['templates'] }); },
  });

  const remove = useMutation({
    mutationFn: (id: number) => api(`/templates/${id}`, { method: 'DELETE', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['templates'] }),
  });

  return (
    <div className="max-w-3xl">
      <h1 className="text-2xl font-semibold">Templates</h1>
      <p className="mt-1 text-sm text-slate-500">Reusable message bodies with <code>{'{{name}}'}</code> variables.</p>

      <Card className="mt-6">
        <form onSubmit={e => { e.preventDefault(); create.mutate(); }} className="space-y-3">
          <Input value={name} onChange={e => setName(e.target.value)} placeholder="Template name" required />
          <textarea
            value={body} onChange={e => setBody(e.target.value)} rows={3} required maxLength={1530}
            placeholder="Hej {{first_name}}, …"
            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
          />
          <Button type="submit" disabled={create.isPending}>Save template</Button>
        </form>
      </Card>

      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <ul className="divide-y divide-slate-200 dark:divide-slate-800">
            {(data ?? []).map(t => (
              <li key={t.id} className="p-4">
                <div className="flex items-center justify-between">
                  <div className="font-medium">{t.name}</div>
                  <Button variant="danger" onClick={() => remove.mutate(t.id)}>Delete</Button>
                </div>
                <p className="mt-2 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">{t.body}</p>
              </li>
            ))}
            {!data?.length && <li className="p-6 text-sm text-slate-500">No templates yet.</li>}
          </ul>
        )}
      </Card>
    </div>
  );
}

function extractVars(body: string): string[] {
  return Array.from(new Set(Array.from(body.matchAll(/{{\s*(\w+)\s*}}/g)).map(m => m[1])));
}
