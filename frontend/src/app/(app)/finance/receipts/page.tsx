'use client';

import { Suspense } from 'react';
import { PaymentsListPage } from '@/features/finance/receivables/PaymentsListPage';

export default function ReceiptsPage() {
  return (
    <Suspense>
      <PaymentsListPage type="receipt" />
    </Suspense>
  );
}
