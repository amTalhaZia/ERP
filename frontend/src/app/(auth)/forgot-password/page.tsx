'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AlertCircle, CheckCircle2, Hexagon } from 'lucide-react';
import { authApi } from '@/features/auth/api';
import { isApiError } from '@/lib/api/errors';
import styles from '../auth.module.scss';

const schema = z.object({ email: z.email('Enter a valid email address') });
type FormValues = z.infer<typeof schema>;

export default function ForgotPasswordPage() {
  const [sent, setSent] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const onSubmit = async ({ email }: FormValues) => {
    setFormError(null);
    try {
      await authApi.forgotPassword(email);
      setSent(true);
    } catch (e) {
      setFormError(isApiError(e) ? e.message : 'Something went wrong.');
    }
  };

  return (
    <div className={styles.card}>
      <div className={styles.logo}>
        <Hexagon size={22} strokeWidth={2.25} aria-hidden /> Enterprise ERP
      </div>
      <h1 className={styles.title}>Reset your password</h1>
      <p className={styles.subtitle}>We&apos;ll email you a secure link to choose a new password.</p>

      {sent ? (
        <div className={`${styles.alert} ${styles.success}`} role="status">
          <CheckCircle2 size={18} aria-hidden />
          <span>If an account exists for that email, a reset link is on its way. It expires in 60 minutes.</span>
        </div>
      ) : (
        <>
          {formError && (
            <div className={styles.alert} role="alert" style={{ marginBottom: 16 }}>
              <AlertCircle size={18} aria-hidden />
              <span>{formError}</span>
            </div>
          )}
          <form className={styles.form} onSubmit={handleSubmit(onSubmit)} noValidate>
            <div className={styles.field}>
              <label htmlFor="email">Email</label>
              <input id="email" type="email" autoComplete="username" className={styles.input} aria-invalid={!!errors.email} {...register('email')} />
              {errors.email && <span className={styles.error}>{errors.email.message}</span>}
            </div>
            <button type="submit" className={styles.submit} disabled={isSubmitting}>
              {isSubmitting && <span className={styles.spinner} aria-hidden />}
              Send reset link
            </button>
          </form>
        </>
      )}

      <p className={styles.footer}>
        <Link href="/login">Back to sign in</Link>
      </p>
    </div>
  );
}
