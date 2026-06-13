'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { Suspense, useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { api, setToken } from '@/lib/api';

export default function SignupPage() {
  // useSearchParams must be inside Suspense in Next 15, or the prerender fails.
  return (
    <Suspense fallback={<main className="grid min-h-screen place-items-center"><p className="text-sm text-slate-500">Loading…</p></main>}>
      <SignupForm />
    </Suspense>
  );
}

function SignupForm() {
  const router = useRouter();
  const plan   = useSearchParams().get('plan') ?? 'free';

  const [name,     setName]     = useState('');
  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [team,     setTeam]     = useState('');
  const [error,    setError]    = useState<string | null>(null);
  const [loading,  setLoading]  = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true); setError(null);
    try {
      const res = await api<{ token: string }>('/auth/signup', {
        method: 'POST',
        body: JSON.stringify({ name, email, password, team_name: team || undefined }),
      });
      setToken(res.token);
      router.push('/dashboard');
    } catch (e: any) {
      setError(e.title ?? 'Signup failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="grid min-h-screen place-items-center bg-slate-50 dark:bg-slate-950">
      <Card className="w-full max-w-md">
        <h1 className="text-2xl font-semibold">Start your 14-day trial</h1>
        <p className="mt-1 text-sm text-slate-500">50 free SMS, no credit card. Plan: <strong>{plan}</strong></p>
        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <label className="block">
            <span className="text-sm">Your name</span>
            <Input value={name} onChange={e => setName(e.target.value)} required />
          </label>
          <label className="block">
            <span className="text-sm">Workspace name (optional)</span>
            <Input value={team} onChange={e => setTeam(e.target.value)} placeholder="Acme Inc." />
          </label>
          <label className="block">
            <span className="text-sm">Email</span>
            <Input type="email" value={email} onChange={e => setEmail(e.target.value)} required />
          </label>
          <label className="block">
            <span className="text-sm">Password (10+ chars, letters & numbers)</span>
            <Input type="password" value={password} onChange={e => setPassword(e.target.value)} required />
          </label>
          {error && <p className="text-sm text-red-600">{error}</p>}
          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? 'Creating…' : 'Create workspace'}
          </Button>
        </form>
        <p className="mt-6 text-center text-sm">
          Already have an account? <Link href="/login" className="text-brand-600 hover:underline">Sign in</Link>
        </p>
      </Card>
    </main>
  );
}
