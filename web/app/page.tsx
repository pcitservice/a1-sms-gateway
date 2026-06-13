import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

const plans = [
  { slug: 'free',     name: 'Free Trial', price: '0',   sms: '50',     blurb: '14 days, all features.' },
  { slug: 'starter',  name: 'Starter',    price: '99',  sms: '500',    blurb: 'For freelancers & side projects.' },
  { slug: 'business', name: 'Business',   price: '299', sms: '3 000',  blurb: 'For small businesses.', highlight: true },
  { slug: 'pro',      name: 'Pro',        price: '999', sms: '15 000', blurb: 'For high-volume senders.' },
];

export default function Landing() {
  return (
    <main className="min-h-screen bg-gradient-to-b from-white to-brand-50 dark:from-slate-950 dark:to-slate-900">
      <header className="mx-auto flex max-w-7xl items-center justify-between p-6">
        <Link href="/" className="text-lg font-semibold">A1 SMS Gateway</Link>
        <nav className="flex items-center gap-3">
          <Link href="/login"><Button variant="ghost">Sign in</Button></Link>
          <Link href="/signup"><Button>Start 14-day trial</Button></Link>
        </nav>
      </header>

      <section className="mx-auto max-w-4xl px-6 py-24 text-center">
        <h1 className="text-5xl font-bold leading-tight tracking-tight">
          Send and receive SMS through your own gateway.
        </h1>
        <p className="mt-6 text-lg text-slate-600 dark:text-slate-300">
          A1 SMS Gateway gives you a Stripe-billed, multi-tenant SaaS platform sitting in
          front of a Teltonika TRB140 — or any LTE modem. Real two-way SMS, real
          delivery tracking, real automation, real webhooks. Run it on your VPS in one
          command.
        </p>
        <div className="mt-10 flex justify-center gap-3">
          <Link href="/signup"><Button className="px-6 py-3">Start free</Button></Link>
          <Link href="/api/documentation"><Button variant="secondary" className="px-6 py-3">API docs</Button></Link>
        </div>
      </section>

      <section className="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-6 pb-24 md:grid-cols-4">
        {plans.map(p => (
          <Card key={p.slug} className={p.highlight ? 'ring-2 ring-brand-500' : ''}>
            <h3 className="text-lg font-semibold">{p.name}</h3>
            <div className="mt-2 text-3xl font-bold">{p.price}<span className="text-base font-normal text-slate-500"> DKK/mo</span></div>
            <p className="mt-1 text-sm text-slate-500">{p.sms} SMS included</p>
            <p className="mt-4 text-sm">{p.blurb}</p>
            <Link href={`/signup?plan=${p.slug}`} className="mt-6 block">
              <Button variant={p.highlight ? 'primary' : 'secondary'} className="w-full">Choose {p.name}</Button>
            </Link>
          </Card>
        ))}
      </section>

      <footer className="border-t border-slate-200 px-6 py-8 text-center text-sm text-slate-500 dark:border-slate-800">
        © {new Date().getFullYear()} A1 Tech Flow · sms.a1techflow.com
      </footer>
    </main>
  );
}
