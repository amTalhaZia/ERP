'use client';

import { Suspense } from 'react';
import { DocumentListPage } from '@/features/finance/receivables/DocumentListPage';

export default function BillsPage() {
  return (
    <Suspense>
      <DocumentListPage kind="bill" />
    </Suspense>
  );
}
