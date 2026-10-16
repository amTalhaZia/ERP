'use client';

import { useState } from 'react';
import { ArrowRight, UserX } from 'lucide-react';
import { Alert, Button, FormField, Modal, Select } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { employees, useDirectory, useInvalidate, type Employee } from '@/features/hr/api';
import styles from '@/features/hr/hr.module.scss';

/**
 * Change who an employee reports to, from their profile. Nothing is saved on a
 * single click: picking someone shows a before → after preview that must be
 * confirmed, and removing the manager needs its own explicit confirmation.
 */
export function ManagerDialog({ open, employee, onClose }: { open: boolean; employee: Employee; onClose: () => void }) {
  const directory = useDirectory(open);
  const invalidate = useInvalidate();
  const update = employees.useUpdate();
  const [choice, setChoice] = useState('');
  const [confirmRemove, setConfirmRemove] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const current = employee.manager ?? null;
  const options = (directory.data ?? [])
    .filter((d) => d.id !== employee.id && d.id !== current?.id)
    .map((d) => ({ value: String(d.id), label: `${d.name}${d.designation ? ` · ${d.designation}` : ''}${d.department ? ` · ${d.department}` : ''}` }));
  const picked = (directory.data ?? []).find((d) => String(d.id) === choice) ?? null;

  const save = async (managerId: number | null) => {
    setError(null);
    try {
      await update.mutateAsync({ id: employee.id, body: { manager_id: managerId } });
      void invalidate('employees');
      onClose();
    } catch (e) {
      const err = e as ApiError;
      setError(err.errors?.manager_id?.[0] ?? err.message);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={`Who does ${employee.first_name} report to?`}
      description="Their leave and expense requests go to this person for approval."
      footer={
        confirmRemove ? (
          <>
            <Button variant="secondary" onClick={() => setConfirmRemove(false)}>Keep {current?.name}</Button>
            <Button variant="danger" loading={update.isPending} onClick={() => void save(null)}>Yes, remove manager</Button>
          </>
        ) : (
          <>
            <Button variant="secondary" onClick={onClose}>Cancel</Button>
            <Button disabled={!picked} loading={update.isPending} onClick={() => picked && void save(picked.id)}>Confirm change</Button>
          </>
        )
      }
    >
      <div className={styles.stack}>
        {error && <Alert variant="danger">{error}</Alert>}

        {confirmRemove ? (
          <Alert variant="warning" icon={<UserX size={16} />} title={`Remove ${current?.name} as manager?`}>
            {employee.name} will have nobody to report to: they move to the top of the org chart and their requests will need an HR manager to approve.
          </Alert>
        ) : (
          <>
            <FormField label="New manager" hint={directory.isLoading ? 'Loading people…' : 'Only current employees are listed.'}>
              <Select aria-label="New manager" placeholder="Choose a person" value={choice} onChange={(ev) => setChoice(ev.target.value)} options={options} />
            </FormField>

            <div className={styles.managerPreview} aria-live="polite">
              <div>
                <span>Now</span>
                <strong>{current?.name ?? 'Nobody'}</strong>
              </div>
              <ArrowRight size={16} aria-hidden />
              <div data-new={picked ? 'true' : undefined}>
                <span>After saving</span>
                <strong>{picked?.name ?? '—'}</strong>
              </div>
            </div>

            {current && (
              <Button variant="ghost" size="sm" leftIcon={<UserX size={14} />} onClick={() => setConfirmRemove(true)} style={{ alignSelf: 'flex-start' }}>
                Remove manager instead
              </Button>
            )}
          </>
        )}
      </div>
    </Modal>
  );
}
