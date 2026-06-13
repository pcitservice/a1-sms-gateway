import './globals.css';
import type { Metadata } from 'next';
import { Providers } from './providers';

export const metadata: Metadata = {
  title: { default: 'A1 SMS Gateway', template: '%s · A1 SMS Gateway' },
  description: 'Send and receive SMS at scale through Teltonika TRB140 gateways.',
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
