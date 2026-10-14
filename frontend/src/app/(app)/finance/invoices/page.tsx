'use client';

import { Suspense } from 'react';
import { DocumentListPage } from '@/features/finance/receivables/DocumentListPage';

export default function InvoicesPage() {
  return (
    <Suspense>
      <DocumentListPage kind="invoice" />
    </Suspense>
  );
}
