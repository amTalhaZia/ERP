'use client';

import { use } from 'react';
import { Alert } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { RunDetail } from '@/features/payroll/RunDetail';

export default function PayrollRunPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const can = useCan();
  if (!can('payroll.view')) return <Alert variant="warning">You don’t have permission to view payroll runs.</Alert>;
  return <RunDetail key={id} id={id} />;
}
