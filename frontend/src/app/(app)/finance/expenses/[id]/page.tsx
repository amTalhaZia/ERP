'use client';

import { use } from 'react';
import { ExpenseClaimPage } from '@/features/finance/receivables/ExpenseClaimPage';

export default function ExpenseClaimDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <ExpenseClaimPage key={id} id={id} />;
}
