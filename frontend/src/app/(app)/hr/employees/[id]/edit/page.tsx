'use client';

import { use } from 'react';
import { Alert, ErrorState, PageHeader, SkeletonCard } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { employees } from '@/features/hr/api';
import { EmployeeForm } from '@/features/hr/EmployeeForm';
import styles from '@/features/hr/hr.module.scss';

export default function EditEmployeePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const can = useCan();
  const q = employees.useOne(id);

  if (!can('employees.update')) return <Alert variant="warning">You don’t have permission to edit employees.</Alert>;
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const e = q.data;

  return (
    <div className={styles.stack}>
      <PageHeader title={`Edit ${e.name}`} description={e.employee_code} backHref={`/hr/employees/${e.id}`} backLabel={e.name} />
      <EmployeeForm key={e.updated_at ?? e.id} employee={e} />
    </div>
  );
}
