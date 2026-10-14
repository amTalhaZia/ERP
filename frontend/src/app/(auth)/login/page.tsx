'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AlertCircle, Eye, EyeOff, Hexagon } from 'lucide-react';
import { authApi } from '@/features/auth/api';
import { useSession } from '@/lib/auth/SessionProvider';
import { isApiError } from '@/lib/api/errors';
import styles from '../auth.module.scss';

const schema = z.object({
  email: z.email('Enter a valid email address'),
  password: z.string().min(1, 'Enter your password'),
  remember: z.boolean().optional(),
});
type FormValues = z.infer<typeof schema>;

function LoginPageInner() {
  const router = useRouter();
  const params = useSearchParams();
  const { applySession } = useSession();
  const [showPassword, setShowPassword] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { remember: true } });

  const onSubmit = async (values: FormValues) => {
    setFormError(null);
    try {
      const res = await authApi.login(values);
      if (res.two_factor) {
        router.replace(`/two-factor?next=${encodeURIComponent(params.get('next') ?? '/dashboard')}`);
        return;
      }
      if (res.session) {
        applySession(res.session);
        router.replace(params.get('next') ?? '/dashboard');
      }
    } catch (e) {
      if (isApiError(e)) {
        if (e.status === 422 && e.errors) {
          Object.entries(e.errors).forEach(([field, msgs]) => {
            if (field === 'email' || field === 'password') setError(field, { message: msgs[0] });
          });
          if (!e.errors.email && !e.errors.password) setFormError(e.message);
        } else {
          setFormError(e.message);
        }
      } else {
        setFormError('Something went wrong. Please try again.');
      }
    }
  };

  return (
    <div className={styles.card}>
      <div className={styles.logo}>
        <Hexagon size={22} strokeWidth={2.25} aria-hidden />
        Enterprise ERP
      </div>
      <h1 className={styles.title}>Sign in</h1>
      <p className={styles.subtitle}>Use your work email to access your companies.</p>

      {formError && (
        <div className={styles.alert} role="alert" style={{ marginBottom: 16 }}>
          <AlertCircle size={18} aria-hidden />
          <span>{formError}</span>
        </div>
      )}

      <form className={styles.form} onSubmit={handleSubmit(onSubmit)} noValidate>
        <div className={styles.field}>
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            autoComplete="username"
            className={styles.input}
            placeholder="you@company.com"
            aria-invalid={!!errors.email}
            aria-describedby={errors.email ? 'email-error' : undefined}
            {...register('email')}
          />
          {errors.email && (
            <span id="email-error" className={styles.error}>
              {errors.email.message}
            </span>
          )}
        </div>

        <div className={styles.field}>
          <label htmlFor="password">Password</label>
          <div className={styles.inputWrap}>
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              className={styles.input}
              aria-invalid={!!errors.password}
              aria-describedby={errors.password ? 'password-error' : undefined}
              {...register('password')}
            />
            <button type="button" className={styles.eye} onClick={() => setShowPassword((s) => !s)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
          {errors.password && (
            <span id="password-error" className={styles.error}>
              {errors.password.message}
            </span>
          )}
        </div>

        <div className={styles.row}>
          <label>
            <input type="checkbox" {...register('remember')} />
            Keep me signed in
          </label>
          <Link href="/forgot-password">Forgot password?</Link>
        </div>

        <button type="submit" className={styles.submit} disabled={isSubmitting}>
          {isSubmitting && <span className={styles.spinner} aria-hidden />}
          {isSubmitting ? 'Signing in…' : 'Sign in'}
        </button>
      </form>

      <p className={styles.footer}>Access is by invitation. Contact your company administrator if you need an account.</p>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense fallback={null}>
      <LoginPageInner />
    </Suspense>
  );
}
