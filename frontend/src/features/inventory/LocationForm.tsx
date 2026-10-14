'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, Drawer, FormField, FormGrid, Input, Select, Switch, useToast } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { humanize } from '@/lib/format';
import { LOCATION_TYPES, locations, stockApi, type LocationInput, type StorageLocation } from './api';

const schema = z.object({
  code: z.string().trim().min(1, 'Code is required').max(30).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only'),
  name: z.string().trim().min(1, 'Name is required').max(120),
  type: z.enum(['zone', 'aisle', 'rack', 'bin']),
  parent_id: z.string(),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

export function LocationForm({
  open,
  warehouseId,
  location,
  siblings,
  onClose,
}: {
  open: boolean;
  warehouseId: number;
  location: StorageLocation | null;
  /** Other locations in the same warehouse (parent candidates). */
  siblings: StorageLocation[];
  onClose: () => void;
}) {
  const qc = useQueryClient();
  const toast = useToast();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: location?.code ?? '',
      name: location?.name ?? '',
      type: location?.type ?? 'bin',
      parent_id: location?.parent_id ? String(location.parent_id) : '',
      is_active: location?.is_active ?? true,
    },
  });
  const create = useMutation<StorageLocation, ApiError, LocationInput>({
    mutationFn: (body) => stockApi.createLocation(warehouseId, body),
    onSuccess: () => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('locations') });
      toast.success('Location created');
      onClose();
    },
  });
  const update = locations.useUpdate({ onSuccess: onClose });

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: LocationInput = { ...v, parent_id: v.parent_id ? Number(v.parent_id) : null };
    try {
      if (location) await update.mutateAsync({ id: location.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const parentOpts = siblings.filter((s) => s.id !== location?.id).map((s) => ({ value: String(s.id), label: `${s.code} · ${s.name}` }));

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={location ? `Edit ${location.code}` : 'New storage location'}
      size="sm"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="location-form" loading={formState.isSubmitting}>{location ? 'Save' : 'Create location'}</Button>
        </>
      }
    >
      <form id="location-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={1}>
          <FormField label="Code" required error={e.code?.message} hint="Unique within this warehouse, e.g. A-01-03"><Input {...register('code')} autoFocus /></FormField>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} /></FormField>
          <FormField label="Type" error={e.type?.message}>
            <Select {...register('type')} options={LOCATION_TYPES.map((t) => ({ value: t, label: humanize(t) }))} />
          </FormField>
          <FormField label="Parent location" error={e.parent_id?.message}>
            <Select {...register('parent_id')} placeholder="None" options={parentOpts} />
          </FormField>
          <Controller control={control} name="is_active" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" />
          )} />
        </FormGrid>
      </form>
    </Drawer>
  );
}
