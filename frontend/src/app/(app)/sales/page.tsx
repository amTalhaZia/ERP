import { redirect } from 'next/navigation';

/** /sales has no landing page of its own; send people to the customer list. */
export default function SalesIndexPage() {
  redirect('/sales/customers');
}
