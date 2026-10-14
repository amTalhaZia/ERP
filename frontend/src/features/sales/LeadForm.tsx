'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useRouter } from 'next/navigation';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, Checkbox, CurrencyInput, Drawer, FormField, FormGrid, FormSection, Input, Modal, Select, Textarea, useToast } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { leads, salesApi, LEAD_SOURCES, LEAD_STATUSES, type Lead } from './api';
import { enumOptions, flattenErrors, nullable, useStages, useUserOptions } from './shared';

const EDITABLE_STATUSES = LEAD_STATUSES.filter((s) => s !== 'converted');

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  company_name: z.string().trim().max(150).optional().or(z.literal('')),
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  phone: z.string().trim().max(40).optional().or(z.literal('')),
  source: z.enum(LEAD_SOURCES),
  status: z.string(),
  estimated_value: z.string().nullable(),
  assigned_to: z.string(),
  notes: z.string().max(5000).optional().or(z.literal('')),
});
type Values = z.infer<typeof schema>;

export function LeadForm({ open, lead, onClose }: { open: boolean; lead: Lead | null; onClose: () => void }) {
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const [formError, setFormError] = useState<string | null>(null);
  const users = useUserOptions([lead?.assignee]);
  const create = leads.useCreate({ onSuccess: onClose });
  const update = leads.useUpdate({ onSuccess: onClose });
  const converted = lead?.status === 'converted';
  const { register, handleSubmit, setError, control, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: lead?.name ?? '',
      company_name: lead?.company_name ?? '',
      email: lead?.email ?? '',
      phone: lead?.phone ?? '',
      source: lead?.source ?? 'website',
      status: lead?.status ?? 'new',
      estimated_value: lead?.estimated_value ?? null,
      assigned_to: lead?.assigned_to ? String(lead.assigned_to) : '',
      notes: lead?.notes ?? '',
    },
  });

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: Record<string, unknown> = {
      name: v.name,
      company_name: nullable(v.company_name),
      email: nullable(v.email),
      phone: nullable(v.phone),
      source: v.source,
      estimated_value: v.estimated_value,
      assigned_to: v.assigned_to ? Number(v.assigned_to) : null,
      notes: nullable(v.notes),
    };
    if (!converted) body.status = v.status;
    try {
      if (lead) await update.mutateAsync({ id: lead.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={lead ? `Edit ${lead.name}` : 'New lead'}
      size="md"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="lead-form" loading={formState.isSubmitting}>{lead ? 'Save changes' : 'Create lead'}</Button>
        </>
      }
    >
      <form id="lead-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormSection title="Contact">
          <FormGrid columns={2}>
            <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
            <FormField label="Company" error={e.company_name?.message}><Input {...register('company_name')} /></FormField>
            <FormField label="Email" error={e.email?.message}><Input type="email" {...register('email')} /></FormField>
            <FormField label="Phone" error={e.phone?.message}><Input type="tel" {...register('phone')} /></FormField>
          </FormGrid>
        </FormSection>
        <FormSection title="Qualification">
          <FormGrid columns={2}>
            <FormField label="Source" error={e.source?.message}><Select {...register('source')} options={enumOptions(LEAD_SOURCES)} /></FormField>
            <FormField label="Status" error={e.status?.message} hint={converted ? 'Converted leads keep their status.' : 'Converted is set by the Convert action.'}>
              <Select {...register('status')} disabled={converted} options={enumOptions(converted ? LEAD_STATUSES : EDITABLE_STATUSES)} />
            </FormField>
            <FormField label="Estimated value" error={e.estimated_value?.message}>
              <Controller control={control} name="estimated_value" render={({ field }) => <CurrencyInput currency={currency} value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} />} />
            </FormField>
            <FormField label="Assigned to" error={e.assigned_to?.message}><Select {...register('assigned_to')} placeholder="Unassigned" options={users.options} /></FormField>
            <FormField label="Notes" span="full" error={e.notes?.message}><Textarea rows={4} {...register('notes')} /></FormField>
          </FormGrid>
        </FormSection>
      </form>
    </Drawer>
  );
}

export function AssignLeadModal({ lead, onClose }: { lead: Lead; onClose: () => void }) {
  const toast = useToast();
  const qc = useQueryClient();
  const users = useUserOptions([lead.assignee]);
  const [userId, setUserId] = useState(lead.assigned_to ? String(lead.assigned_to) : '');
  const [error, setError] = useState<string | null>(null);
  const m = useMutation({
    mutationFn: () => salesApi.assignLead(lead.id, Number(userId)),
    onSuccess: (l) => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('leads') });
      toast.success(`Assigned to ${l.assignee?.name ?? 'user'}`);
      onClose();
    },
    onError: (e: ApiError) => setError(flattenErrors(e).fields.user_id ?? e.message),
  });
  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title={`Assign ${lead.name}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={() => m.mutate()} loading={m.isPending} disabled={!userId}>Assign</Button>
        </>
      }
    >
      <FormField label="Assignee" required error={error ?? undefined} hint={users.limited ? 'Only you are listed (no access to the user directory).' : undefined}>
        <Select value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="Choose a user" options={users.options} />
      </FormField>
    </Modal>
  );
}

export function ConvertLeadModal({ lead, onClose }: { lead: Lead; onClose: () => void }) {
  const router = useRouter();
  const toast = useToast();
  const qc = useQueryClient();
  const stages = useStages();
  const openStages = (stages.data ?? []).filter((s) => !s.is_won && !s.is_lost);
  const [createOpp, setCreateOpp] = useState(true);
  const [stageId, setStageId] = useState('');
  const [error, setError] = useState<string | null>(null);
  const effectiveStage = stageId || (openStages[0] ? String(openStages[0].id) : '');
  const m = useMutation({
    mutationFn: () => salesApi.convertLead(lead.id, { create_opportunity: createOpp, stage_id: createOpp && effectiveStage ? Number(effectiveStage) : null }),
    onSuccess: (r) => {
      void qc.invalidateQueries({ predicate: (q) => ['leads', 'customers', 'opportunities'].some((k) => q.queryKey.includes(k)) });
      toast.success(r.customer_created ? `Customer ${r.customer.name} created` : `Linked to existing customer ${r.customer.name}`, r.opportunity ? `Opportunity “${r.opportunity.title}” added to the pipeline.` : undefined);
      onClose();
      router.push(`/sales/customers/${r.customer.id}`);
    },
    onError: (e: ApiError) => setError(e.message),
  });

  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title={`Convert ${lead.name}`}
      description="Creates a customer from this lead (or links an existing one with the same email)."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={() => m.mutate()} loading={m.isPending} disabled={createOpp && !effectiveStage && !stages.isLoading}>Convert lead</Button>
        </>
      }
    >
      {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
      <Checkbox checked={createOpp} onChange={(e) => setCreateOpp(e.target.checked)} label="Also create an opportunity" description="Adds a deal to the pipeline using the lead’s estimated value." />
      {createOpp && (
        <div style={{ marginTop: 16 }}>
          <FormField label="Pipeline stage" required>
            <Select value={effectiveStage} onChange={(e) => setStageId(e.target.value)} disabled={stages.isLoading} placeholder={stages.isLoading ? 'Loading stages…' : 'Choose a stage'} options={openStages.map((s) => ({ value: String(s.id), label: `${s.name} (${s.probability}%)` }))} />
          </FormField>
        </div>
      )}
    </Modal>
  );
}
