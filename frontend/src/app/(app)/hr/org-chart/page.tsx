'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Building2, Network } from 'lucide-react';
import { Alert, Button, EmptyState, ErrorState, PageHeader, SegmentedControl, SkeletonCard } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { hrApi, useCompanyId } from '@/features/hr/api';
import { OrgChart } from '@/features/hr/OrgChart';
import { BranchOrgView } from '@/features/hr/BranchOrgView';
import styles from '@/features/hr/hr.module.scss';

type View = 'branches' | 'reporting';

export default function OrgChartPage() {
  const can = useCan();
  const companyId = useCompanyId();
  const q = useQuery({ queryKey: [companyId, 'employees', 'org-chart'], queryFn: hrApi.orgChart, enabled: can('employees.view') });
  const [view, setView] = useState<View>('branches');

  if (!can('employees.view')) return <Alert variant="warning">You don’t have permission to view the org chart.</Alert>;

  return (
    <div className={styles.stack}>
      <PageHeader
        title="Org chart"
        description={
          view === 'branches'
            ? 'Who works where: each branch, its departments and the people in them. Set branch, department and manager on an employee’s profile.'
            : 'Who reports to whom. Set an employee’s manager on their profile to place them here.'
        }
        actions={<Button variant="secondary" href="/hr/employees">Employee list</Button>}
      />
      <SegmentedControl<View>
        aria-label="Chart view"
        value={view}
        onChange={setView}
        options={[
          { value: 'branches', label: 'By branch', icon: <Building2 size={14} /> },
          { value: 'reporting', label: 'Reporting lines', icon: <Network size={14} /> },
        ]}
      />
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !q.data ? (
        <SkeletonCard lines={6} />
      ) : q.data.length === 0 ? (
        <EmptyState icon={<Network size={22} />} title="No employees yet" description="Add employees and set their managers to build the chart." />
      ) : view === 'branches' ? (
        <BranchOrgView roots={q.data} />
      ) : (
        <OrgChart key={q.dataUpdatedAt} roots={q.data} />
      )}
    </div>
  );
}
