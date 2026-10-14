'use client';

import { useMemo, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { ChevronDown, ChevronRight, Minus, Plus, RotateCcw } from 'lucide-react';
import { Avatar, Button, IconButton, SearchInput } from '@/components/ui';
import type { OrgNode } from './api';
import { cn } from '@/lib/cn';
import styles from './hr.module.scss';

function highlight(text: string, term: string): ReactNode {
  if (!term) return text;
  const i = text.toLowerCase().indexOf(term.toLowerCase());
  if (i < 0) return text;
  return (
    <>
      {text.slice(0, i)}
      <mark className={styles.hl}>{text.slice(i, i + term.length)}</mark>
      {text.slice(i + term.length)}
    </>
  );
}

const matches = (n: OrgNode, term: string) =>
  !!term && [n.name, n.designation ?? '', n.employee_code, n.department ?? ''].some((v) => v.toLowerCase().includes(term.toLowerCase()));

/** Ids of every node that is, or has a descendant that is, a search match. */
function matchPaths(nodes: OrgNode[], term: string, into = new Set<number>()): Set<number> {
  const walk = (n: OrgNode): boolean => {
    let hit = matches(n, term);
    for (const c of n.children) if (walk(c)) hit = true;
    if (hit) into.add(n.id);
    return hit;
  };
  nodes.forEach(walk);
  return into;
}

function countAll(nodes: OrgNode[]): number {
  return nodes.reduce((a, n) => a + 1 + countAll(n.children), 0);
}

function allIds(nodes: OrgNode[], into: number[] = []): number[] {
  nodes.forEach((n) => {
    if (n.children.length) into.push(n.id);
    allIds(n.children, into);
  });
  return into;
}

function Node({
  node, term, collapsed, onToggle, onPath, depth,
}: { node: OrgNode; term: string; collapsed: Set<number>; onToggle: (id: number) => void; onPath: Set<number>; depth: number }) {
  const hasChildren = node.children.length > 0;
  // While searching, branches leading to a match open automatically.
  const open = hasChildren && (term ? onPath.has(node.id) || !collapsed.has(node.id) : !collapsed.has(node.id));
  const isMatch = matches(node, term);
  const groupId = `org-${node.id}`;

  return (
    <li>
      <div className={styles.nodeRow}>
        {hasChildren ? (
          <button
            type="button"
            className={styles.toggle}
            aria-expanded={open}
            aria-controls={groupId}
            aria-label={`${open ? 'Collapse' : 'Expand'} ${node.name}'s team (${node.children.length})`}
            onClick={() => onToggle(node.id)}
          >
            {open ? <ChevronDown size={14} aria-hidden /> : <ChevronRight size={14} aria-hidden />}
          </button>
        ) : (
          <span className={styles.toggleSpacer} aria-hidden />
        )}
        <Link
          href={`/hr/employees/${node.id}`}
          className={styles.node}
          data-match={isMatch ? 'true' : undefined}
          data-dim={term && !onPath.has(node.id) ? 'true' : undefined}
          aria-label={`${node.name}${node.designation ? `, ${node.designation}` : ''}${hasChildren ? `, ${node.children.length} direct reports` : ''}`}
        >
          <Avatar name={node.name} src={node.photo_url ?? undefined} size="sm" />
          <span className={styles.nodeText}>
            <strong>{highlight(node.name, term)}</strong>
            <span>{node.designation ? highlight(node.designation, term) : 'No designation'}{node.department ? ` · ${node.department}` : ''}</span>
          </span>
          {hasChildren && <span className={styles.count} aria-hidden>{node.children.length}</span>}
        </Link>
      </div>
      {open && (
        <ul id={groupId} aria-label={`Reports to ${node.name}`}>
          {node.children.map((c) => (
            <Node key={c.id} node={c} term={term} collapsed={collapsed} onToggle={onToggle} onPath={onPath} depth={depth + 1} />
          ))}
        </ul>
      )}
    </li>
  );
}

/**
 * Reporting-line tree. Nested lists with disclosure buttons (keyboard and
 * screen-reader friendly); renders top-down on desktop, indented on mobile.
 */
export function OrgChart({ roots }: { roots: OrgNode[] }) {
  // Collapse from the third level down so large companies open readable.
  const [collapsed, setCollapsed] = useState<Set<number>>(() => {
    const s = new Set<number>();
    const walk = (nodes: OrgNode[], depth: number) => nodes.forEach((n) => {
      if (depth >= 1 && n.children.length) s.add(n.id);
      walk(n.children, depth + 1);
    });
    walk(roots, 0);
    return s;
  });
  const [term, setTerm] = useState('');
  const [zoom, setZoom] = useState(1);
  const onPath = useMemo(() => matchPaths(roots, term.trim()), [roots, term]);
  const matchCount = useMemo(() => {
    let n = 0;
    const walk = (nodes: OrgNode[]) => nodes.forEach((x) => { if (matches(x, term.trim())) n++; walk(x.children); });
    walk(roots);
    return n;
  }, [roots, term]);

  const toggle = (id: number) => setCollapsed((prev) => {
    const next = new Set(prev);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    return next;
  });

  return (
    <div className={styles.stack}>
      <div className={styles.between}>
        <div className={styles.orgToolbar}>
          <SearchInput
            placeholder="Find a person or title"
            aria-label="Find a person or title"
            onDebouncedChange={(v: string) => setTerm(v)}
            style={{ minWidth: 240 }}
          />
          <span className={styles.small} aria-live="polite">
            {term.trim() ? `${matchCount} match${matchCount === 1 ? '' : 'es'}` : `${countAll(roots)} people`}
          </span>
        </div>
        <div className={styles.orgToolbar}>
          <Button size="sm" variant="ghost" onClick={() => setCollapsed(new Set())}>Expand all</Button>
          <Button size="sm" variant="ghost" onClick={() => setCollapsed(new Set(allIds(roots)))}>Collapse all</Button>
          <IconButton size="sm" variant="secondary" icon={<Minus size={14} />} aria-label="Zoom out" disabled={zoom <= 0.5} onClick={() => setZoom((z) => Math.max(0.5, +(z - 0.1).toFixed(1)))} />
          <span className={styles.small} style={{ minWidth: 40, textAlign: 'center' }} aria-live="polite">{Math.round(zoom * 100)}%</span>
          <IconButton size="sm" variant="secondary" icon={<Plus size={14} />} aria-label="Zoom in" disabled={zoom >= 1.5} onClick={() => setZoom((z) => Math.min(1.5, +(z + 0.1).toFixed(1)))} />
          <IconButton size="sm" variant="ghost" icon={<RotateCcw size={14} />} aria-label="Reset zoom" onClick={() => setZoom(1)} />
        </div>
      </div>
      <div className={styles.orgViewport} tabIndex={0} aria-label="Organisation chart (scrollable)" role="region">
        <div className={styles.orgCanvas} style={{ zoom }}>
          <ul className={cn(styles.tree, styles.horizontal)} aria-label="Organisation chart">
            {roots.map((n) => (
              <Node key={n.id} node={n} term={term.trim()} collapsed={collapsed} onToggle={toggle} onPath={onPath} depth={0} />
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
}
