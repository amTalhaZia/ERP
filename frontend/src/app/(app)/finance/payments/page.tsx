'use client';

import { Suspense } from 'react';
import { PaymentsListPage } from '@/features/finance/receivables/PaymentsListPage';

export default function PaymentsPage() {
  return (
    <Suspense>
      <PaymentsListPage type="payment" />
    </Suspense>
  );
}
