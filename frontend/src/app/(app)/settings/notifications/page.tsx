'use client';

import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardBody, CardHeader, ErrorState, PageHeader, Skeleton, Switch, useToast } from '@/components/ui';
import { api, get } from '@/lib/api/client';
import { humanize } from '@/lib/format';
import styles from '@/components/crud/crud.module.scss';

type Channels = { in_app: boolean; email: boolean };
interface Prefs {
  defaults: Channels;
  known_types: string[] | Record<string, string>;
  preferences: Record<string, Channels>;
}

export default function NotificationSettingsPage() {
  const qc = useQueryClient();
  const toast = useToast();
  const q = useQuery({ queryKey: ['notification-preferences'], queryFn: () => get<{ data: Prefs }>('/notifications/preferences').then((r) => r.data) });
  const save = useMutation({
    mutationFn: (preferences: Record<string, Channels>) => api.put<{ data: Prefs }>('/notifications/preferences', { preferences }).then((r) => r.data.data),
    onSuccess: (d) => {
      qc.setQueryData(['notification-preferences'], d);
      toast.success('Preferences saved');
    },
    onError: () => toast.error('Could not save preferences'),
  });

  const types = useMemo(() => {
    const k = q.data?.known_types;
    if (!k) return [] as { type: string; label: string }[];
    return Array.isArray(k) ? k.map((t) => ({ type: t, label: humanize(t.replace(/\./g, ' ')) })) : Object.entries(k).map(([type, label]) => ({ type, label }));
  }, [q.data]);

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;

  const current = (type: string): Channels => q.data?.preferences[type] ?? q.data?.defaults ?? { in_app: true, email: true };
  const toggle = (type: string, channel: keyof Channels, value: boolean) => save.mutate({ [type]: { ...current(type), [channel]: value } });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 860 }}>
      <PageHeader title="Notifications" description="Choose how you hear about approvals, assignments and alerts. Security emails (password reset, verification) are always sent." />
      <Card>
        <CardHeader title="Delivery preferences" />
        <CardBody padding="none">
          {!q.data ? (
            <Skeleton style={{ height: 240, margin: 16 }} />
          ) : (
            <div className={styles.tableScroll} style={{ margin: 0 }}>
              <table className={styles.lines}>
                <thead><tr><th>Event</th><th>In app</th><th>Email</th></tr></thead>
                <tbody>
                  {types.map(({ type, label }) => (
                    <tr key={type}>
                      <td>{label}</td>
                      <td><Switch size="sm" aria-label={`${label} in app`} checked={current(type).in_app} onCheckedChange={(v) => toggle(type, 'in_app', v)} /></td>
                      <td><Switch size="sm" aria-label={`${label} by email`} checked={current(type).email} onCheckedChange={(v) => toggle(type, 'email', v)} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
