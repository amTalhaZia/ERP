import { NextResponse, type NextRequest } from 'next/server';

/**
 * Forwards the visitor's approximate location (from Vercel's edge headers) to the
 * Laravel API on proxied /api and /sanctum calls, for the audit log's "Location"
 * column. The backend only trusts these headers when they carry GEO_HEADER_SECRET,
 * and any X-ERP-Geo-* headers sent by the browser are removed first.
 * Locally (no secret / no Vercel headers) nothing is added.
 */
export function proxy(request: NextRequest) {
  const headers = new Headers(request.headers);
  for (const name of ['x-erp-geo-secret', 'x-erp-geo-country', 'x-erp-geo-city']) headers.delete(name);

  const secret = process.env.GEO_HEADER_SECRET;
  const country = request.headers.get('x-vercel-ip-country');
  if (secret && country) {
    headers.set('x-erp-geo-secret', secret);
    headers.set('x-erp-geo-country', country);
    const city = request.headers.get('x-vercel-ip-city'); // already URL-encoded by Vercel
    if (city) headers.set('x-erp-geo-city', city);
  }

  return NextResponse.next({ request: { headers } });
}

export const config = {
  matcher: ['/api/:path*', '/sanctum/:path*'],
};
