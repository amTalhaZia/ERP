'use client';

import { useQuery } from '@tanstack/react-query';
import { Network } from 'lucide-react';
import { Alert, Button, EmptyState, ErrorState, PageHeader, SkeletonCard } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { hrApi, useCompanyId } from '@/features/hr/api';
import { OrgChart } from '@/features/hr/OrgChart';
import styles from '@/features/hr/hr.module.scss';

export default function OrgChartPage() {
  const can = useCan();
  const companyId = useCompanyId();
  const q = useQuery({ queryKey: [companyId, 'employees', 'org-chart'], queryFn: hrApi.orgChart, enabled: can('employees.view') });

  if (!can('employees.view')) return <Alert variant="warning">You don’t have permission to view the org chart.</Alert>;

  return (
    <div className={styles.stack}>
      <PageHeader
        title="Org chart"
        description="Reporting lines for everyone currently employed. Set an employee's manager on their profile to place them here."
        actions={<Button variant="secondary" href="/hr/employees">Employee list</Button>}
      />
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !q.data ? (
        <SkeletonCard lines={6} />
      ) : q.data.length === 0 ? (
        <EmptyState icon={<Network size={22} />} title="No employees yet" description="Add employees and set their managers to build the chart." />
      ) : (
        <OrgChart key={q.dataUpdatedAt} roots={q.data} />
      )}
    </div>
  );
}
