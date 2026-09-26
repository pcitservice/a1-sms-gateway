import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

const APP_NAME = process.env.NEXT_PUBLIC_APP_NAME || 'A1TechFlow SMS';

const plans = [
  { slug: 'free',   name: 'Free Trial',    price: '0',  sms: '25',  blurb: 'Try it, no card needed.' },
  { slug: 'topup',  name: 'Pay-as-you-go', price: '99', sms: '300', blurb: 'Refills anytime, no subscription.', highlight: true },
];

export default function Landing() {
  return (
    <main className="min-h-screen bg-gradient-to-b from-white to-brand-50 dark:from-slate-950 dark:to-slate-900">
      <header className="mx-auto flex max-w-7xl items-center justify-between p-6">
        <Link href="/" className="text-lg font-semibold">{APP_NAME}</Link>
        <nav className="flex items-center gap-3">
          <Link href="/docs"><Button variant="ghost">API docs</Button></Link>
          <Link href="/login"><Button variant="ghost">Sign in</Button></Link>
          <Link href="/signup"><Button>Start 14-day trial</Button></Link>
        </nav>
      </header>

      <section className="mx-auto max-w-4xl px-6 py-24 text-center">
        <h1 className="text-5xl font-bold leading-tight tracking-tight">
          Two-way SMS for your Danish business.
        </h1>
        <p className="mt-6 text-lg text-slate-600 dark:text-slate-300">
          Send appointment reminders, order updates, marketing blasts — and receive
          replies straight to your inbox. Simple REST API, live delivery reports,
          pay only for what you send.
        </p>
        <div className="mt-10 flex justify-center gap-3">
          <Link href="/signup"><Button className="px-6 py-3">Start free — 25 SMS</Button></Link>
          <Link href="/docs"><Button variant="secondary" className="px-6 py-3">API docs</Button></Link>
        </div>
        <p className="mt-4 text-xs text-slate-500">No credit card. Denmark only for now.</p>
      </section>

      <section className="mx-auto max-w-3xl px-6 pb-24">
        <div className="grid gap-6 md:grid-cols-2">
          {plans.map(p => (
            <Card key={p.slug} className={p.highlight ? 'ring-2 ring-brand-500' : ''}>
              <h3 className="text-lg font-semibold">{p.name}</h3>
              <div className="mt-2 text-3xl font-bold">
                {p.price}
                <span className="text-base font-normal text-slate-500"> DKK</span>
              </div>
              <p className="mt-1 text-sm text-slate-500">
                {p.sms} SMS · {p.slug === 'topup' ? 'one-off, no auto-renew' : '14-day trial'}
              </p>
              <p className="mt-4 text-sm">{p.blurb}</p>
              <Link href="/signup" className="mt-6 block">
                <Button variant={p.highlight ? 'primary' : 'secondary'} className="w-full">
                  {p.slug === 'topup' ? 'Sign up and top up' : 'Start free trial'}
                </Button>
              </Link>
            </Card>
          ))}
        </div>
      </section>

      <footer className="border-t border-slate-200 px-6 py-8 text-center text-sm text-slate-500 dark:border-slate-800">
        © {new Date().getFullYear()} A1TechFlow · sms.a1techflow.com
      </footer>
    </main>
  );
}
