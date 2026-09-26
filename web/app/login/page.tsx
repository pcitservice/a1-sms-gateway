'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { Suspense, useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { api, setToken } from '@/lib/api';

export default function LoginPage() {
  return (
    <Suspense fallback={<main className="grid min-h-screen place-items-center"><p className="text-sm text-slate-500">Loading…</p></main>}>
      <LoginForm />
    </Suspense>
  );
}

function LoginForm() {
  const router = useRouter();
  const params = useSearchParams();
  const justVerified = params.get('verified') === '1';

  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [error,    setError]    = useState<string | null>(null);
  const [unverified, setUnverified] = useState<string | null>(null);
  const [loading,  setLoading]  = useState(false);
  const [resending, setResending] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true); setError(null); setUnverified(null);
    try {
      const res = await api<{ token: string; user: { is_admin?: boolean } }>(
        '/auth/login',
        { method: 'POST', body: JSON.stringify({ email, password, device_name: 'web' }) },
      );
      setToken(res.token);
      router.push(res.user.is_admin ? '/admin' : '/dashboard');
    } catch (e: any) {
      if (e?.verification_required) {
        setUnverified(e.email ?? email);
      } else {
        setError(e?.message ?? e?.detail ?? e?.title ?? 'Login failed');
      }
    } finally {
      setLoading(false);
    }
  }

  async function resend() {
    if (!unverified) return;
    setResending(true);
    try {
      await api('/auth/resend-verification', { method: 'POST', body: JSON.stringify({ email: unverified }) });
      setError(null);
    } catch { /* silent */ } finally {
      setResending(false);
    }
  }

  return (
    <main className="grid min-h-screen place-items-center bg-slate-50 dark:bg-slate-950">
      <Card className="w-full max-w-md">
        <h1 className="text-2xl font-semibold">Sign in</h1>
        <p className="mt-1 text-sm text-slate-500">to your {process.env.NEXT_PUBLIC_APP_NAME || 'A1TechFlow SMS'} workspace</p>

        {justVerified && (
          <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200">
            ✅ Email verified. Sign in to continue.
          </div>
        )}

        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <label className="block">
            <span className="text-sm">Email</span>
            <Input type="email" value={email} onChange={e => setEmail(e.target.value)} required autoFocus />
          </label>
          <label className="block">
            <span className="text-sm">Password</span>
            <Input type="password" value={password} onChange={e => setPassword(e.target.value)} required />
          </label>

          {unverified && (
            <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
              Your email <strong>{unverified}</strong> is not verified yet. Check your inbox for the link we sent.
              <button type="button" onClick={resend} disabled={resending} className="mt-2 block text-xs font-medium underline underline-offset-2">
                {resending ? 'Sending…' : 'Resend verification email'}
              </button>
            </div>
          )}

          {error && !unverified && <p className="text-sm text-red-600">{error}</p>}

          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>
        <div className="mt-6 flex justify-between text-sm">
          <Link href="/forgot-password" className="text-brand-600 hover:underline">Forgot password?</Link>
          <Link href="/signup" className="text-brand-600 hover:underline">Create account</Link>
        </div>
      </Card>
    </main>
  );
}
