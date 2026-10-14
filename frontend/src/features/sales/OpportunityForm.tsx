'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Trash2 } from 'lucide-react';
import { Alert, Button, ConfirmDialog, CurrencyInput, DateInput, Drawer, FormField, FormGrid, Input, Select, Textarea } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { opportunities, type Opportunity, type PipelineStage } from './api';
import { nullable, useCustomerOptions, useUserOptions } from './shared';

const schema = z.object({
  title: z.string().trim().min(1, 'Title is required').max(200),
  customer_id: z.string(),
  stage_id: z.string().min(1, 'Choose a stage'),
  value: z.string().nullable(),
  probability: z
    .string()
    .trim()
    .refine((v) => v === '' || (/^\d{1,3}$/.test(v) && Number(v) <= 100), 'Whole number 0–100'),
  expected_close_date: z.string().nullable(),
  owner_id: z.string(),
  lost_reason: z.string().max(500).optional().or(z.literal('')),
});
type Values = z.infer<typeof schema>;

export function OpportunityForm({
  open,
  opportunity,
  stages,
  defaultStageId,
  canManage,
  onClose,
}: {
  open: boolean;
  opportunity: Opportunity | null;
  stages: PipelineStage[];
  defaultStageId?: number;
  canManage: boolean;
  onClose: () => void;
}) {
  const companyCurrency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const currency = opportunity?.currency ?? companyCurrency;
  const [formError, setFormError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const customersOpt = useCustomerOptions(opportunity?.customer ?? null);
  const users = useUserOptions([opportunity?.owner]);
  const create = opportunities.useCreate({ onSuccess: onClose });
  const update = opportunities.useUpdate({ onSuccess: onClose });
  const del = opportunities.useDelete({ onSuccess: onClose });
  const firstOpen = stages.find((s) => !s.is_won && !s.is_lost);

  const { register, handleSubmit, setError, control, watch, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: opportunity?.title ?? '',
      customer_id: opportunity?.customer_id ? String(opportunity.customer_id) : '',
      stage_id: String(opportunity?.stage_id ?? defaultStageId ?? firstOpen?.id ?? ''),
      value: opportunity?.value ?? null,
      probability: opportunity ? String(opportunity.probability) : '',
      expected_close_date: opportunity?.expected_close_date ?? null,
      owner_id: opportunity?.owner_id ? String(opportunity.owner_id) : '',
      lost_reason: opportunity?.lost_reason ?? '',
    },
  });
  const stageId = watch('stage_id');
  const stage = stages.find((s) => String(s.id) === stageId);

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: Record<string, unknown> = {
      title: v.title,
      customer_id: v.customer_id ? Number(v.customer_id) : null,
      stage_id: Number(v.stage_id),
      value: v.value ?? '0',
      expected_close_date: v.expected_close_date || null,
      lost_reason: stage?.is_lost ? nullable(v.lost_reason) : null,
    };
    // Send probability only when typed, so a stage change can apply its own default.
    if (v.probability !== '' && (!opportunity || formState.dirtyFields.probability)) body.probability = Number(v.probability);
    // On create the API defaults the owner to you; only send an explicit choice.
    if (v.owner_id || opportunity) body.owner_id = v.owner_id ? Number(v.owner_id) : null;
    try {
      if (opportunity) await update.mutateAsync({ id: opportunity.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  const readOnly = !canManage;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={opportunity ? opportunity.title : 'New opportunity'}
      description={opportunity ? `${opportunity.status === 'open' ? 'Open' : opportunity.status === 'won' ? 'Won' : 'Lost'} · ${stage?.name ?? opportunity.stage?.name ?? ''}` : 'Add a deal to the pipeline.'}
      size="md"
      footer={
        readOnly ? (
          <Button variant="secondary" onClick={onClose}>Close</Button>
        ) : (
          <>
            {opportunity && (
              <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirmDelete(true)} style={{ marginRight: 'auto' }}>Delete</Button>
            )}
            <Button variant="secondary" onClick={onClose}>Cancel</Button>
            <Button type="submit" form="opportunity-form" loading={formState.isSubmitting}>{opportunity ? 'Save changes' : 'Create opportunity'}</Button>
          </>
        )
      }
    >
      <form id="opportunity-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        <fieldset disabled={readOnly} style={{ border: 0, padding: 0, margin: 0 }}>
          {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
          <FormGrid columns={2}>
            <FormField label="Title" required span="full" error={e.title?.message}><Input {...register('title')} autoFocus maxLength={200} /></FormField>
            <FormField label="Customer" span="full" error={e.customer_id?.message}>
              <Select {...register('customer_id')} placeholder={customersOpt.isLoading ? 'Loading…' : 'No customer yet'} options={customersOpt.options} />
            </FormField>
            <FormField label="Stage" required error={e.stage_id?.message}>
              <Select {...register('stage_id')} placeholder="Choose a stage" options={stages.map((s) => ({ value: String(s.id), label: `${s.name} (${s.probability}%)` }))} />
            </FormField>
            <FormField label="Probability %" hint={`Blank uses the stage default${stage ? ` (${stage.probability}%)` : ''}`} error={e.probability?.message}>
              <Input inputMode="numeric" {...register('probability')} placeholder={stage ? String(stage.probability) : ''} />
            </FormField>
            <FormField label="Value" error={e.value?.message}>
              <Controller control={control} name="value" render={({ field }) => <CurrencyInput currency={currency} value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} />} />
            </FormField>
            <FormField label="Expected close" error={e.expected_close_date?.message}>
              <Controller control={control} name="expected_close_date" render={({ field }) => <DateInput value={field.value} onValueChange={field.onChange} />} />
            </FormField>
            <FormField label="Owner" span="full" error={e.owner_id?.message} hint={!opportunity ? 'Defaults to you.' : undefined}>
              <Select {...register('owner_id')} placeholder={opportunity ? 'No owner' : 'Me'} options={users.options} />
            </FormField>
            {stage?.is_lost && (
              <FormField label="Lost reason" span="full" error={e.lost_reason?.message}>
                <Textarea rows={3} maxLength={500} {...register('lost_reason')} placeholder="Price, timing, competitor…" />
              </FormField>
            )}
          </FormGrid>
        </fieldset>
      </form>
      {opportunity && (
        <ConfirmDialog
          open={confirmDelete}
          onClose={() => setConfirmDelete(false)}
          title={`Delete “${opportunity.title}”?`}
          destructive
          confirmLabel="Delete opportunity"
          onConfirm={async () => {
            await del.mutateAsync(opportunity.id);
            setConfirmDelete(false);
          }}
        />
      )}
    </Drawer>
  );
}
