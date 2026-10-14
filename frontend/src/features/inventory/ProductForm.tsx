'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, CurrencyInput, Drawer, FormField, FormGrid, FormSection, Input, Select, Switch, Textarea } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useQuery } from '@tanstack/react-query';
import { PRODUCT_TYPES, categories, products, stockApi, units, type CategoryNode, type Product, type ProductInput } from './api';
import { QuantityInput, useCompanyId, useCurrency } from './shared';

const decimal = (label: string) =>
  z.string().trim().refine((v) => v === '' || /^\d+(\.\d{1,4})?$/.test(v), `${label} must be a number with up to 4 decimals`);

const schema = z.object({
  sku: z.string().trim().min(1, 'SKU is required').max(60),
  barcode: z.string().trim().max(64),
  name: z.string().trim().min(1, 'Name is required').max(200),
  description: z.string().max(1000),
  type: z.enum(['goods', 'service']),
  category_id: z.string(),
  uom_id: z.string(),
  cost_price: z.string().nullable(),
  sale_price: z.string().nullable(),
  tax_rate: decimal('Tax rate').refine((v) => v === '' || Number(v) <= 100, 'Tax rate must be between 0 and 100'),
  reorder_level: decimal('Reorder level'),
  reorder_quantity: decimal('Reorder quantity'),
  track_batches: z.boolean(),
  track_serials: z.boolean(),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

const toValues = (p: Product | null): Values => ({
  sku: p?.sku ?? '',
  barcode: p?.barcode ?? '',
  name: p?.name ?? '',
  description: p?.description ?? '',
  type: p?.type ?? 'goods',
  category_id: p?.category_id ? String(p.category_id) : '',
  uom_id: p?.uom_id ? String(p.uom_id) : '',
  cost_price: p?.cost_price ?? null,
  sale_price: p?.sale_price ?? null,
  tax_rate: p?.tax_rate ?? '',
  reorder_level: p?.reorder_level ?? '',
  reorder_quantity: p?.reorder_quantity ?? '',
  track_batches: p?.track_batches ?? false,
  track_serials: p?.track_serials ?? false,
  is_active: p?.is_active ?? true,
});

/** Flattens the category tree into indented Select options. */
export function categoryOptions(tree: CategoryNode[] | undefined, exclude?: number) {
  const out: { value: string; label: string }[] = [];
  const walk = (nodes: CategoryNode[], depth: number) => {
    for (const n of nodes) {
      if (n.id === exclude) continue; // also skips its descendants
      out.push({ value: String(n.id), label: `${'   '.repeat(depth)}${depth ? '└ ' : ''}${n.name}` });
      walk(n.children, depth + 1);
    }
  };
  walk(tree ?? [], 0);
  return out;
}

export function useCategoryTree(enabled = true) {
  const companyId = useCompanyId();
  return useQuery({ queryKey: [companyId, 'product-categories', 'tree'], queryFn: stockApi.categoryTree, staleTime: 60_000, enabled });
}

/** Create / edit drawer. Remount with a `key` to reset between records. */
export function ProductForm({ open, product, onClose, onSaved }: { open: boolean; product: Product | null; onClose: () => void; onSaved?: (p: Product) => void }) {
  const currency = useCurrency();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, watch, formState } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: toValues(product) });
  const done = (p: Product) => {
    onSaved?.(p);
    onClose();
  };
  const create = products.useCreate({ onSuccess: done });
  const update = products.useUpdate({ onSuccess: done });
  const tree = useCategoryTree(open);
  const uoms = units.useList({ per_page: 100, sort: 'name', 'filter[is_active]': 'true' }, { enabled: open });
  // Fall back to the flat list if the tree is unavailable (it only holds active categories).
  const flat = categories.useList({ per_page: 100, sort: 'name' }, { enabled: open && !!tree.error });
  const type = watch('type');

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: ProductInput = {
      sku: v.sku,
      barcode: v.barcode || null,
      name: v.name,
      description: v.description || null,
      type: v.type,
      category_id: v.category_id ? Number(v.category_id) : null,
      uom_id: v.uom_id ? Number(v.uom_id) : null,
      cost_price: v.cost_price ?? '0',
      sale_price: v.sale_price ?? '0',
      tax_rate: v.tax_rate || null,
      reorder_level: v.reorder_level || '0',
      reorder_quantity: v.reorder_quantity || '0',
      track_batches: v.type === 'goods' && v.track_batches,
      track_serials: v.type === 'goods' && v.track_serials,
      is_active: v.is_active,
    };
    try {
      if (product) await update.mutateAsync({ id: product.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const catOpts = tree.data ? categoryOptions(tree.data) : (flat.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }));
  // Keep an inactive current category selectable.
  if (product?.category && !catOpts.some((o) => o.value === String(product.category_id))) {
    catOpts.unshift({ value: String(product.category.id), label: product.category.name });
  }
  const uomOpts = (uoms.data?.data ?? []).map((u) => ({ value: String(u.id), label: `${u.name} (${u.symbol})` }));
  if (product?.uom && !uomOpts.some((o) => o.value === String(product.uom_id))) {
    uomOpts.unshift({ value: String(product.uom.id), label: `${product.uom.name} (${product.uom.symbol})` });
  }

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={product ? `Edit ${product.sku}` : 'New product'}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="product-form" loading={formState.isSubmitting}>{product ? 'Save changes' : 'Create product'}</Button>
        </>
      }
    >
      <form id="product-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormSection title="Identity">
          <FormGrid columns={2}>
            <FormField label="SKU" required error={e.sku?.message} hint="Unique within the company">
              <Input {...register('sku')} autoFocus autoComplete="off" />
            </FormField>
            <FormField label="Barcode" error={e.barcode?.message}>
              <Input {...register('barcode')} autoComplete="off" />
            </FormField>
            <FormField label="Name" required span="full" error={e.name?.message}>
              <Input {...register('name')} />
            </FormField>
            <FormField label="Description" span="full" error={e.description?.message}>
              <Textarea rows={2} {...register('description')} />
            </FormField>
            <FormField label="Type" required error={e.type?.message} hint={type === 'service' ? 'Services are not stocked' : undefined}>
              <Select {...register('type')} options={PRODUCT_TYPES} />
            </FormField>
            <FormField label="Category" error={e.category_id?.message}>
              <Select {...register('category_id')} placeholder="Uncategorised" options={catOpts} />
            </FormField>
            <FormField label="Unit of measure" error={e.uom_id?.message}>
              <Select {...register('uom_id')} placeholder="None" options={uomOpts} />
            </FormField>
          </FormGrid>
        </FormSection>

        <FormSection title="Pricing">
          <FormGrid columns={3}>
            <FormField label="Cost price" error={e.cost_price?.message}>
              <Controller control={control} name="cost_price" render={({ field }) => (
                <CurrencyInput currency={currency} value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} />
              )} />
            </FormField>
            <FormField label="Sale price" error={e.sale_price?.message}>
              <Controller control={control} name="sale_price" render={({ field }) => (
                <CurrencyInput currency={currency} value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} />
              )} />
            </FormField>
            <FormField label="Tax rate" error={e.tax_rate?.message}>
              <Controller control={control} name="tax_rate" render={({ field }) => (
                <QuantityInput value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} trailing="%" placeholder="0" />
              )} />
            </FormField>
          </FormGrid>
        </FormSection>

        {type === 'goods' && (
          <FormSection title="Stock control">
            <FormGrid columns={2}>
              <FormField label="Reorder level" hint="Alert when total on-hand falls to this level" error={e.reorder_level?.message}>
                <Controller control={control} name="reorder_level" render={({ field }) => (
                  <QuantityInput value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} placeholder="0" />
                )} />
              </FormField>
              <FormField label="Reorder quantity" hint="Suggested quantity to reorder" error={e.reorder_quantity?.message}>
                <Controller control={control} name="reorder_quantity" render={({ field }) => (
                  <QuantityInput value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} placeholder="0" />
                )} />
              </FormField>
              <Controller control={control} name="track_batches" render={({ field }) => (
                <Switch checked={field.value} onCheckedChange={field.onChange} label="Track batches" description="Receipts record batch number and expiry." />
              )} />
              <Controller control={control} name="track_serials" render={({ field }) => (
                <Switch checked={field.value} onCheckedChange={field.onChange} label="Track serial numbers" description="One serial per unit on receipt and issue." />
              )} />
            </FormGrid>
          </FormSection>
        )}

        <Controller control={control} name="is_active" render={({ field }) => (
          <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive products cannot be received, issued or transferred." />
        )} />
      </form>
    </Drawer>
  );
}
