'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Alert, SkeletonCard } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';

/** The sidebar group links to /payroll: land on runs, or on self-service payslips. */
export default function PayrollIndexPage() {
  const router = useRouter();
  const can = useCan();
  const target = can('payroll.view') ? '/payroll/runs' : can('payslips.view.own') ? '/payroll/my-payslips' : can('payroll.manage') ? '/payroll/structures' : null;

  useEffect(() => {
    if (target) router.replace(target);
  }, [router, target]);

  if (!target) return <Alert variant="warning">You don’t have access to payroll.</Alert>;
  return <SkeletonCard />;
}
