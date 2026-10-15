'use client';

import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AlertCircle, Hexagon, KeyRound } from 'lucide-react';
import { authApi } from '@/features/auth/api';
import { useSession } from '@/lib/auth/SessionProvider';
import { isApiError } from '@/lib/api/errors';
import { homePathFor } from '@/lib/auth/home';
import styles from '../auth.module.scss';

const schema = z.object({ code: z.string().min(6, 'Enter the 6-digit code or a recovery code') });
type FormValues = z.infer<typeof schema>;

function TwoFactorPageInner() {
  const router = useRouter();
  const params = useSearchParams();
  const { applySession } = useSession();
  const [recovery, setRecovery] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const onSubmit = async ({ code }: FormValues) => {
    setFormError(null);
    try {
      const res = await authApi.twoFactorChallenge(code.trim(), recovery);
      applySession(res.session);
      router.replace(params.get('next') ?? homePathFor(res.session));
    } catch (e) {
      setFormError(isApiError(e) ? (e.errors?.code?.[0] ?? e.message) : 'Something went wrong.');
    }
  };

  return (
    <div className={styles.card}>
      <div className={styles.logo}>
        <Hexagon size={22} strokeWidth={2.25} aria-hidden /> Enterprise ERP
      </div>
      <h1 className={styles.title}>Two-factor verification</h1>
      <p className={styles.subtitle}>
        {recovery ? 'Enter one of your recovery codes.' : 'Enter the 6-digit code from your authenticator app.'}
      </p>

      {formError && (
        <div className={styles.alert} role="alert" style={{ marginBottom: 16 }}>
          <AlertCircle size={18} aria-hidden />
          <span>{formError}</span>
        </div>
      )}

      <form className={styles.form} onSubmit={handleSubmit(onSubmit)} noValidate>
        <div className={styles.field}>
          <label htmlFor="code">{recovery ? 'Recovery code' : 'Authentication code'}</label>
          <input
            id="code"
            inputMode={recovery ? 'text' : 'numeric'}
            autoComplete="one-time-code"
            autoFocus
            maxLength={recovery ? 11 : 6}
            className={`${styles.input} ${recovery ? '' : styles.code}`}
            aria-invalid={!!errors.code}
            {...register('code')}
          />
          {errors.code && <span className={styles.error}>{errors.code.message}</span>}
        </div>
        <button type="submit" className={styles.submit} disabled={isSubmitting}>
          {isSubmitting && <span className={styles.spinner} aria-hidden />}
          Verify
        </button>
      </form>

      <p className={styles.footer}>
        <button type="button" onClick={() => setRecovery((r) => !r)} style={{ color: 'var(--color-primary-text)', display: 'inline-flex', gap: 6, alignItems: 'center' }}>
          <KeyRound size={14} aria-hidden /> {recovery ? 'Use authenticator code' : 'Use a recovery code instead'}
        </button>
      </p>
    </div>
  );
}

export default function TwoFactorPage() {
  return (
    <Suspense fallback={null}>
      <TwoFactorPageInner />
    </Suspense>
  );
}
