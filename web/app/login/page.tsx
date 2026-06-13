'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { api, setToken } from '@/lib/api';

export default function LoginPage() {
  const router = useRouter();
  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [error,    setError]    = useState<string | null>(null);
  const [loading,  setLoading]  = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true); setError(null);
    try {
      const res = await api<{ token: string; user: { is_admin?: boolean } }>(
        '/auth/login',
        { method: 'POST', body: JSON.stringify({ email, password, device_name: 'web' }) },
      );
      setToken(res.token);
      router.push(res.user.is_admin ? '/admin' : '/dashboard');
    } catch (e: any) {
      setError(e.title ?? 'Login failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="grid min-h-screen place-items-center bg-slate-50 dark:bg-slate-950">
      <Card className="w-full max-w-md">
        <h1 className="text-2xl font-semibold">Sign in</h1>
        <p className="mt-1 text-sm text-slate-500">to your A1 SMS Gateway workspace</p>
        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <label className="block">
            <span className="text-sm">Email</span>
            <Input type="email" value={email} onChange={e => setEmail(e.target.value)} required autoFocus />
          </label>
          <label className="block">
            <span className="text-sm">Password</span>
            <Input type="password" value={password} onChange={e => setPassword(e.target.value)} required />
          </label>
          {error && <p className="text-sm text-red-600">{error}</p>}
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
