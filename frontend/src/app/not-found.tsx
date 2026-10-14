import Link from 'next/link';

export default function NotFound() {
  return (
    <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 24, textAlign: 'center' }}>
      <div>
        <p style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-primary-text)', letterSpacing: '0.08em' }}>404</p>
        <h1 style={{ fontSize: 24, fontWeight: 600, margin: '8px 0' }}>Page not found</h1>
        <p style={{ color: 'var(--color-text-secondary)', marginBottom: 20 }}>
          The page you are looking for does not exist or you do not have access to it.
        </p>
        <Link href="/dashboard">Back to dashboard</Link>
      </div>
    </div>
  );
}
