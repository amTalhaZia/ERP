import { redirect } from 'next/navigation';

/** The sidebar group links to /inventory; land on stock levels. */
export default function InventoryIndexPage() {
  redirect('/inventory/stock');
}
