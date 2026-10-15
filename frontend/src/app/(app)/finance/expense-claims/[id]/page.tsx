import { redirect } from 'next/navigation';

/** Legacy notification links pointed at /finance/expense-claims/{id}; the page lives at /finance/expenses/{id}. */
export default async function LegacyExpenseClaimRedirect({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  redirect(`/finance/expenses/${encodeURIComponent(id)}`);
}
