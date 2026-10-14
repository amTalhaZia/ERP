'use client';

import { Suspense } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { Alert, PageHeader, Tab, TabList, TabPanel, Tabs } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { PayComponentsTab } from '@/features/payroll/setup/PayComponents';
import { SalaryStructuresTab } from '@/features/payroll/setup/SalaryStructures';
import { TaxTablesTab } from '@/features/payroll/setup/TaxTables';
import { EmployeeSalariesTab } from '@/features/payroll/setup/EmployeeSalaries';
import styles from '@/features/payroll/payroll.module.scss';

const TABS = ['components', 'structures', 'tax-tables', 'salaries'] as const;
type TabKey = (typeof TABS)[number];

function StructuresInner() {
  const can = useCan();
  const params = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const raw = params.get('tab');
  const tab: TabKey = (TABS as readonly string[]).includes(raw ?? '') ? (raw as TabKey) : 'components';
  const canManage = can('payroll.manage');

  if (!can(['payroll.view', 'payroll.manage'], 'any')) {
    return <Alert variant="warning">You don’t have permission to view payroll setup.</Alert>;
  }

  return (
    <div className={styles.stack}>
      <PageHeader
        title="Payroll setup"
        description={canManage ? 'Pay components, salary structures, tax tables and employee salaries used to calculate payroll.' : 'Read-only: managing payroll setup needs the “Manage salary structures” permission.'}
      />
      <Tabs value={tab} onValueChange={(v) => router.replace(v === 'components' ? pathname : `${pathname}?tab=${v}`, { scroll: false })}>
        <TabList>
          <Tab value="components">Pay components</Tab>
          <Tab value="structures">Salary structures</Tab>
          <Tab value="tax-tables">Tax tables</Tab>
          <Tab value="salaries">Employee salaries</Tab>
        </TabList>
        <TabPanel value="components" className={styles.tabBody}><PayComponentsTab canManage={canManage} /></TabPanel>
        <TabPanel value="structures" className={styles.tabBody}><SalaryStructuresTab canManage={canManage} /></TabPanel>
        <TabPanel value="tax-tables" className={styles.tabBody}><TaxTablesTab canManage={canManage} /></TabPanel>
        <TabPanel value="salaries" className={styles.tabBody}><EmployeeSalariesTab canManage={canManage} /></TabPanel>
      </Tabs>
    </div>
  );
}

export default function PayrollStructuresPage() {
  return (
    <Suspense>
      <StructuresInner />
    </Suspense>
  );
}
