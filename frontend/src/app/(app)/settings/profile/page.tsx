'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { MailCheck } from 'lucide-react';
import { Alert, Avatar, Button, Card, CardBody, CardHeader, FormField, FormGrid, Input, PageHeader, useToast } from '@/components/ui';
import { api } from '@/lib/api/client';
import { authApi } from '@/features/auth/api';
import { useSession } from '@/lib/auth/SessionProvider';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';

const schema = z.object({ name: z.string().trim().min(1, 'Name is required').max(150), phone: z.string().trim().max(40).optional() });
type Values = z.infer<typeof schema>;

export default function ProfileSettingsPage() {
  const user = useAppSelector(selectUser);
  const { refresh } = useSession();
  const toast = useToast();
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);
  const { register, handleSubmit, setError: setFieldError, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    values: { name: user?.name ?? '', phone: '' },
  });

  const onSubmit = async (v: Values) => {
    setError(null);
    try {
      await api.patch('/auth/profile', { name: v.name, phone: v.phone || null });
      toast.success('Profile updated');
      void refresh();
    } catch (e) {
      setError(applyServerErrors(e as ApiError, setFieldError as never, ['name', 'phone']));
    }
  };

  if (!user) return null;
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 860 }}>
      <PageHeader title="My profile" />
      {!user.email_verified_at && (
        <Alert
          variant="warning"
          title="Verify your email address"
          actions={
            <Button size="sm" variant="secondary" leftIcon={<MailCheck size={14} />} disabled={sent} onClick={async () => { await authApi.resendVerification(); setSent(true); }}>
              {sent ? 'Link sent' : 'Send verification link'}
            </Button>
          }
        >
          Some notifications are only emailed to verified addresses.
        </Alert>
      )}
      <Card>
        <CardHeader title="Personal details" />
        <CardBody>
          <div style={{ display: 'flex', gap: 16, alignItems: 'center', marginBottom: 20 }}>
            <Avatar name={user.name} src={user.avatar_url ?? undefined} size="lg" />
            <div>
              <div style={{ fontWeight: 600 }}>{user.name}</div>
              <div style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>{user.email}</div>
            </div>
          </div>
          {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
          <form onSubmit={handleSubmit(onSubmit)} noValidate>
            <FormGrid columns={2}>
              <FormField label="Full name" required error={formState.errors.name?.message}><Input {...register('name')} /></FormField>
              <FormField label="Phone" error={formState.errors.phone?.message}><Input type="tel" {...register('phone')} /></FormField>
              <FormField label="Email" hint="Contact an administrator to change your sign-in email."><Input value={user.email} readOnly /></FormField>
            </FormGrid>
            <div style={{ marginTop: 16 }}>
              <Button type="submit" loading={formState.isSubmitting}>Save profile</Button>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
