'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery } from '@tanstack/react-query';
import { format, startOfMonth } from 'date-fns';
import { Download, FileSpreadsheet, FileText, History, Play, Sheet } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, DateInput, EmptyState, ErrorState, FormField, FormGrid, Menu, MenuItem, PageHeader, Pagination, SearchInput, Select, Skeleton, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney, formatNumber, humanize } from '@/lib/format';
import { isApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { reportsApi, type ReportColumn, type ReportDefinition, type ReportFormat } from '@/features/reports/api';
import crud from '@/components/crud/crud.module.scss';
import styles from './reports.module.scss';

function defaultsFor(def: ReportDefinition): Record<string, string> {
  const out: Record<string, string> = {};
  for (const p of def.params) {
    if (p.type === 'date') out[p.name] = p.name.includes('from') || p.name.includes('start') ? format(startOfMonth(new Date()), 'yyyy-MM-dd') : format(new Date(), 'yyyy-MM-dd');
    else if (p.type === 'select' && p.required && p.options?.[0]) out[p.name] = p.options[0].value;
  }
  return out;
}

function Cell({ col, value, currency }: { col: ReportColumn; value: unknown; currency: string }) {
  if (value === null || value === undefined || value === '') return <span className={crud.muted}>—</span>;
  if (col.type === 'money') return <>{formatMoney(value as string, currency)}</>;
  if (col.type === 'number') return <>{formatNumber(value as string)}</>;
  if (col.type === 'date') return <>{formatDate(value as string)}</>;
  return <>{String(value)}</>;
}

const FORMAT_ICON: Record<ReportFormat, typeof FileText> = { csv: Sheet, xlsx: FileSpreadsheet, pdf: FileText };

export default function ReportsPage() {
  const can = useCan();
  const toast = useToast();
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const branches = useAppSelector(selectBranches);
  const catalogue = useQuery({ queryKey: [companyId, 'reports', 'catalogue'], queryFn: reportsApi.catalogue, staleTime: 5 * 60_000 });
  const [selected, setSelected] = useState<ReportDefinition | null>(null);
  const [params, setParams] = useState<Record<string, string>>({});
  const [run, setRun] = useState<{ key: string; params: Record<string, string>; page: number } | null>(null);
  const [filter, setFilter] = useState('');

  const preview = useQuery({
    queryKey: [companyId, 'reports', 'preview', run],
    queryFn: () => reportsApi.preview(run!.key, { ...run!.params, page: run!.page, per_page: 50 }),
    enabled: !!run,
  });

  const exportMut = useMutation({
    mutationFn: (f: ReportFormat) => reportsApi.export(selected!.key, f, params),
    onSuccess: (r) => {
      if (r.kind === 'queued') toast.info('Export queued', 'This report is large — you will be notified when the file is ready.');
      else toast.success('Export downloaded');
    },
    onError: (e) => toast.error('Export failed', isApiError(e) ? e.message : undefined),
  });

  const grouped = useMemo(() => {
    const term = filter.trim().toLowerCase();
    return (catalogue.data ?? [])
      .filter((r) => !term || r.name.toLowerCase().includes(term) || r.description.toLowerCase().includes(term))
      .reduce<Record<string, ReportDefinition[]>>((acc, r) => {
        (acc[r.module] ??= []).push(r);
        return acc;
      }, {});
  }, [catalogue.data, filter]);

  const choose = (def: ReportDefinition) => {
    setSelected(def);
    const p = defaultsFor(def);
    setParams(p);
    setRun({ key: def.key, params: p, page: 1 });
  };

  const missingRequired = selected?.params.some((p) => p.required && !params[p.name]);
  const data = preview.data?.data;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title="Reports & exports"
        description="Run any report you have access to, preview it, and export to CSV, Excel or PDF. Every export is recorded in the audit log."
        actions={can('reports.export') && <Button variant="secondary" leftIcon={<History size={16} />} href="/reports/exports">My exports</Button>}
      />

      <div className={styles.layout}>
        <aside className={styles.catalogue} aria-label="Report catalogue">
          <SearchInput placeholder="Find a report" value={filter} onValueChange={setFilter} aria-label="Find a report" size="sm" />
          {catalogue.error ? (
            <ErrorState error={catalogue.error} onRetry={() => void catalogue.refetch()} />
          ) : catalogue.isLoading ? (
            <Skeleton variant="text" lines={10} />
          ) : Object.keys(grouped).length === 0 ? (
            <EmptyState size="sm" title="No reports available" description="Your role doesn’t include any reports." />
          ) : (
            Object.entries(grouped).map(([module, defs]) => (
              <div key={module} className={styles.group}>
                <div className={styles.groupTitle}>{humanize(module)}</div>
                <ul>
                  {defs.map((d) => (
                    <li key={d.key}>
                      <button type="button" className={selected?.key === d.key ? styles.active : undefined} onClick={() => choose(d)} aria-current={selected?.key === d.key}>
                        <span>{d.name}</span>
                        <small>{d.description}</small>
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            ))
          )}
        </aside>

        <section className={styles.main}>
          {!selected ? (
            <Card><CardBody><EmptyState title="Choose a report" description="Pick a report from the list to set its filters and preview the results." /></CardBody></Card>
          ) : (
            <>
              <Card>
                <CardHeader
                  title={selected.name}
                  description={selected.description}
                  actions={
                    can('reports.export') && (
                      <Menu trigger={<Button variant="secondary" leftIcon={<Download size={16} />} loading={exportMut.isPending} disabled={missingRequired}>Export</Button>} align="end">
                        {selected.formats.map((f) => {
                          const Icon = FORMAT_ICON[f];
                          return <MenuItem key={f} icon={<Icon size={16} />} onSelect={() => exportMut.mutate(f)}>{f.toUpperCase()}</MenuItem>;
                        })}
                      </Menu>
                    )
                  }
                />
                {selected.params.length > 0 && (
                  <CardBody>
                    <FormGrid columns={4}>
                      {selected.params.map((p) => (
                        <FormField key={p.name} label={p.label} required={p.required}>
                          {p.type === 'date' ? (
                            <DateInput value={params[p.name] ?? ''} onValueChange={(v) => setParams((s) => ({ ...s, [p.name]: v ?? '' }))} />
                          ) : p.type === 'branch' ? (
                            <Select value={params[p.name] ?? ''} placeholder="All branches" onChange={(e) => setParams((s) => ({ ...s, [p.name]: e.target.value }))} options={branches.map((b) => ({ value: String(b.id), label: b.name }))} />
                          ) : (
                            <Select value={params[p.name] ?? ''} placeholder={p.required ? undefined : 'Any'} onChange={(e) => setParams((s) => ({ ...s, [p.name]: e.target.value }))} options={p.options ?? []} />
                          )}
                        </FormField>
                      ))}
                    </FormGrid>
                    <div style={{ marginTop: 12 }}>
                      <Button leftIcon={<Play size={16} />} onClick={() => setRun({ key: selected.key, params, page: 1 })} disabled={missingRequired}>Run report</Button>
                    </div>
                  </CardBody>
                )}
              </Card>

              <Card>
                <CardBody padding="none">
                  {preview.error ? (
                    <div style={{ padding: 16 }}><ErrorState error={preview.error} onRetry={() => void preview.refetch()} /></div>
                  ) : preview.isLoading || !data ? (
                    <Skeleton style={{ height: 320, margin: 16, width: 'auto' }} />
                  ) : data.rows.length === 0 ? (
                    <EmptyState title="No rows for these filters" />
                  ) : (
                    <>
                      {preview.data?.meta.truncated && <Alert variant="info" style={{ margin: 16 }}>Preview limited to the first rows. Export to get the full report.</Alert>}
                      <div className={crud.tableScroll} style={{ margin: 0 }}>
                        <table className={crud.lines}>
                          <thead>
                            <tr>{data.columns.map((c) => <th key={c.key} className={c.type === 'money' || c.type === 'number' ? crud.r : undefined}>{c.label}</th>)}</tr>
                          </thead>
                          <tbody>
                            {data.rows.map((row, i) => (
                              <tr key={i}>
                                {data.columns.map((c) => (
                                  <td key={c.key} className={c.type === 'money' || c.type === 'number' ? crud.r : undefined}><Cell col={c} value={row[c.key]} currency={data.currency} /></td>
                                ))}
                              </tr>
                            ))}
                          </tbody>
                          {data.totals && (
                            <tfoot>
                              <tr>
                                {data.columns.map((c, i) => (
                                  <td key={c.key} className={c.type === 'money' || c.type === 'number' ? crud.r : undefined}>
                                    {i === 0 && data.totals?.[c.key] === undefined ? 'Total' : <Cell col={c} value={data.totals?.[c.key]} currency={data.currency} />}
                                  </td>
                                ))}
                              </tr>
                            </tfoot>
                          )}
                        </table>
                      </div>
                      {preview.data && preview.data.meta.pagination.last_page > 1 && (
                        <div style={{ padding: 12 }}>
                          <Pagination
                            page={preview.data.meta.pagination.page}
                            lastPage={preview.data.meta.pagination.last_page}
                            total={preview.data.meta.pagination.total}
                            perPage={preview.data.meta.pagination.per_page}
                            onPageChange={(page) => setRun((r) => (r ? { ...r, page } : r))}
                          />
                        </div>
                      )}
                    </>
                  )}
                </CardBody>
              </Card>
            </>
          )}
        </section>
      </div>
      {!can('reports.export') && <p style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Exporting requires the “Export reports” permission. <Link href="/settings/profile">Ask your administrator.</Link></p>}
    </div>
  );
}
