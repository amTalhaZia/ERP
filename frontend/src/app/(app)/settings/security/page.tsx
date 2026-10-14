'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import { KeyRound, ShieldCheck, ShieldOff, Smartphone } from 'lucide-react';
import { Alert, Badge, Button, Card, CardBody, CardHeader, FormField, FormGrid, Input, Modal, PageHeader, useToast } from '@/components/ui';
import { authApi } from '@/features/auth/api';
import { useSession } from '@/lib/auth/SessionProvider';
import { applyServerErrors } from '@/lib/crud/resource';
import { isApiError, type ApiError } from '@/lib/api/errors';
import { formatDateTime } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';

const pwSchema = z
  .object({
    current_password: z.string().min(1, 'Enter your current password'),
    password: z.string().min(10, 'At least 10 characters').regex(/[A-Za-z]/, 'Include a letter').regex(/\d/, 'Include a number'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, { message: 'Passwords do not match', path: ['password_confirmation'] });
type PwValues = z.infer<typeof pwSchema>;

function ChangePassword() {
  const toast = useToast();
  const [error, setError] = useState<string | null>(null);
  const { register, handleSubmit, reset, setError: setFieldError, formState } = useForm<PwValues>({ resolver: zodResolver(pwSchema) });
  const onSubmit = async (v: PwValues) => {
    setError(null);
    try {
      const r = await authApi.changePassword(v);
      toast.success('Password changed', r.message);
      reset({ current_password: '', password: '', password_confirmation: '' });
    } catch (e) {
      setError(applyServerErrors(e as ApiError, setFieldError as never, ['current_password', 'password', 'password_confirmation']));
    }
  };
  const e = formState.errors;
  return (
    <Card>
      <CardHeader title="Password" description="Changing your password signs out every other session." />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormGrid columns={3}>
            <FormField label="Current password" required error={e.current_password?.message}><Input type="password" autoComplete="current-password" {...register('current_password')} /></FormField>
            <FormField label="New password" required error={e.password?.message}><Input type="password" autoComplete="new-password" {...register('password')} /></FormField>
            <FormField label="Confirm new password" required error={e.password_confirmation?.message}><Input type="password" autoComplete="new-password" {...register('password_confirmation')} /></FormField>
          </FormGrid>
          <div style={{ marginTop: 16 }}>
            <Button type="submit" loading={formState.isSubmitting} leftIcon={<KeyRound size={16} />}>Update password</Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

/** Sensitive actions require a fresh password confirmation (server enforced). */
function ConfirmPasswordModal({ open, onClose, onConfirmed }: { open: boolean; onClose: () => void; onConfirmed: () => void }) {
  const [pw, setPw] = useState('');
  const [error, setError] = useState<string | null>(null);
  const m = useMutation({
    mutationFn: () => authApi.confirmPassword(pw),
    onSuccess: () => {
      setPw('');
      onConfirmed();
    },
    onError: (e) => setError(isApiError(e) ? (e.errors?.password?.[0] ?? e.message) : 'Failed'),
  });
  return (
    <Modal
      open={open}
      onClose={onClose}
      size="sm"
      title="Confirm your password"
      description="For your security, confirm your password to continue."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={() => m.mutate()} loading={m.isPending} disabled={!pw}>Confirm</Button>
        </>
      }
    >
      <FormField label="Password" error={error ?? undefined}>
        <Input type="password" autoComplete="current-password" value={pw} onChange={(e) => setPw(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && pw && m.mutate()} autoFocus />
      </FormField>
    </Modal>
  );
}

function TwoFactor() {
  const user = useAppSelector(selectUser);
  const { refresh } = useSession();
  const toast = useToast();
  const [confirming, setConfirming] = useState<null | 'enable' | 'codes'>(null);
  const [setup, setSetup] = useState<{ qr_svg: string; secret: string } | null>(null);
  const [code, setCode] = useState('');
  const [codes, setCodes] = useState<string[] | null>(null);
  const [disablePw, setDisablePw] = useState('');
  const [error, setError] = useState<string | null>(null);

  const enable = useMutation({
    mutationFn: authApi.twoFactor.enable,
    onSuccess: (r) => setSetup(r),
    onError: (e: ApiError) => (e.status === 423 || e.code === 'auth.password_confirmation_required' ? setConfirming('enable') : toast.error('Could not start setup', e.message)),
  });
  const confirm = useMutation({
    mutationFn: () => authApi.twoFactor.confirm(code),
    onSuccess: (r) => {
      setCodes(r.recovery_codes);
      setSetup(null);
      setCode('');
      void refresh();
      toast.success('Two-factor authentication enabled');
    },
    onError: (e: ApiError) => setError(e.errors?.code?.[0] ?? e.message),
  });
  const regen = useMutation({
    mutationFn: authApi.twoFactor.recoveryCodes,
    onSuccess: (r) => setCodes(r.recovery_codes),
    onError: (e: ApiError) => (e.status === 423 ? setConfirming('codes') : toast.error('Failed', e.message)),
  });
  const disable = useMutation({
    mutationFn: () => authApi.twoFactor.disable(disablePw),
    onSuccess: () => {
      setDisablePw('');
      void refresh();
      toast.success('Two-factor authentication disabled');
    },
    onError: (e: ApiError) => setError(e.errors?.password?.[0] ?? e.message),
  });

  const enabled = user?.two_factor_enabled;

  return (
    <Card>
      <CardHeader
        title="Two-factor authentication"
        description="Require a code from an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…) when signing in."
        actions={enabled ? <Badge variant="success"><ShieldCheck size={12} /> Enabled</Badge> : <Badge variant="neutral">Off</Badge>}
      />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }} dismissible onDismiss={() => setError(null)}>{error}</Alert>}

        {codes && (
          <Alert variant="warning" title="Save your recovery codes" style={{ marginBottom: 16 }}>
            Each code works once if you lose your device. They will not be shown again.
            <pre style={{ marginTop: 8, fontFamily: 'var(--font-mono)', fontSize: 13, display: 'grid', gridTemplateColumns: 'repeat(2, max-content)', gap: '4px 24px' }}>
              {codes.map((c) => <span key={c}>{c}</span>)}
            </pre>
            <Button size="sm" variant="secondary" onClick={() => { void navigator.clipboard?.writeText(codes.join('\n')); toast.success('Copied'); }}>Copy codes</Button>
          </Alert>
        )}

        {!enabled && !setup && (
          <Button leftIcon={<Smartphone size={16} />} onClick={() => enable.mutate()} loading={enable.isPending}>Set up two-factor</Button>
        )}

        {setup && (
          <div style={{ display: 'flex', gap: 24, flexWrap: 'wrap', alignItems: 'flex-start' }}>
            <div style={{ background: '#fff', padding: 8, borderRadius: 8, border: '1px solid var(--color-border)' }} dangerouslySetInnerHTML={{ __html: setup.qr_svg }} aria-label="QR code for authenticator app" role="img" />
            <div style={{ flex: '1 1 260px', display: 'flex', flexDirection: 'column', gap: 12 }}>
              <p style={{ fontSize: 13 }}>1. Scan the QR code with your authenticator app, or enter this key manually:</p>
              <code style={{ fontSize: 13, wordBreak: 'break-all', background: 'var(--color-bg-subtle)', padding: 8, borderRadius: 6 }}>{setup.secret}</code>
              <p style={{ fontSize: 13 }}>2. Enter the 6-digit code it shows:</p>
              <div style={{ display: 'flex', gap: 8 }}>
                <Input inputMode="numeric" maxLength={6} value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} aria-label="Verification code" style={{ maxWidth: 160, letterSpacing: '0.3em' }} />
                <Button onClick={() => confirm.mutate()} loading={confirm.isPending} disabled={code.length !== 6}>Verify & enable</Button>
              </div>
            </div>
          </div>
        )}

        {enabled && (
          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'flex-end' }}>
            <Button variant="secondary" onClick={() => regen.mutate()} loading={regen.isPending}>Regenerate recovery codes</Button>
            <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
              <FormField label="Password to disable">
                <Input type="password" value={disablePw} onChange={(e) => setDisablePw(e.target.value)} autoComplete="current-password" />
              </FormField>
              <Button variant="danger" leftIcon={<ShieldOff size={16} />} onClick={() => disable.mutate()} loading={disable.isPending} disabled={!disablePw}>Disable</Button>
            </div>
          </div>
        )}
      </CardBody>

      <ConfirmPasswordModal
        open={confirming !== null}
        onClose={() => setConfirming(null)}
        onConfirmed={() => {
          const next = confirming;
          setConfirming(null);
          if (next === 'enable') enable.mutate();
          if (next === 'codes') regen.mutate();
        }}
      />
    </Card>
  );
}

export default function SecuritySettingsPage() {
  const user = useAppSelector(selectUser);
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 960 }}>
      <PageHeader title="Security" description={user?.last_login_at ? `Last sign-in ${formatDateTime(user.last_login_at)}` : undefined} />
      <ChangePassword />
      <TwoFactor />
    </div>
  );
}
