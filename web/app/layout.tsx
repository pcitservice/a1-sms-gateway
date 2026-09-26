import './globals.css';
import type { Metadata } from 'next';
import { Providers } from './providers';

export const metadata: Metadata = {
  title: {
    default: process.env.NEXT_PUBLIC_APP_NAME || 'A1TechFlow SMS',
    template: `%s · ${process.env.NEXT_PUBLIC_APP_NAME || 'A1TechFlow SMS'}`,
  },
  description: 'Send and receive SMS in Denmark. Simple REST API, pay-as-you-go, no subscription.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="min-h-screen antialiased">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
