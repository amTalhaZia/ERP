import { redirect } from 'next/navigation';

/**
 * Legacy notification links pointed at /hr/leave/{id}, which never existed. The
 * leave page highlights `?request=`; users without the approvals tab fall back
 * to "My leave" automatically.
 */
export default async function LegacyLeaveRequestRedirect({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  redirect(`/hr/leave?tab=approvals&request=${encodeURIComponent(id)}`);
}
