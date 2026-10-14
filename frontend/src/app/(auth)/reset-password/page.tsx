'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AlertCircle, Hexagon } from 'lucide-react';
import { authApi } from '@/features/auth/api';
import { isApiError } from '@/lib/api/errors';
import styles from '../auth.module.scss';

const schema = z
  .object({
    password: z.string().min(10, 'At least 10 characters').regex(/[A-Za-z]/, 'Include a letter').regex(/\d/, 'Include a number'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, { message: 'Passwords do not match', path: ['password_confirmation'] });
type FormValues = z.infer<typeof schema>;

function ResetPasswordPageInner() {
  const params = useSearchParams();
  const router = useRouter();
  const token = params.get('token') ?? '';
  const email = params.get('email') ?? '';
  const [formError, setFormError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const onSubmit = async (values: FormValues) => {
    setFormError(null);
    try {
      await authApi.resetPassword({ token, email, ...values });
      router.replace('/login?reset=1');
    } catch (e) {
      if (isApiError(e) && e.errors) {
        if (e.errors.password) setError('password', { message: e.errors.password[0] });
        if (e.errors.email) setFormError(e.errors.email[0]);
      } else {
        setFormError(isApiError(e) ? e.message : 'Something went wrong.');
      }
    }
  };

  if (!token || !email) {
    return (
      <div className={styles.card}>
        <h1 className={styles.title}>Invalid link</h1>
        <p className={styles.subtitle}>This password reset link is missing information. Request a new one.</p>
        <p className={styles.footer}>
          <Link href="/forgot-password">Request a new link</Link>
        </p>
      </div>
    );
  }

  return (
    <div className={styles.card}>
      <div className={styles.logo}>
        <Hexagon size={22} strokeWidth={2.25} aria-hidden /> Enterprise ERP
      </div>
      <h1 className={styles.title}>Choose a new password</h1>
      <p className={styles.subtitle}>For {email}. Other active sessions will be signed out.</p>

      {formError && (
        <div className={styles.alert} role="alert" style={{ marginBottom: 16 }}>
          <AlertCircle size={18} aria-hidden />
          <span>{formError}</span>
        </div>
      )}

      <form className={styles.form} onSubmit={handleSubmit(onSubmit)} noValidate>
        <div className={styles.field}>
          <label htmlFor="password">New password</label>
          <input id="password" type="password" autoComplete="new-password" className={styles.input} aria-invalid={!!errors.password} {...register('password')} />
          {errors.password && <span className={styles.error}>{errors.password.message}</span>}
        </div>
        <div className={styles.field}>
          <label htmlFor="password_confirmation">Confirm password</label>
          <input id="password_confirmation" type="password" autoComplete="new-password" className={styles.input} aria-invalid={!!errors.password_confirmation} {...register('password_confirmation')} />
          {errors.password_confirmation && <span className={styles.error}>{errors.password_confirmation.message}</span>}
        </div>
        <button type="submit" className={styles.submit} disabled={isSubmitting}>
          {isSubmitting && <span className={styles.spinner} aria-hidden />}
          Update password
        </button>
      </form>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={null}>
      <ResetPasswordPageInner />
    </Suspense>
  );
}
