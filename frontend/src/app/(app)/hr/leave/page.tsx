'use client';

import { Suspense } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { Alert, PageHeader, Tab, TabList, TabPanel, Tabs } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { leaveRequests } from '@/features/hr/api';
import { BalancesAdmin, LeaveTypesAdmin, MyLeave, RequestsTable } from '@/features/hr/Leave';
import styles from '@/features/hr/hr.module.scss';

function LeaveInner() {
  const can = useCan();
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams();
  const canRequest = can('leave.request');
  const canApprove = can('leave.approve');
  const canManage = can('leave.manage');
  const canViewAll = can(['leave.view', 'leave.approve', 'leave.manage'], 'any');
  const pending = leaveRequests.useList({ 'filter[status]': 'pending', per_page: 1 }, { enabled: canApprove });

  const tabs = [
    { value: 'mine', label: 'My leave', show: true },
    { value: 'approvals', label: 'Approvals', show: canApprove, count: pending.data?.meta.pagination.total || undefined },
    { value: 'all', label: 'All requests', show: canViewAll },
    { value: 'balances', label: 'Balances', show: canManage },
    { value: 'types', label: 'Leave types', show: canManage },
  ].filter((t) => t.show);
  const requested = search.get('tab') ?? '';
  const tab = tabs.some((t) => t.value === requested) ? requested : 'mine';
  const setTab = (t: string) => {
    const next = new URLSearchParams();
    if (t !== 'mine') next.set('tab', t);
    router.replace(next.toString() ? `${pathname}?${next}` : pathname, { scroll: false });
  };

  if (!can(['leave.view', 'leave.request', 'leave.approve', 'leave.manage'], 'any')) {
    return <Alert variant="warning">You don’t have permission to view leave.</Alert>;
  }

  return (
    <div className={styles.stack}>
      <PageHeader title="Leave" description="Balances, requests and approvals." />
      <Tabs value={tab} onValueChange={setTab}>
        <TabList>
          {tabs.map((t) => <Tab key={t.value} value={t.value} count={t.count}>{t.label}</Tab>)}
        </TabList>
        <TabPanel value="mine"><div className={styles.tabBody}><MyLeave canRequest={canRequest} /></div></TabPanel>
        {canApprove && <TabPanel value="approvals"><div className={styles.tabBody}><RequestsTable mode="approvals" canApprove={canApprove} canManage={canManage} /></div></TabPanel>}
        {canViewAll && <TabPanel value="all"><div className={styles.tabBody}><RequestsTable mode="all" canApprove={canApprove} canManage={canManage} /></div></TabPanel>}
        {canManage && <TabPanel value="balances"><div className={styles.tabBody}><BalancesAdmin /></div></TabPanel>}
        {canManage && <TabPanel value="types"><div className={styles.tabBody}><LeaveTypesAdmin /></div></TabPanel>}
      </Tabs>
    </div>
  );
}

export default function LeavePage() {
  return (
    <Suspense>
      <LeaveInner />
    </Suspense>
  );
}
