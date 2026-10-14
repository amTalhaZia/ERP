'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, Drawer, FormField, FormGrid, Input, Select, Switch } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { categories, units, type ProductCategory, type UnitOfMeasure } from './api';
import { categoryOptions, useCategoryTree } from './ProductForm';

const code = z.string().trim().min(1, 'Code is required').max(30).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only');

// ---- Category ---------------------------------------------------------------

const categorySchema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(120),
  code,
  parent_id: z.string(),
  is_active: z.boolean(),
});
type CategoryValues = z.infer<typeof categorySchema>;

export function CategoryForm({ open, category, onClose }: { open: boolean; category: ProductCategory | null; onClose: () => void }) {
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, formState } = useForm<CategoryValues>({
    resolver: zodResolver(categorySchema),
    defaultValues: {
      name: category?.name ?? '',
      code: category?.code ?? '',
      parent_id: category?.parent_id ? String(category.parent_id) : '',
      is_active: category?.is_active ?? true,
    },
  });
  const create = categories.useCreate({ onSuccess: onClose });
  const update = categories.useUpdate({ onSuccess: onClose });
  const tree = useCategoryTree(open);

  const onSubmit = async (v: CategoryValues) => {
    setFormError(null);
    const body = { ...v, parent_id: v.parent_id ? Number(v.parent_id) : null };
    try {
      if (category) await update.mutateAsync({ id: category.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(categorySchema.shape)));
    }
  };

  // Exclude the category itself and its whole subtree (a category cannot be its own ancestor).
  const parentOpts = categoryOptions(tree.data, category?.id);
  if (category?.parent && !parentOpts.some((o) => o.value === String(category.parent_id))) {
    parentOpts.unshift({ value: String(category.parent.id), label: category.parent.name });
  }

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={category ? `Edit ${category.name}` : 'New category'}
      size="sm"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="category-form" loading={formState.isSubmitting}>{category ? 'Save' : 'Create category'}</Button>
        </>
      }
    >
      <form id="category-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={1}>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
          <FormField label="Code" required error={e.code?.message}><Input {...register('code')} /></FormField>
          <FormField label="Parent category" error={e.parent_id?.message} hint="Only active categories are listed">
            <Select {...register('parent_id')} placeholder="None (top level)" options={parentOpts} />
          </FormField>
          <Controller control={control} name="is_active" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" />
          )} />
        </FormGrid>
      </form>
    </Drawer>
  );
}

// ---- Unit of measure ------------------------------------------------------------

const uomSchema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(60),
  symbol: z.string().trim().min(1, 'Symbol is required').max(12),
  precision: z.string().regex(/^[0-4]$/, 'Between 0 and 4'),
  is_active: z.boolean(),
});
type UomValues = z.infer<typeof uomSchema>;

export function UomForm({ open, uom, onClose }: { open: boolean; uom: UnitOfMeasure | null; onClose: () => void }) {
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, formState } = useForm<UomValues>({
    resolver: zodResolver(uomSchema),
    defaultValues: { name: uom?.name ?? '', symbol: uom?.symbol ?? '', precision: String(uom?.precision ?? 0), is_active: uom?.is_active ?? true },
  });
  const create = units.useCreate({ onSuccess: onClose });
  const update = units.useUpdate({ onSuccess: onClose });

  const onSubmit = async (v: UomValues) => {
    setFormError(null);
    const body = { ...v, precision: Number(v.precision) };
    try {
      if (uom) await update.mutateAsync({ id: uom.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(uomSchema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={uom ? `Edit ${uom.name}` : 'New unit of measure'}
      size="sm"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="uom-form" loading={formState.isSubmitting}>{uom ? 'Save' : 'Create unit'}</Button>
        </>
      }
    >
      <form id="uom-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={1}>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus placeholder="Kilogram" /></FormField>
          <FormField label="Symbol" required error={e.symbol?.message} hint="Unique, e.g. kg, pcs, m"><Input {...register('symbol')} /></FormField>
          <FormField label="Decimal places" error={e.precision?.message} hint="How many decimals quantities in this unit allow">
            <Select {...register('precision')} options={['0', '1', '2', '3', '4'].map((p) => ({ value: p, label: p }))} />
          </FormField>
          <Controller control={control} name="is_active" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" />
          )} />
        </FormGrid>
      </form>
    </Drawer>
  );
}
