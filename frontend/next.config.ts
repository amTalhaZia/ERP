import path from 'node:path';
import type { NextConfig } from 'next';

/**
 * Hosted demo (Vercel): set BACKEND_URL (e.g. https://erp-api.onrender.com) and leave
 * NEXT_PUBLIC_API_URL empty. The API, Sanctum and file URLs are then proxied through this
 * site, so the login cookie stays first-party (no cross-site cookie problems).
 */
const backend = process.env.BACKEND_URL?.replace(/\/$/, '');

const nextConfig: NextConfig = {
  // With the proxy on, the browser calls this same site (empty API base) — no separate setting needed.
  ...(backend ? { env: { NEXT_PUBLIC_API_URL: '' } } : {}),
  reactStrictMode: true,
  poweredByHeader: false,
  turbopack: { root: process.cwd() },
  // `@use 'tokens' as *;` resolves from src/styles in every SCSS module
  // (Turbopack's sass importer does not understand the `@/` TS alias).
  sassOptions: {
    loadPaths: [path.join(process.cwd(), 'src/styles')],
  },
  images: {
    remotePatterns: [
      { protocol: 'http', hostname: 'localhost', port: '8000', pathname: '/storage/**' },
    ],
  },
  async rewrites() {
    if (!backend) return [];
    return [
      { source: '/api/:path*', destination: `${backend}/api/:path*` },
      { source: '/sanctum/:path*', destination: `${backend}/sanctum/:path*` },
      { source: '/storage/:path*', destination: `${backend}/storage/:path*` },
    ];
  },
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          { key: 'X-Frame-Options', value: 'DENY' },
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=(self)' },
        ],
      },
    ];
  },
};

export default nextConfig;
