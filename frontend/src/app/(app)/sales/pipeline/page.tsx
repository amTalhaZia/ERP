'use client';

import { useState, type DragEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarClock, Gauge, KanbanSquare, MoreHorizontal, Plus, Scale, Target, Trophy } from 'lucide-react';
import {
  Avatar, Button, EmptyState, ErrorState, FormField, IconButton, Menu, MenuItem, MenuLabel, MetricCard, Modal, PageHeader, SegmentedControl, Select, Skeleton, Textarea, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney, formatPercent } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { cn } from '@/lib/cn';
import { useAppSelector } from '@/store';
import { salesApi, type BoardColumn, type Opportunity } from '@/features/sales/api';
import { fromScaled, toScaled } from '@/features/sales/decimal';
import { OpportunityForm } from '@/features/sales/OpportunityForm';
import { useStages, useUserOptions } from '@/features/sales/shared';
import styles from './pipeline.module.scss';

type Stage = BoardColumn['stage'];
const FALLBACK_COLORS = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)', 'var(--chart-6)'];

/** Pure optimistic move: returns a new board with the card in the target column. */
function moveCard(board: BoardColumn[], oppId: number, to: Stage): BoardColumn[] {
  const from = board.find((c) => c.items.some((i) => i.id === oppId));
  const item = from?.items.find((i) => i.id === oppId);
  if (!from || !item) return board;
  const moved: Opportunity = {
    ...item,
    stage_id: to.id,
    stage: { id: to.id, name: to.name, color: to.color, probability: to.probability },
    probability: to.probability,
    status: to.is_won ? 'won' : to.is_lost ? 'lost' : 'open',
  };
  return board.map((c) => {
    if (c.stage.id === from.stage.id) {
      return { ...c, count: c.count - 1, total_value: fromScaled(toScaled(c.total_value) - toScaled(item.value)), items: c.items.filter((i) => i.id !== oppId) };
    }
    if (c.stage.id === to.id) {
      const items = [...c.items, moved].sort((a, b) => {
        const d = toScaled(b.value) - toScaled(a.value);
        return d === 0n ? a.id - b.id : d > 0n ? 1 : -1;
      });
      return { ...c, count: c.count + 1, total_value: fromScaled(toScaled(c.total_value) + toScaled(item.value)), items };
    }
    return c;
  });
}

export default function PipelinePage() {
  const can = useCan();
  const toast = useToast();
  const qc = useQueryClient();
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const canManage = can('leads.manage');
  const [status, setStatus] = useState<'all' | 'open'>('all');
  const [ownerId, setOwnerId] = useState('');
  const users = useUserOptions();
  const stages = useStages();

  const params: Record<string, unknown> = {};
  if (status === 'open') params.status = 'open';
  if (ownerId) params.owner_id = Number(ownerId);
  const boardKey = [companyId, 'opportunities', 'board', params];
  const board = useQuery({ queryKey: boardKey, queryFn: () => salesApi.board(params) });
  const summary = useQuery({ queryKey: [companyId, 'opportunities', 'pipeline-summary'], queryFn: salesApi.pipelineSummary });

  const [dragId, setDragId] = useState<number | null>(null);
  const [overStage, setOverStage] = useState<number | null>(null);
  const [lostPrompt, setLostPrompt] = useState<{ opp: Opportunity; stage: Stage } | null>(null);
  const [lostReason, setLostReason] = useState('');
  const [editing, setEditing] = useState<Opportunity | { newInStage?: number } | null>(null);
  const [announce, setAnnounce] = useState('');

  const move = useMutation<Opportunity, ApiError, { opp: Opportunity; stage: Stage; lost_reason?: string | null }, { previous?: BoardColumn[] }>({
    mutationFn: ({ opp, stage, lost_reason }) => salesApi.moveStage(opp.id, { stage_id: stage.id, lost_reason: lost_reason ?? null }),
    onMutate: async ({ opp, stage }) => {
      await qc.cancelQueries({ queryKey: boardKey });
      const previous = qc.getQueryData<BoardColumn[]>(boardKey);
      if (previous) qc.setQueryData<BoardColumn[]>(boardKey, moveCard(previous, opp.id, stage));
      setAnnounce(`Moved ${opp.title} to ${stage.name}.`);
      return { previous };
    },
    onError: (e, { opp }, ctx) => {
      if (ctx?.previous) qc.setQueryData(boardKey, ctx.previous);
      setAnnounce(`Could not move ${opp.title}; it was returned to its column.`);
      toast.error(`Could not move “${opp.title}”`, e.status === 422 ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message : e.message);
    },
    onSuccess: (o, { stage }) => {
      if (stage.is_won) toast.success(`“${o.title}” marked as won`);
    },
    onSettled: () => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('opportunities') });
    },
  });

  const requestMove = (opp: Opportunity, stage: Stage) => {
    if (opp.stage_id === stage.id) return;
    if (stage.is_lost) {
      setLostReason(opp.lost_reason ?? '');
      setLostPrompt({ opp, stage });
      return;
    }
    move.mutate({ opp, stage });
  };

  const columns = board.data ?? [];
  const allStages = columns.map((c) => c.stage);
  const findOpp = (id: number) => columns.flatMap((c) => c.items).find((i) => i.id === id);

  const onDrop = (e: DragEvent<HTMLElement>, stage: Stage) => {
    e.preventDefault();
    const id = Number(e.dataTransfer.getData('text/plain')) || dragId;
    setOverStage(null);
    setDragId(null);
    const opp = id ? findOpp(id) : undefined;
    if (opp) requestMove(opp, stage);
  };

  const s = summary.data;
  const money = (v: string | null | undefined) => formatMoney(v, currency, undefined, { maximumFractionDigits: 0 });
  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className={styles.page}>
      <PageHeader
        title="Pipeline"
        description="Drag deals between stages, or use each card’s menu to move it with the keyboard."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing({})} disabled={!stages.data?.length}>New opportunity</Button>}
      />

      <section className={styles.summary} aria-label="Pipeline summary">
        <MetricCard label="Open pipeline" icon={<Target size={18} />} loading={summary.isLoading} value={money(s?.open.total_value)} footer={s ? `${s.open.count} open deal${s.open.count === 1 ? '' : 's'}` : undefined} />
        <MetricCard label="Weighted value" icon={<Scale size={18} />} loading={summary.isLoading} value={money(s?.open.weighted_value)} footer="Σ value × probability" />
        <MetricCard
          label="Win rate (90 days)"
          icon={<Trophy size={18} />}
          loading={summary.isLoading}
          value={s?.win_rate_90d.rate === null || s?.win_rate_90d.rate === undefined ? '—' : formatPercent(s.win_rate_90d.rate)}
          unavailable={!summary.isLoading && s?.win_rate_90d.rate === null}
          unavailableText="No closed deals yet"
          footer={s ? `${s.win_rate_90d.won} won · ${s.win_rate_90d.lost} lost` : undefined}
        />
        <MetricCard label="Stages" icon={<Gauge size={18} />} loading={board.isLoading} value={columns.length} footer={`${columns.reduce((n, c) => n + c.count, 0)} deals on the board`} />
      </section>

      <div className={styles.toolbar}>
        <SegmentedControl<'all' | 'open'>
          aria-label="Status"
          size="sm"
          value={status}
          onChange={setStatus}
          options={[{ value: 'all', label: 'All deals' }, { value: 'open', label: 'Open only' }]}
        />
        <Select size="sm" aria-label="Owner" placeholder="All owners" value={ownerId} onChange={(e) => setOwnerId(e.target.value)} options={users.options} />
      </div>

      <div className={styles.srOnly} aria-live="polite" role="status">{announce}</div>

      {board.error ? (
        <ErrorState error={board.error} onRetry={() => void board.refetch()} />
      ) : board.isLoading ? (
        <div className={styles.board}>
          {[0, 1, 2, 3].map((i) => <Skeleton key={i} style={{ flex: '0 0 280px', height: 360 }} />)}
        </div>
      ) : columns.length === 0 ? (
        <EmptyState icon={<KanbanSquare size={22} />} title="No pipeline stages" description="Ask an administrator to configure pipeline stages before tracking opportunities." />
      ) : (
        <div className={styles.board} role="list" aria-label="Pipeline stages">
          {columns.map((col, ci) => {
            const color = col.stage.color ?? FALLBACK_COLORS[ci % FALLBACK_COLORS.length];
            return (
              <section
                key={col.stage.id}
                role="listitem"
                aria-labelledby={`stage-${col.stage.id}`}
                className={cn(styles.column, overStage === col.stage.id && styles.columnOver)}
                onDragOver={(e) => {
                  if (dragId === null) return;
                  e.preventDefault();
                  e.dataTransfer.dropEffect = 'move';
                  if (overStage !== col.stage.id) setOverStage(col.stage.id);
                }}
                onDragLeave={(e) => {
                  if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setOverStage((o) => (o === col.stage.id ? null : o));
                }}
                onDrop={(e) => onDrop(e, col.stage)}
              >
                <header className={styles.columnHeader}>
                  <h2 id={`stage-${col.stage.id}`}>
                    <span className={styles.dot} style={{ background: color }} aria-hidden />
                    <span className={styles.columnName}>{col.stage.name}</span>
                    <span className={styles.count} aria-label={`${col.count} deals`}>{col.count}</span>
                    {canManage && !col.stage.is_won && !col.stage.is_lost && (
                      <IconButton icon={<Plus size={14} />} size="sm" aria-label={`Add opportunity to ${col.stage.name}`} onClick={() => setEditing({ newInStage: col.stage.id })} />
                    )}
                  </h2>
                  <div className={styles.columnTotal}>
                    {money(col.total_value)} · {col.stage.probability}%
                  </div>
                </header>
                <ul className={styles.cards} aria-label={`${col.stage.name} deals`}>
                  {col.items.length === 0 && <li className={styles.emptyColumn}>{canManage ? 'Drop deals here' : 'No deals'}</li>}
                  {col.items.map((o) => {
                    const overdue = o.status === 'open' && !!o.expected_close_date && o.expected_close_date < today;
                    return (
                      <li
                        key={o.id}
                        className={cn(styles.card, dragId === o.id && styles.dragging, move.isPending && move.variables?.opp.id === o.id && styles.pending)}
                        draggable={canManage}
                        onDragStart={(e) => {
                          e.dataTransfer.setData('text/plain', String(o.id));
                          e.dataTransfer.effectAllowed = 'move';
                          setDragId(o.id);
                        }}
                        onDragEnd={() => {
                          setDragId(null);
                          setOverStage(null);
                        }}
                      >
                        <div className={styles.cardTop}>
                          <button type="button" className={styles.cardTitle} onClick={() => setEditing(o)}>{o.title}</button>
                          {canManage && (
                            <Menu trigger={<IconButton icon={<MoreHorizontal size={14} />} size="sm" aria-label={`Move ${o.title} to another stage`} />} align="end">
                              <MenuLabel>Move to…</MenuLabel>
                              {allStages.filter((st) => st.id !== o.stage_id).map((st) => (
                                <MenuItem key={st.id} onSelect={() => requestMove(o, st)}>
                                  {st.name}{st.is_won ? ' (won)' : st.is_lost ? ' (lost)' : ''}
                                </MenuItem>
                              ))}
                            </Menu>
                          )}
                        </div>
                        <div className={styles.cardCustomer}>{o.customer?.name ?? 'No customer'}</div>
                        <div className={styles.cardValue}>
                          <span>{formatMoney(o.value, o.currency || currency, undefined, { maximumFractionDigits: 0 })}</span>
                          <small>{o.probability}%</small>
                        </div>
                        <div className={styles.cardMeta}>
                          <span className={cn(overdue && styles.overdue)} title="Expected close">
                            <CalendarClock size={12} aria-hidden style={{ verticalAlign: -2 }} /> {o.expected_close_date ? formatDate(o.expected_close_date, 'dd MMM') : 'No date'}
                            {overdue && <span className={styles.srOnly}> (overdue)</span>}
                          </span>
                          {o.owner && (
                            <span className={styles.owner} title={`Owner: ${o.owner.name}`}>
                              <Avatar name={o.owner.name} size="xs" />
                              {o.owner.name.split(' ')[0]}
                            </span>
                          )}
                        </div>
                      </li>
                    );
                  })}
                </ul>
              </section>
            );
          })}
        </div>
      )}

      {lostPrompt && (
        <Modal
          open
          size="sm"
          onClose={() => setLostPrompt(null)}
          title={`Mark “${lostPrompt.opp.title}” as lost?`}
          description={`Moves the deal to ${lostPrompt.stage.name} and closes it.`}
          footer={
            <>
              <Button variant="secondary" onClick={() => setLostPrompt(null)}>Cancel</Button>
              <Button
                variant="danger"
                onClick={() => {
                  move.mutate({ opp: lostPrompt.opp, stage: lostPrompt.stage, lost_reason: lostReason.trim() || null });
                  setLostPrompt(null);
                }}
              >
                Mark as lost
              </Button>
            </>
          }
        >
          <FormField label="Lost reason" hint="Helps the team learn why deals slip away.">
            <Textarea rows={3} maxLength={500} value={lostReason} onChange={(e) => setLostReason(e.target.value)} placeholder="Price, timing, competitor…" autoFocus />
          </FormField>
        </Modal>
      )}

      {editing !== null && stages.data && (
        <OpportunityForm
          key={'id' in editing ? `opp-${editing.id}` : `new-${editing.newInStage ?? ''}`}
          open
          opportunity={'id' in editing ? (editing as Opportunity) : null}
          defaultStageId={'id' in editing ? undefined : editing.newInStage}
          stages={stages.data}
          canManage={canManage}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  );
}
