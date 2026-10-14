'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AnimatePresence, motion } from 'framer-motion';
import { Bell, CheckCheck, Trash2 } from 'lucide-react';
import { Button, Card, EmptyState, ErrorState, IconButton, PageHeader, Pagination, SegmentedControl, Skeleton, paginationFromMeta } from '@/components/ui';
import { formatRelative } from '@/lib/format';
import { notificationsApi } from '@/features/access/api';
import styles from './notifications.module.scss';

export default function NotificationsPage() {
  const qc = useQueryClient();
  const [filter, setFilter] = useState<'all' | 'unread'>('all');
  const [page, setPage] = useState(1);
  const params = { page, per_page: 20, ...(filter === 'unread' ? { 'filter[read]': 'false' } : {}) };
  const q = useQuery({ queryKey: ['notifications', 'list', params], queryFn: () => notificationsApi.list(params) });
  const invalidate = () => void qc.invalidateQueries({ queryKey: ['notifications'] });
  const markRead = useMutation({ mutationFn: notificationsApi.markRead, onSuccess: invalidate });
  const markAll = useMutation({ mutationFn: notificationsApi.markAllRead, onSuccess: invalidate });
  const remove = useMutation({ mutationFn: notificationsApi.remove, onSuccess: invalidate });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 860 }}>
      <PageHeader
        title="Notifications"
        actions={
          <>
            <SegmentedControl aria-label="Filter" size="sm" value={filter} onChange={(v) => { setFilter(v); setPage(1); }} options={[{ value: 'all', label: 'All' }, { value: 'unread', label: 'Unread' }]} />
            <Button variant="secondary" leftIcon={<CheckCheck size={16} />} onClick={() => markAll.mutate()} loading={markAll.isPending}>Mark all read</Button>
          </>
        }
      />
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : q.isLoading ? (
        <Skeleton style={{ height: 320 }} />
      ) : !q.data?.data.length ? (
        <EmptyState icon={<Bell size={22} />} title={filter === 'unread' ? 'You’re all caught up' : 'No notifications yet'} description="Approvals, low-stock alerts and status changes will appear here." />
      ) : (
        <Card>
          <ul className={styles.list}>
            <AnimatePresence initial={false}>
              {q.data.data.map((n) => (
                <motion.li key={n.id} layout initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0, height: 0 }} className={n.is_read ? styles.read : styles.unread}>
                  <span className={styles.dot} aria-label={n.is_read ? 'Read' : 'Unread'} />
                  <div className={styles.body}>
                    {n.url ? (
                      <Link href={n.url} onClick={() => !n.is_read && markRead.mutate(n.id)} className={styles.title}>{n.title ?? n.type}</Link>
                    ) : (
                      <span className={styles.title}>{n.title ?? n.type}</span>
                    )}
                    {n.body && <p className={styles.text}>{n.body}</p>}
                    <span className={styles.time}>{formatRelative(n.created_at)}</span>
                  </div>
                  <div className={styles.actions}>
                    {!n.is_read && <IconButton icon={<CheckCheck size={16} />} aria-label="Mark as read" onClick={() => markRead.mutate(n.id)} size="sm" />}
                    <IconButton icon={<Trash2 size={16} />} aria-label="Delete notification" onClick={() => remove.mutate(n.id)} size="sm" />
                  </div>
                </motion.li>
              ))}
            </AnimatePresence>
          </ul>
        </Card>
      )}
      {q.data?.meta && q.data.meta.pagination.last_page > 1 && <Pagination {...paginationFromMeta(q.data.meta.pagination)} onPageChange={setPage} />}
    </div>
  );
}
