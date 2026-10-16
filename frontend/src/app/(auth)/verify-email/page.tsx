'use client';

import { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import axios from 'axios';
import { CheckCircle2, AlertCircle, Hexagon } from 'lucide-react';
import { API_BASE } from '@/lib/api/client';
import styles from '../auth.module.scss';

function VerifyEmailInner() {
  const params = useSearchParams();
  const url = params.get('url');
  const [state, setState] = useState<'pending' | 'ok' | 'error'>(url ? 'pending' : 'error');

  useEffect(() => {
    if (!url) return;
    // Only follow signed links that point at our own API (same origin as API_BASE, or this
    // site when the API is proxied through it), never an arbitrary URL from the query string.
    let ours = false;
    try {
      const target = new URL(url, window.location.origin);
      const api = new URL(API_BASE || window.location.origin, window.location.origin);
      ours = target.origin === api.origin && target.pathname.startsWith('/api/');
    } catch {
      ours = false;
    }
    if (!ours) {
      queueMicrotask(() => setState('error'));
      return;
    }
    axios
      .get(url, { withCredentials: true, headers: { Accept: 'application/json' } })
      .then(() => setState('ok'))
      .catch(() => setState('error'));
  }, [url]);

  return (
    <div className={styles.card}>
      <div className={styles.logo}>
        <Hexagon size={22} strokeWidth={2.25} aria-hidden /> Enterprise ERP
      </div>
      <h1 className={styles.title}>Email verification</h1>
      <div style={{ marginTop: 16 }}>
        {state === 'pending' && <p className={styles.subtitle}>Verifying your email address…</p>}
        {state === 'ok' && (
          <div className={`${styles.alert} ${styles.success}`} role="status">
            <CheckCircle2 size={18} aria-hidden /> <span>Your email address is verified.</span>
          </div>
        )}
        {state === 'error' && (
          <div className={styles.alert} role="alert">
            <AlertCircle size={18} aria-hidden /> <span>This verification link is invalid or has expired. Request a new one from your profile.</span>
          </div>
        )}
      </div>
      <p className={styles.footer}>
        <Link href="/dashboard">Continue to the app</Link>
      </p>
    </div>
  );
}

export default function VerifyEmailPage() {
  return (
    <Suspense fallback={null}>
      <VerifyEmailInner />
    </Suspense>
  );
}
