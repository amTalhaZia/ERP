'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, DateInput, Drawer, FormField, FormGrid, Select, useToast } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { applyServerErrors } from '@/lib/crud/resource';
import { formatMoney } from '@/lib/format';
import { payrollApi, useBankAccountOptions, useRunMutation, type PayrollRun } from './api';
import { explainPayrollError, todayIso } from './components';
import styles from './payroll.module.scss';

const schema = z.object({
  bank_account_id: z.string().min(1, 'Choose the bank account the salaries were paid from'),
  paid_at: z.string().min(1, 'Paid date is required'),
});
type Values = z.infer<typeof schema>;

export function MarkPaidDrawer({ run, open, onClose }: { run: PayrollRun; open: boolean; onClose: () => void }) {
  const banks = useBankAccountOptions(open);
  const toast = useToast();
  const pay = useRunMutation((v: { bank_account_id: number; paid_at: string }) => payrollApi.pay(run.id, v));
  const [formError, setFormError] = useState<string | null>(null);
  const { handleSubmit, control, setError, register, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { bank_account_id: '', paid_at: todayIso() },
  });
  const e = formState.errors;
  const mismatched = banks.rows.filter((b) => b.currency !== run.currency).length;

  return (
    <Drawer open={open} onClose={onClose} title={`Mark ${run.number} as paid`} size="sm"
      description="Posts the salary payment: Dr accrued payroll / Cr the bank account’s GL account."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="mark-paid-form" loading={formState.isSubmitting}>Mark paid</Button>
        </>
      }>
      <form id="mark-paid-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        try {
          await pay.mutateAsync({ bank_account_id: Number(v.bank_account_id), paid_at: v.paid_at });
          toast.success(`${run.number} marked as paid`);
          onClose();
        } catch (err) {
          const x = err as ApiError;
          if (x.status === 422) setFormError(applyServerErrors(x, setError as never, ['bank_account_id', 'paid_at']));
          else setFormError(explainPayrollError(x));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <p className={styles.hint} style={{ marginBottom: 16 }}>
          Net pay of {formatMoney(run.totals.net, run.currency)} to {run.totals.employee_count} employee{run.totals.employee_count === 1 ? '' : 's'}.
        </p>
        <FormGrid columns={1}>
          <FormField label="Bank account" required error={e.bank_account_id?.message}
            hint={banks.query.isLoading ? 'Loading…' : banks.rows.length === 0 ? 'No active bank accounts — add one under Finance → Bank.' : mismatched ? `Run currency is ${run.currency}.` : undefined}>
            <Select placeholder="Select bank account" {...register('bank_account_id')} options={banks.options} />
          </FormField>
          <Controller control={control} name="paid_at" render={({ field }) => (
            <FormField label="Paid on" required error={e.paid_at?.message}>
              <DateInput value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />
            </FormField>
          )} />
        </FormGrid>
      </form>
    </Drawer>
  );
}
