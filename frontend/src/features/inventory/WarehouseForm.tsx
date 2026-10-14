'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, Drawer, FormField, FormGrid, FormSection, Input, Select, Switch } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { branches } from '@/features/company/api';
import { warehouses, type Warehouse, type WarehouseInput } from './api';

const optional = (max: number) => z.string().trim().max(max);
const coord = (min: number, max: number) =>
  z.string().trim().refine((v) => !v || (!Number.isNaN(Number(v)) && Number(v) >= min && Number(v) <= max), `Must be between ${min} and ${max}`);

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  code: z.string().trim().min(1, 'Code is required').max(20).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only'),
  branch_id: z.string(),
  address_line1: optional(200),
  address_line2: optional(200),
  city: optional(100),
  state: optional(100),
  postal_code: optional(20),
  country: z.string().trim().refine((v) => v === '' || v.length === 2, 'Use a 2-letter country code'),
  latitude: coord(-90, 90),
  longitude: coord(-180, 180),
  is_default: z.boolean(),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

const toValues = (w: Warehouse | null): Values => ({
  name: w?.name ?? '',
  code: w?.code ?? '',
  branch_id: w?.branch_id ? String(w.branch_id) : '',
  address_line1: w?.address.line1 ?? '',
  address_line2: w?.address.line2 ?? '',
  city: w?.address.city ?? '',
  state: w?.address.state ?? '',
  postal_code: w?.address.postal_code ?? '',
  country: w?.address.country ?? '',
  latitude: w?.latitude ?? '',
  longitude: w?.longitude ?? '',
  is_default: w?.is_default ?? false,
  is_active: w?.is_active ?? true,
});

export function WarehouseForm({ open, warehouse, onClose }: { open: boolean; warehouse: Warehouse | null; onClose: () => void }) {
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, formState } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: toValues(warehouse) });
  const create = warehouses.useCreate({ onSuccess: onClose });
  const update = warehouses.useUpdate({ onSuccess: onClose });
  const branchList = branches.useAll('all', {}, { enabled: open });

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: WarehouseInput = {
      ...v,
      branch_id: v.branch_id ? Number(v.branch_id) : null,
      address_line1: v.address_line1 || null,
      address_line2: v.address_line2 || null,
      city: v.city || null,
      state: v.state || null,
      postal_code: v.postal_code || null,
      country: v.country ? v.country.toUpperCase() : null,
      latitude: v.latitude ? Number(v.latitude) : null,
      longitude: v.longitude ? Number(v.longitude) : null,
    };
    try {
      if (warehouse) await update.mutateAsync({ id: warehouse.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const branchOpts = (branchList.data ?? []).map((b) => ({ value: String(b.id), label: b.name }));
  if (warehouse?.branch && !branchOpts.some((o) => o.value === String(warehouse.branch_id))) {
    branchOpts.unshift({ value: String(warehouse.branch.id), label: warehouse.branch.name });
  }

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={warehouse ? `Edit ${warehouse.name}` : 'New warehouse'}
      size="md"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="warehouse-form" loading={formState.isSubmitting}>{warehouse ? 'Save changes' : 'Create warehouse'}</Button>
        </>
      }
    >
      <form id="warehouse-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormSection title="Details">
          <FormGrid columns={2}>
            <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
            <FormField label="Code" required error={e.code?.message} hint="Unique within the company"><Input {...register('code')} /></FormField>
            <FormField label="Branch" span="full" error={e.branch_id?.message}>
              <Select {...register('branch_id')} placeholder="Company-wide (no branch)" options={branchOpts} />
            </FormField>
          </FormGrid>
          <FormGrid columns={2}>
            <Controller control={control} name="is_default" render={({ field }) => (
              <Switch checked={field.value} onCheckedChange={field.onChange} label="Default warehouse" description="Pre-selected for stock operations." />
            )} />
            <Controller control={control} name="is_active" render={({ field }) => (
              <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" />
            )} />
          </FormGrid>
        </FormSection>
        <FormSection title="Address & location">
          <FormGrid columns={2}>
            <FormField label="Address line 1" span="full" error={e.address_line1?.message}><Input {...register('address_line1')} /></FormField>
            <FormField label="Address line 2" span="full" error={e.address_line2?.message}><Input {...register('address_line2')} /></FormField>
            <FormField label="City" error={e.city?.message}><Input {...register('city')} /></FormField>
            <FormField label="State / region" error={e.state?.message}><Input {...register('state')} /></FormField>
            <FormField label="Postal code" error={e.postal_code?.message}><Input {...register('postal_code')} /></FormField>
            <FormField label="Country" hint="ISO code, e.g. PK, US" error={e.country?.message}><Input maxLength={2} {...register('country')} /></FormField>
            <FormField label="Latitude" hint="Shown on the locations map" error={e.latitude?.message}><Input inputMode="decimal" {...register('latitude')} /></FormField>
            <FormField label="Longitude" error={e.longitude?.message}><Input inputMode="decimal" {...register('longitude')} /></FormField>
          </FormGrid>
        </FormSection>
      </form>
    </Drawer>
  );
}
