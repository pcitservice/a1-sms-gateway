'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { api, getToken } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type User = { id: number; name: string; email: string; is_admin: boolean; suspended_at: string | null };

export default function AdminUsers() {
  const qc = useQueryClient();
  const [q, setQ] = useState('');
  const { data, isLoading } = useQuery<{ data: User[] }>({
    queryKey: ['admin-users', q],
    queryFn: () => api(`/admin/users?q=${encodeURIComponent(q)}`, { token: getToken() }),
  });
  const toggle = useMutation({
    mutationFn: ({ id, suspend }: { id: number; suspend: boolean }) =>
      api(`/admin/users/${id}/${suspend ? 'suspend' : 'activate'}`, { method: 'POST', token: getToken() }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-users'] }),
  });

  return (
    <div>
      <h1 className="text-2xl font-semibold">Users</h1>
      <div className="mt-4 max-w-sm"><Input value={q} onChange={e => setQ(e.target.value)} placeholder="Search…" /></div>
      <Card className="mt-6 p-0">
        {isLoading ? (
          <div className="p-6 text-sm text-slate-500">Loading…</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="text-left text-slate-500">
              <tr><th className="px-4 py-3">Name</th><th className="px-4 py-3">Email</th><th className="px-4 py-3">Role</th><th className="px-4 py-3">Status</th><th className="px-4 py-3"></th></tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
              {(data?.data ?? []).map(u => (
                <tr key={u.id}>
                  <td className="px-4 py-3">{u.name}</td>
                  <td className="px-4 py-3">{u.email}</td>
                  <td className="px-4 py-3">{u.is_admin ? 'admin' : 'user'}</td>
                  <td className="px-4 py-3">{u.suspended_at ? 'suspended' : 'active'}</td>
                  <td className="px-4 py-3 text-right">
                    <Button
                      variant={u.suspended_at ? 'primary' : 'danger'}
                      onClick={() => toggle.mutate({ id: u.id, suspend: !u.suspended_at })}
                    >
                      {u.suspended_at ? 'Activate' : 'Suspend'}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
