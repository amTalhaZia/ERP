'use client';

import { Alert, PageHeader } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { EmployeeForm } from '@/features/hr/EmployeeForm';
import styles from '@/features/hr/hr.module.scss';

export default function NewEmployeePage() {
  const can = useCan();
  return (
    <div className={styles.stack}>
      <PageHeader title="New employee" description="The employee code is assigned automatically." backHref="/hr/employees" backLabel="Employees" />
      {can('employees.create') ? <EmployeeForm employee={null} /> : <Alert variant="warning">You don’t have permission to create employees.</Alert>}
    </div>
  );
}
