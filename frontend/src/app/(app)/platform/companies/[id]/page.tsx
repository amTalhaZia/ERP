'use client';

import { use, useMemo, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { Building2, Crown, GitBranch, Lock, Mail, Power, PowerOff, ShieldCheck, Users, UserSquare2 } from 'lucide-react';
import {
  Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, MetricCard, PageHeader, SearchInput, Skeleton, StatusBadge,
} from '@/components/ui';
import { formatDate, formatRelative } from '@/lib/format';
import { platformApi, platformCompanies, type CompanyMember } from '@/features/platform/api';
import { ROLE_META, ROLE_ORDER, primaryRoleSlug, roleRank } from '@/features/platform/roleOrder';
import styles from './company.module.scss';

export default function PlatformCompanyPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const companyId = Number(id);
  const company = platformCompanies.useOne(companyId);
  const members = useQuery({ queryKey: ['platform', 'company-members', companyId], queryFn: () => platformApi.members(companyId), enabled: Number.isFinite(companyId) });
  const activate = platformCompanies.useAction('activate', { success: 'Company activated' });
  const deactivate = platformCompanies.useAction('deactivate', { success: 'Company deactivated' });
  const [confirmOff, setConfirmOff] = useState(false);
  const [filter, setFilter] = useState('');

  // Group members by their highest role, ordered from most to least access.
  const groups = useMemo(() => {
    const term = filter.trim().toLowerCase();
    const list = (members.data ?? []).filter((m) => !term || m.name.toLowerCase().includes(term) || m.email.toLowerCase().includes(term));
    const map = new Map<string, CompanyMember[]>();
    for (const m of list) {
      const key = primaryRoleSlug(m.roles.map((r) => r.slug)) ?? 'none';
      map.set(key, [...(map.get(key) ?? []), m]);
    }
    return [...map.entries()].sort(([a], [b]) => roleRank(a === 'none' ? [] : [a]) - roleRank(b === 'none' ? [] : [b]));
  }, [members.data, filter]);

  if (company.error) return <ErrorState error={company.error} onRetry={() => void company.refetch()} />;
  if (!company.data) return <Skeleton style={{ height: 420 }} />;
  const c = company.data;
  const inactiveMembers = (members.data ?? []).filter((m) => !m.is_active).length;

  return (
    <div className={styles.page}>
      <PageHeader
        title={c.name}
        description={[c.legal_name, c.city, c.currency].filter(Boolean).join(' · ')}
        backHref="/platform/companies"
        backLabel="Companies"
        meta={
          <>
            <StatusBadge status={c.is_active ? 'active' : 'inactive'} />
            {c.is_demo && <Badge variant="warning">Demo</Badge>}
            <Badge variant="neutral">{c.code}</Badge>
          </>
        }
        actions={
          c.is_active ? (
            <Button variant="secondary" leftIcon={<PowerOff size={16} />} onClick={() => setConfirmOff(true)}>Deactivate</Button>
          ) : (
            <Button leftIcon={<Power size={16} />} loading={activate.isPending} onClick={() => activate.mutate({ id: c.id })}>Activate</Button>
          )
        }
      />

      <Alert variant="info" icon={<Lock size={16} />}>
        Platform view shows <b>login accounts and roles only</b>. Employees’ HR records, salaries, invoices and other business data stay private to the company.
      </Alert>

      <div className={styles.stats}>
        <MetricCard label="Login users" icon={<Users size={18} />} value={c.users_count ?? 0} footer={inactiveMembers ? `${inactiveMembers} inactive` : 'All active'} />
        <MetricCard label="Employees (HR records)" icon={<UserSquare2 size={18} />} value={c.employees_count ?? 0} footer="Headcount only" />
        <MetricCard label="Branches" icon={<GitBranch size={18} />} value={c.branches_count ?? 0} />
        <MetricCard label="Created" icon={<Building2 size={18} />} value={formatDate(c.created_at)} footer={c.timezone} />
      </div>

      <Card>
        <CardHeader title={<span className={styles.titleIcon}><Crown size={16} /> Company owner</span>} description="Full access inside this company. Only an owner can add or remove other owners." />
        <CardBody>
          {c.owners?.length ? (
            <ul className={styles.owners}>
              {c.owners.map((o) => (
                <li key={o.id}>
                  <Avatar name={o.name} size="md" />
                  <div>
                    <strong>{o.name}</strong>
                    <a href={`mailto:${o.email}`}><Mail size={12} /> {o.email}</a>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <Alert variant="warning">This company has no owner. Use the company’s Users page (as an owner) or re-provision ownership.</Alert>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader
          title={<span className={styles.titleIcon}><ShieldCheck size={16} /> Users by role</span>}
          description="Grouped from most to least access."
          actions={<SearchInput placeholder="Search users" value={filter} onValueChange={setFilter} aria-label="Search users" size="sm" />}
        />
        <CardBody padding="none">
          {members.error ? (
            <div style={{ padding: 16 }}><ErrorState error={members.error} onRetry={() => void members.refetch()} /></div>
          ) : members.isLoading ? (
            <Skeleton style={{ height: 280, margin: 16, width: 'auto' }} />
          ) : groups.length === 0 ? (
            <EmptyState size="sm" title={filter ? 'No matching users' : 'No users yet'} />
          ) : (
            groups.map(([slug, list]) => {
              const meta = ROLE_META[slug];
              return (
                <section key={slug} className={styles.group}>
                  <header className={styles.groupHead}>
                    <span className={styles.rank}>{slug === 'none' ? '–' : (ROLE_ORDER as readonly string[]).indexOf(slug) + 1 || '★'}</span>
                    <div>
                      <strong>{meta?.label ?? list[0]?.roles.find((r) => r.slug === slug)?.name ?? 'No role'}</strong>
                      <span>{meta?.hint ?? (slug === 'none' ? 'Member without a role — no access' : 'Custom role')}</span>
                    </div>
                    <Badge size="sm" variant="neutral">{list.length}</Badge>
                  </header>
                  <ul className={styles.members}>
                    {list.map((m) => (
                      <li key={m.id} className={m.is_active ? undefined : styles.inactive}>
                        <Avatar name={m.name} src={m.avatar_url ?? undefined} size="sm" />
                        <div className={styles.who}>
                          <strong>{m.name}</strong>
                          <span>{m.email}</span>
                        </div>
                        <div className={styles.tags}>
                          {m.roles.length > 1 && m.roles.filter((r) => r.slug !== slug).map((r) => <Badge key={r.slug} size="sm" variant="primary">+ {r.name}</Badge>)}
                          {m.branch_restricted && <Badge size="sm" variant="warning">Branch-limited</Badge>}
                          {m.two_factor_enabled && <Badge size="sm" variant="success">2FA</Badge>}
                          {!m.is_active && <Badge size="sm" variant="neutral">Inactive</Badge>}
                        </div>
                        <span className={styles.seen}>{m.last_login_at ? `Seen ${formatRelative(m.last_login_at)}` : 'Never signed in'}</span>
                      </li>
                    ))}
                  </ul>
                </section>
              );
            })
          )}
        </CardBody>
      </Card>

      <p className={styles.footnote}>
        To change a user’s role, the company’s owner or admin uses <b>Users &amp; roles → Users</b> inside the company. Platform staff can deactivate an account everywhere from{' '}
        <Link href="/platform/users">Platform users</Link>.
      </p>

      <ConfirmDialog
        open={confirmOff}
        onClose={() => setConfirmOff(false)}
        title={`Deactivate ${c.name}?`}
        description="Members will no longer be able to access this company until it is reactivated. No data is deleted."
        destructive
        confirmLabel="Deactivate"
        onConfirm={async () => {
          await deactivate.mutateAsync({ id: c.id });
          setConfirmOff(false);
        }}
      />
    </div>
  );
}
