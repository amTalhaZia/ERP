'use client';

import { useEffect, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, ImageUp, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ErrorState, FormActions, FormField, FormGrid, Input, PageHeader, Select, SkeletonCard, useToast,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import { useSession } from '@/lib/auth/SessionProvider';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { companyApi, type Company } from '@/features/company/api';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const CURRENCIES = ['USD', 'EUR', 'GBP', 'PKR', 'AED', 'SAR', 'INR', 'CAD', 'AUD', 'JPY', 'CNY', 'SGD', 'ZAR', 'TRY', 'MYR', 'BDT'];
const TIMEZONES = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : ['UTC'];

const opt = z.string().trim().max(200).optional().or(z.literal(''));
const schema = z.object({
  name: z.string().trim().min(1, 'Company name is required').max(150),
  legal_name: opt,
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  phone: opt,
  website: z.url('Enter a full URL, e.g. https://example.com').optional().or(z.literal('')),
  tax_number: opt,
  registration_number: opt,
  address_line1: opt,
  address_line2: opt,
  city: opt,
  state: opt,
  postal_code: opt,
  country: z.string().trim().length(2, '2-letter code').optional().or(z.literal('')),
  currency: z.string().length(3),
  timezone: z.string().min(1),
  date_format: z.string(),
  fiscal_year_start_month: z.string(),
  primary_color: z.string().regex(/^#[0-9a-fA-F]{6}$/, 'Hex colour like #4f46e5').optional().or(z.literal('')),
});
type Values = z.infer<typeof schema>;

const toValues = (c: Company): Values => ({
  name: c.name,
  legal_name: c.legal_name ?? '',
  email: c.email ?? '',
  phone: c.phone ?? '',
  website: c.website ?? '',
  tax_number: c.tax_number ?? '',
  registration_number: c.registration_number ?? '',
  address_line1: c.address.line1 ?? '',
  address_line2: c.address.line2 ?? '',
  city: c.address.city ?? '',
  state: c.address.state ?? '',
  postal_code: c.address.postal_code ?? '',
  country: c.address.country ?? '',
  currency: c.currency,
  timezone: c.timezone,
  date_format: c.date_format,
  fiscal_year_start_month: String(c.fiscal_year_start_month),
  primary_color: c.primary_color ?? '',
});

export default function CompanyProfilePage() {
  const can = useCan();
  const toast = useToast();
  const qc = useQueryClient();
  const { refresh } = useSession();
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const canEdit = can('company.settings.manage');
  const fileRef = useRef<HTMLInputElement>(null);
  const [formError, setFormError] = useState<string | null>(null);

  const q = useQuery({ queryKey: [companyId, 'company'], queryFn: companyApi.get });
  const form = useForm<Values>({ resolver: zodResolver(schema) });
  const { register, handleSubmit, reset, setError, formState } = form;

  useEffect(() => {
    if (q.data) reset(toValues(q.data));
  }, [q.data, reset]);

  const save = useMutation({
    mutationFn: companyApi.update,
    onSuccess: (c) => {
      qc.setQueryData([companyId, 'company'], c);
      reset(toValues(c));
      toast.success('Company settings saved');
      void refresh();
    },
  });
  const logo = useMutation({
    mutationFn: companyApi.uploadLogo,
    onSuccess: (c) => {
      qc.setQueryData([companyId, 'company'], c);
      toast.success('Logo updated');
      void refresh();
    },
    onError: (e: ApiError) => toast.error('Logo upload failed', e.errors?.logo?.[0] ?? e.message),
  });
  const removeLogo = useMutation({
    mutationFn: companyApi.deleteLogo,
    onSuccess: () => {
      void q.refetch();
      void refresh();
    },
  });

  const onSubmit = async (v: Values) => {
    setFormError(null);
    try {
      await save.mutateAsync({
        ...v,
        fiscal_year_start_month: Number(v.fiscal_year_start_month),
        email: v.email || null,
        website: v.website || null,
        country: v.country ? v.country.toUpperCase() : null,
        primary_color: v.primary_color || null,
      });
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const c = q.data;
  const e = formState.errors;

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title="Company profile"
        description="Legal details, branding and localisation for this company."
        meta={
          <>
            <Badge variant="neutral">{c.code}</Badge>
            {c.is_demo && <Badge variant="warning">Demo data</Badge>}
            <Badge variant="neutral">{c.branches_count ?? 0} branches</Badge>
            <Badge variant="neutral">{c.users_count ?? 0} users</Badge>
          </>
        }
      />

      {!canEdit && <Alert variant="info">You can view these settings. Changing them requires the “Manage company settings” permission.</Alert>}
      {formError && <Alert variant="danger">{formError}</Alert>}

      <Card>
        <CardHeader title="Branding" description="Shown in the sidebar, documents and emails." />
        <CardBody>
          <div style={{ display: 'flex', alignItems: 'center', gap: 20, flexWrap: 'wrap' }}>
            <div style={{ width: 72, height: 72, borderRadius: 12, border: '1px solid var(--color-border)', display: 'grid', placeItems: 'center', background: 'var(--color-bg-subtle)', overflow: 'hidden' }}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {c.logo_url ? <img src={c.logo_url} alt={`${c.name} logo`} style={{ width: '100%', height: '100%', objectFit: 'contain' }} /> : <Building2 size={28} color="var(--color-text-muted)" />}
            </div>
            {canEdit && (
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <input
                  ref={fileRef}
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/svg+xml"
                  hidden
                  onChange={(ev) => {
                    const f = ev.target.files?.[0];
                    if (f) logo.mutate(f);
                    ev.target.value = '';
                  }}
                />
                <Button type="button" variant="secondary" leftIcon={<ImageUp size={16} />} loading={logo.isPending} onClick={() => fileRef.current?.click()}>
                  Upload logo
                </Button>
                {c.logo_url && (
                  <Button type="button" variant="ghost" leftIcon={<Trash2 size={16} />} loading={removeLogo.isPending} onClick={() => removeLogo.mutate()}>
                    Remove
                  </Button>
                )}
                <span style={{ fontSize: 12, color: 'var(--color-text-muted)', alignSelf: 'center' }}>PNG, JPG, WebP or SVG · max 2 MB</span>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      <fieldset disabled={!canEdit} style={{ border: 0, padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 16 }}>
        <Card>
          <CardHeader title="Legal & contact" />
          <CardBody>
            <FormGrid columns={2}>
              <FormField label="Company name" required error={e.name?.message}><Input {...register('name')} /></FormField>
              <FormField label="Legal name" error={e.legal_name?.message}><Input {...register('legal_name')} /></FormField>
              <FormField label="Tax number" error={e.tax_number?.message}><Input {...register('tax_number')} /></FormField>
              <FormField label="Registration number" error={e.registration_number?.message}><Input {...register('registration_number')} /></FormField>
              <FormField label="Email" error={e.email?.message}><Input type="email" {...register('email')} /></FormField>
              <FormField label="Phone" error={e.phone?.message}><Input type="tel" {...register('phone')} /></FormField>
              <FormField label="Website" error={e.website?.message} span="full"><Input type="url" placeholder="https://" {...register('website')} /></FormField>
            </FormGrid>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Registered address" />
          <CardBody>
            <FormGrid columns={2}>
              <FormField label="Address line 1" span="full" error={e.address_line1?.message}><Input {...register('address_line1')} /></FormField>
              <FormField label="Address line 2" span="full" error={e.address_line2?.message}><Input {...register('address_line2')} /></FormField>
              <FormField label="City" error={e.city?.message}><Input {...register('city')} /></FormField>
              <FormField label="State / region" error={e.state?.message}><Input {...register('state')} /></FormField>
              <FormField label="Postal code" error={e.postal_code?.message}><Input {...register('postal_code')} /></FormField>
              <FormField label="Country" hint="ISO code, e.g. PK" error={e.country?.message}><Input maxLength={2} {...register('country')} /></FormField>
            </FormGrid>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Localisation & finance" description="Currency and fiscal year drive accounting periods and reports." />
          <CardBody>
            <FormGrid columns={2}>
              <FormField label="Base currency" required hint="Changing it after posting transactions is not recommended." error={e.currency?.message}>
                <Select {...register('currency')} options={Array.from(new Set([c.currency, ...CURRENCIES])).map((x) => ({ value: x, label: x }))} />
              </FormField>
              <FormField label="Fiscal year starts" required error={e.fiscal_year_start_month?.message}>
                <Select {...register('fiscal_year_start_month')} options={MONTHS.map((m, i) => ({ value: String(i + 1), label: m }))} />
              </FormField>
              <FormField label="Time zone" required error={e.timezone?.message}>
                <Select {...register('timezone')} options={TIMEZONES.map((t) => ({ value: t, label: t }))} />
              </FormField>
              <FormField label="Date format" required error={e.date_format?.message}>
                <Select
                  {...register('date_format')}
                  options={[
                    { value: 'Y-m-d', label: '2026-10-14' },
                    { value: 'd/m/Y', label: '14/10/2026' },
                    { value: 'm/d/Y', label: '10/14/2026' },
                    { value: 'd.m.Y', label: '14.10.2026' },
                    { value: 'd M Y', label: '14 Oct 2026' },
                  ]}
                />
              </FormField>
              <FormField label="Brand colour" error={e.primary_color?.message}><Input placeholder="#4f46e5" {...register('primary_color')} /></FormField>
            </FormGrid>
          </CardBody>
        </Card>
      </fieldset>

      {canEdit && (
        <FormActions>
          <Button type="button" variant="secondary" disabled={!formState.isDirty} onClick={() => reset(toValues(c))}>Reset</Button>
          <Button type="submit" loading={formState.isSubmitting} disabled={!formState.isDirty}>Save changes</Button>
        </FormActions>
      )}
    </form>
  );
}
