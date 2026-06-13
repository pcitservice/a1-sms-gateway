import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  output: 'standalone',
  reactStrictMode: true,
  poweredByHeader: false,
  // Stylistic lint failures shouldn't block production builds; we still run
  // `npm run lint` separately in CI for visibility.
  eslint: { ignoreDuringBuilds: true },
  // Type-checking still happens in CI via `npm run type-check`.
  typescript: { ignoreBuildErrors: false },
  experimental: {
    optimizePackageImports: ['lucide-react', 'recharts'],
  },
  async rewrites() {
    return [
      // Route /api/v1/* through the Laravel backend in dev. In prod Nginx does this.
      {
        source: '/api/v1/:path*',
        destination: (process.env.NEXT_PUBLIC_API_URL || 'http://api:8000/api/v1') + '/:path*',
      },
    ];
  },
};

export default nextConfig;
