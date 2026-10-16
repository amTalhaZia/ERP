'use client';

import { Check } from 'lucide-react';
import { cn } from '@/lib/cn';
import { roleRank } from '@/features/platform/roleOrder';
import { groupRoles, roleIcon } from './roleLevels';
import styles from './RolePicker.module.scss';

interface RoleOption {
  id: number;
  slug: string;
  name: string;
  description?: string | null;
}

export function RolePicker({ roles, value, onChange, invalid }: { roles: RoleOption[]; value: number[]; onChange: (ids: number[]) => void; invalid?: boolean }) {
  const sorted = [...roles].sort((a, b) => roleRank([a.slug]) - roleRank([b.slug]) || a.name.localeCompare(b.name));
  const groups = groupRoles(sorted);

  const selected = sorted.filter((r) => value.includes(r.id));
  const toggle = (id: number, on: boolean) => onChange(on ? [...value, id] : value.filter((x) => x !== id));

  return (
    <div className={cn(styles.picker, invalid && styles.invalid)}>
      {groups.map((g) => (
        <fieldset key={g.key} className={cn(styles.group, styles[g.tone])}>
          <legend className={styles.legend}>
            <span className={styles.dot} aria-hidden />
            <span className={styles.legendTitle}>{g.title}</span>
            <span className={styles.legendHint}>{g.hint}</span>
          </legend>
          <div className={styles.grid}>
            {g.items.map((r) => {
              const checked = value.includes(r.id);
              const Icon = roleIcon(r.slug);
              return (
                <label key={r.id} className={cn(styles.tile, checked && styles.checked)} title={r.description ?? undefined}>
                  <input type="checkbox" className={styles.input} checked={checked} onChange={(ev) => toggle(r.id, ev.target.checked)} />
                  <span className={styles.tileHead}>
                    <span className={styles.icon} aria-hidden><Icon size={16} /></span>
                    <span className={styles.check} aria-hidden>{checked && <Check size={12} strokeWidth={3} />}</span>
                  </span>
                  <span className={styles.name}>{r.name}</span>
                  {r.description && <span className={styles.desc}>{r.description}</span>}
                  {r.slug === 'company.owner' && <span className={styles.flag}>Full control — only for co-owners</span>}
                </label>
              );
            })}
          </div>
        </fieldset>
      ))}

      <div className={cn(styles.summary, selected.length === 0 && styles.summaryEmpty)} aria-live="polite">
        {selected.length === 0 ? (
          'No role selected yet'
        ) : (
          <>
            <span>Selected</span>
            {selected.map((r) => (
              <button key={r.id} type="button" className={styles.chip} onClick={() => toggle(r.id, false)} aria-label={`Remove ${r.name}`}>
                {r.name} <span aria-hidden>×</span>
              </button>
            ))}
          </>
        )}
      </div>
    </div>
  );
}
