'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { Building2, FolderTree, MapPin } from 'lucide-react';
import { Avatar, EmptyState, SearchInput } from '@/components/ui';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import type { OrgNode } from './api';
import styles from './BranchOrgView.module.scss';

type Row = Omit<OrgNode, 'children'>;
interface Person extends Row {
  depth: number;
}
interface DeptGroup {
  key: string;
  name: string;
  people: Person[];
}
interface BranchGroup {
  key: string;
  id: number | null;
  name: string;
  depts: DeptGroup[];
  total: number;
}

function flatten(nodes: OrgNode[], into: Row[] = []): Row[] {
  nodes.forEach(({ children, ...row }) => {
    into.push(row);
    flatten(children, into);
  });
  return into;
}

/** Order people so each manager is followed by the people reporting to them (within the same department). */
function withReportingDepth(rows: Row[]): Person[] {
  const ids = new Set(rows.map((r) => r.id));
  const kids = new Map<number, Row[]>();
  const tops: Row[] = [];
  rows.forEach((r) => {
    if (r.manager_id !== null && ids.has(r.manager_id)) kids.set(r.manager_id, [...(kids.get(r.manager_id) ?? []), r]);
    else tops.push(r);
  });
  const out: Person[] = [];
  const walk = (list: Row[], depth: number) =>
    list.forEach((r) => {
      out.push({ ...r, depth });
      walk(kids.get(r.id) ?? [], depth + 1);
    });
  // Most-senior first: people with the biggest teams lead each department.
  const size = (r: Row): number => (kids.get(r.id) ?? []).reduce((n, k) => n + 1 + size(k), 0);
  walk([...tops].sort((a, b) => size(b) - size(a) || a.name.localeCompare(b.name)), 0);
  return out;
}

/**
 * Company → Branch → Department → people. Answers "who works where" at a
 * glance; reporting lines are kept as indentation inside each department.
 */
export function BranchOrgView({ roots }: { roots: OrgNode[] }) {
  const branchList = useAppSelector(selectBranches);
  const selectedBranch = useAppSelector((s) => s.ui.branchId);
  const [term, setTerm] = useState('');

  const groups = useMemo<BranchGroup[]>(() => {
    const t = term.trim().toLowerCase();
    const rows = flatten(roots).filter(
      (r) => (selectedBranch === null || r.branch_id === selectedBranch) && (!t || [r.name, r.designation ?? '', r.department ?? ''].some((v) => v.toLowerCase().includes(t))),
    );
    const names = new Map(branchList.map((b) => [b.id, b.name]));
    const byBranch = new Map<string, Row[]>();
    rows.forEach((r) => {
      const k = r.branch_id === null ? 'none' : String(r.branch_id);
      byBranch.set(k, [...(byBranch.get(k) ?? []), r]);
    });
    return [...byBranch.entries()]
      .map(([key, list]) => {
        const byDept = new Map<string, Row[]>();
        list.forEach((r) => {
          const k = r.department ?? '';
          byDept.set(k, [...(byDept.get(k) ?? []), r]);
        });
        const depts = [...byDept.entries()]
          .map(([name, people]) => ({ key: name || 'none', name: name || 'No department', people: withReportingDepth(people) }))
          .sort((a, b) => (a.key === 'none' ? 1 : b.key === 'none' ? -1 : a.name.localeCompare(b.name)));
        const id = key === 'none' ? null : Number(key);
        return { key, id, name: id === null ? 'No branch set' : (names.get(id) ?? `Branch #${id}`), depts, total: list.length };
      })
      .sort((a, b) => (a.id === null ? 1 : b.id === null ? -1 : b.total - a.total));
  }, [roots, branchList, selectedBranch, term]);

  const people = groups.reduce((n, g) => n + g.total, 0);

  return (
    <div className={styles.wrap}>
      <div className={styles.toolbar}>
        <SearchInput placeholder="Find a person, title or department" aria-label="Find a person, title or department" onDebouncedChange={(v: string) => setTerm(v)} />
        <span className={styles.meta} aria-live="polite">
          {people} {people === 1 ? 'person' : 'people'} · {groups.length} {groups.length === 1 ? 'branch' : 'branches'}
          {selectedBranch !== null && ' (filtered by the branch selector at the top)'}
        </span>
      </div>

      {groups.length === 0 ? (
        <EmptyState icon={<FolderTree size={22} />} title="Nobody matches" description="Try another name, or pick “All branches” at the top." />
      ) : (
        groups.map((b) => (
          <section key={b.key} className={styles.branch} aria-labelledby={`branch-${b.key}`}>
            <header className={styles.branchHead}>
              <span className={styles.branchIcon} aria-hidden>{b.id === null ? <MapPin size={18} /> : <Building2 size={18} />}</span>
              <div>
                <h2 id={`branch-${b.key}`}>{b.name}</h2>
                <span>
                  {b.total} {b.total === 1 ? 'person' : 'people'} · {b.depts.length} {b.depts.length === 1 ? 'department' : 'departments'}
                </span>
              </div>
            </header>
            <div className={styles.depts}>
              {b.depts.map((d) => (
                <article key={d.key} className={styles.dept}>
                  <header className={styles.deptHead}>
                    <strong>{d.name}</strong>
                    <span>{d.people.length}</span>
                  </header>
                  <ul className={styles.people}>
                    {d.people.map((p) => (
                      <li key={p.id} style={{ paddingLeft: `calc(var(--space-3) + ${Math.min(p.depth, 4) * 20}px)` }} data-lead={p.depth === 0 && d.people.length > 1 ? 'true' : undefined}>
                        {p.depth > 0 && <span className={styles.elbow} aria-hidden />}
                        <Link href={`/hr/employees/${p.id}`} className={styles.person}>
                          <Avatar name={p.name} src={p.photo_url ?? undefined} size="sm" />
                          <span className={styles.who}>
                            <strong>{p.name}</strong>
                            <span>{p.designation ?? 'No job title'}</span>
                          </span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                </article>
              ))}
            </div>
          </section>
        ))
      )}
    </div>
  );
}
