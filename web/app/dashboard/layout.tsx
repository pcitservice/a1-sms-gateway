'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api, getToken, setToken } from '@/lib/api';
import { Button } from '@/components/ui/button';

const NAV = [
  { href: '/dashboard',           label: 'Overview' },
  { href: '/dashboard/send',      label: 'Send SMS' },
  { href: '/dashboard/outbox',    label: 'Outbox' },
  { href: '/dashboard/inbox',     label: 'Inbox' },
  { href: '/dashboard/messages',  label: 'Messages' },
  { href: '/dashboard/contacts',  label: 'Contacts' },
  { href: '/dashboard/campaigns',   label: 'Campaigns' },
  { href: '/dashboard/templates',   label: 'Templates' },
  { href: '/dashboard/automations', label: 'Automations' },
  { href: '/dashboard/webhooks',    label: 'Webhooks' },
  { href: '/dashboard/api-keys',    label: 'API keys' },
  { href: '/docs',                  label: 'API docs' },
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

    // Log the user out ONLY on a real 401 (Sanctum says "your token is
    // invalid"). Transient 5xx, timeouts and 429s must NOT sign the user
    // out — those happen every time we redeploy, hit a throttle, or the
    // network hiccups, and used to boot people to /login mid-session.
    async function load(retries = 3) {
      try {
        const r = await api('/auth/me', { token: t });
        setMe(r as any);
      } catch (e: any) {
        if (e?.status === 401) {
          setToken(null);
          router.push('/login');
          return;
        }
        if (retries > 0) {
          await new Promise(r => setTimeout(r, 1500));
          return load(retries - 1);
        }
        // Give up gracefully — keep the token, show a soft error banner.
        setMe({ name: '(offline)', email: '' } as any);
      }
    }
    load();
  }, [router]);

  if (!me) return <p className="p-6 text-sm text-slate-500">Loading…</p>;

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <aside className="w-60 border-r border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
        <Link href="/dashboard" className="block text-lg font-semibold">{process.env.NEXT_PUBLIC_APP_NAME || 'A1TechFlow SMS'}</Link>
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
