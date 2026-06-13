'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api, getToken, setToken } from '@/lib/api';
import { Button } from '@/components/ui/button';

const NAV = [
  { href: '/dashboard',           label: 'Overview' },
  { href: '/dashboard/send',      label: 'Send SMS' },
  { href: '/dashboard/inbox',     label: 'Inbox' },
  { href: '/dashboard/contacts',  label: 'Contacts' },
  { href: '/dashboard/campaigns',   label: 'Campaigns' },
  { href: '/dashboard/templates',   label: 'Templates' },
  { href: '/dashboard/automations', label: 'Automations' },
  { href: '/dashboard/webhooks',    label: 'Webhooks' },
  { href: '/dashboard/api-keys',    label: 'API keys' },
  { href: '/dashboard/billing',     label: 'Billing' },
  { href: '/dashboard/settings',    label: 'Settings' },
  { href: '/dashboard/security',    label: 'Security' },
];

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const router  = useRouter();
  const path    = usePathname();
  const [me, setMe] = useState<{ name: string; email: string; current_team?: any } | null>(null);

  useEffect(() => {
    const t = getToken();
    if (!t) { router.push('/login'); return; }
    api('/auth/me', { token: t })
      .then(setMe as any)
      .catch(() => { setToken(null); router.push('/login'); });
  }, [router]);

  if (!me) return <p className="p-6 text-sm text-slate-500">Loading…</p>;

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <aside className="w-60 border-r border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
        <Link href="/dashboard" className="block text-lg font-semibold">A1 SMS</Link>
        <nav className="mt-6 space-y-1 text-sm">
          {NAV.map(item => (
            <Link
              key={item.href}
              href={item.href}
              className={`block rounded-md px-3 py-2 transition ${
                path === item.href
                  ? 'bg-brand-50 text-brand-700 dark:bg-brand-700/20 dark:text-brand-100'
                  : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
              }`}
            >
              {item.label}
            </Link>
          ))}
        </nav>
        <div className="mt-10 text-xs text-slate-500">
          <div className="font-medium text-slate-700 dark:text-slate-300">{me.name}</div>
          <div>{me.email}</div>
          <Button
            variant="ghost"
            className="mt-3 w-full justify-start px-3"
            onClick={async () => { try { await api('/auth/logout', { method: 'POST', token: getToken() }); } finally { setToken(null); router.push('/login'); } }}
          >
            Sign out
          </Button>
        </div>
      </aside>
      <main className="flex-1 p-8">{children}</main>
    </div>
  );
}
