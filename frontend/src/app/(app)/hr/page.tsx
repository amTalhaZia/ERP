import { redirect } from 'next/navigation';

/** The sidebar group links to /hr; land on the employee list. */
export default function HrIndexPage() {
  redirect('/hr/employees');
}
