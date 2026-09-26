'use client';

import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { Suspense, useState } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export default function VerifyEmailPage() {
  return (
    <Suspense fallback={<main className="grid min-h-screen place-items-center"><p className="text-sm text-slate-500">Loading…</p></main>}>
      <VerifyEmailInner />
    </Suspense>
  );
}

function VerifyEmailInner() {
  const email = useSearchParams().get('email') ?? '';
  const [status, setStatus] = useState<'idle' | 'sending' | 'sent' | 'error'>('idle');
  const [err, setErr] = useState<string | null>(null);

  async function resend() {
    if (!email) { setErr('Please open this page from the signup or login flow.'); setStatus('error'); return; }
    setStatus('sending'); setErr(null);
    try {
      await api('/auth/resend-verification', {
        method: 'POST',
        body: JSON.stringify({ email }),
      });
      setStatus('sent');
    } catch (e: any) {
      setErr(e?.message ?? e?.title ?? 'Could not resend right now.');
      setStatus('error');
    }
  }

  return (
    <main className="grid min-h-screen place-items-center bg-slate-50 dark:bg-slate-950">
      <Card className="w-full max-w-md">
        <div className="text-4xl">📧</div>
        <h1 className="mt-2 text-2xl font-semibold">Verify your email</h1>
        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
          We sent a verification link{email ? <> to <strong>{email}</strong></> : ''}. Click it to activate your workspace — you won't be able to sign in until you do.
        </p>
        <p className="mt-3 text-xs text-slate-500">
          Can't find it? Check your spam folder. The link expires in 60 minutes.
        </p>

        <div className="mt-6 flex flex-col gap-2">
          <Button onClick={resend} disabled={status === 'sending' || status === 'sent'} className="w-full">
            {status === 'sending' ? 'Sending…' : status === 'sent' ? 'Sent — check your inbox' : 'Resend verification email'}
          </Button>
          {err && <p className="text-sm text-red-600">{err}</p>}
        </div>

        <p className="mt-6 text-center text-sm">
          <Link href="/login" className="text-brand-600 hover:underline">Back to sign in</Link>
        </p>
      </Card>
    </main>
  );
}
