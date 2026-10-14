'use client';

import { use } from 'react';
import { DocumentPage } from '@/features/finance/receivables/DocumentPage';

export default function InvoiceDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <DocumentPage key={id} kind="invoice" id={id} />;
}
