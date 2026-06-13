'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { api } from '@/lib/api';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [done,  setDone]  = useState(false);
  const [busy,  setBusy]  = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api('/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email }) });
      setDone(true);
    } finally { setBusy(false); }
  }

  return (
    <main className="grid min-h-screen place-items-center bg-slate-50 dark:bg-slate-950">
      <Card className="w-full max-w-md">
        <h1 className="text-2xl font-semibold">Reset password</h1>
        {done ? (
          <p className="mt-4 text-sm text-slate-600 dark:text-slate-300">
            If that email is registered, you'll receive a reset link shortly.
          </p>
        ) : (
          <form onSubmit={onSubmit} className="mt-6 space-y-4">
            <Input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="you@example.com" required />
            <Button type="submit" className="w-full" disabled={busy}>{busy ? 'Sending…' : 'Send reset link'}</Button>
          </form>
        )}
        <p className="mt-6 text-center text-sm">
          <Link href="/login" className="text-brand-600 hover:underline">Back to sign in</Link>
        </p>
      </Card>
    </main>
  );
}
