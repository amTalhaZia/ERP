'use client';

import { Suspense, useMemo, useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { Search as SearchIcon } from 'lucide-react';
import { Card, CardBody, CardHeader, EmptyState, ErrorState, PageHeader, SearchInput, Skeleton } from '@/components/ui';
import { get } from '@/lib/api/client';
import { humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import styles from '@/components/crud/crud.module.scss';

interface Hit {
  type: string;
  id: number | string;
  title: string;
  subtitle?: string | null;
  url: string;
}

/** Accepts `{data: Hit[]}` or `{data: {type: Hit[]}}`. */
function normalize(data: unknown): Record<string, Hit[]> {
  if (Array.isArray(data)) {
    return (data as Hit[]).reduce<Record<string, Hit[]>>((acc, h) => {
      (acc[h.type] ??= []).push(h);
      return acc;
    }, {});
  }
  if (data && typeof data === 'object') {
    const out: Record<string, Hit[]> = {};
    for (const [k, v] of Object.entries(data as Record<string, unknown>)) if (Array.isArray(v) && v.length) out[k] = v as Hit[];
    return out;
  }
  return {};
}

function SearchInner() {
  const params = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const initial = params.get('q') ?? '';
  const [q, setQ] = useState(initial);

  const query = useQuery({
    queryKey: [companyId, 'search', initial],
    queryFn: () => get<{ data: unknown }>('/search', { q: initial }).then((r) => normalize(r.data)),
    enabled: initial.trim().length >= 2,
  });
  const groups = useMemo(() => Object.entries(query.data ?? {}), [query.data]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 900 }}>
      <PageHeader title="Search" description="Employees, customers, suppliers, products, documents and transactions you have access to." />
      <SearchInput
        autoFocus
        size="lg"
        placeholder="Search by name, number, SKU, email…"
        value={q}
        onValueChange={setQ}
        onDebouncedChange={(v: string) => router.replace(v ? `${pathname}?q=${encodeURIComponent(v)}` : pathname)}
        aria-label="Search"
      />
      {initial.trim().length < 2 ? (
        <EmptyState icon={<SearchIcon size={22} />} title="Type at least 2 characters" />
      ) : query.error ? (
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      ) : query.isLoading ? (
        <Skeleton style={{ height: 240 }} />
      ) : groups.length === 0 ? (
        <EmptyState icon={<SearchIcon size={22} />} title={`No results for “${initial}”`} description="Try a different spelling, a document number or an SKU." />
      ) : (
        groups.map(([type, hits]) => (
          <Card key={type}>
            <CardHeader title={humanize(type)} />
            <CardBody padding="none">
              <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                {hits.map((h) => (
                  <li key={`${h.type}-${h.id}`} style={{ borderTop: '1px solid var(--color-divider)' }}>
                    <Link href={h.url} style={{ display: 'block', padding: '10px 16px', color: 'inherit' }}>
                      <div className={styles.cellMain}>
                        <strong>{h.title}</strong>
                        {h.subtitle && <span>{h.subtitle}</span>}
                      </div>
                    </Link>
                  </li>
                ))}
              </ul>
            </CardBody>
          </Card>
        ))
      )}
    </div>
  );
}

export default function SearchPage() {
  return (
    <Suspense>
      <SearchInner />
    </Suspense>
  );
}
