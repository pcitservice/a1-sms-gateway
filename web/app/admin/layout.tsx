'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api, getToken, setToken } from '@/lib/api';

const NAV = [
  { href: '/admin',           label: 'Overview' },
  { href: '/admin/users',     label: 'Users' },
  { href: '/admin/gateways',  label: 'Gateways' },
  { href: '/admin/financial', label: 'Financial' },
  { href: '/admin/system',    label: 'System' },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const path   = usePathname();
  const [me, setMe] = useState<{ is_admin: boolean } | null>(null);

  useEffect(() => {
    const t = getToken();
    if (!t) { router.push('/login'); return; }
    api<{ is_admin: boolean }>('/auth/me', { token: t })
      .then(r => { if (!r.is_admin) { router.push('/dashboard'); } else setMe(r); })
      .catch(() => { setToken(null); router.push('/login'); });
  }, [router]);

  if (!me) return <p className="p-6 text-sm text-slate-500">Loading…</p>;

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <aside className="w-60 border-r border-slate-200 bg-slate-900 p-4 text-slate-100 dark:bg-slate-950">
        <Link href="/admin" className="block text-lg font-semibold">A1 SMS · Admin</Link>
        <nav className="mt-6 space-y-1 text-sm">
          {NAV.map(item => (
            <Link
              key={item.href}
              href={item.href}
              className={`block rounded-md px-3 py-2 ${path === item.href ? 'bg-brand-700/50 text-white' : 'text-slate-300 hover:bg-slate-800'}`}
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </aside>
      <main className="flex-1 p-8">{children}</main>
    </div>
  );
}
