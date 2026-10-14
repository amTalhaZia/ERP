import { get } from '@/lib/api/client';
import type { Money } from '@/lib/api/types';

/** A metric for the current period plus the comparison period. `null` = no data, never a fabricated 0. */
export interface Metric {
  current: Money | number | null;
  previous: Money | number | null;
  /** Percent change; null when previous is 0/null (not meaningful). */
  change_percent: number | null;
}

export interface TrendPoint {
  period: string; // 'YYYY-MM' or 'YYYY-MM-DD'
  revenue: Money;
  expenses: Money;
}

export interface DashboardPayload {
  period: { from: string; to: string; previous_from: string; previous_to: string };
  currency: string;
  /** Modules whose data were included (others are absent rather than zero). */
  modules: string[];
  metrics: {
    revenue?: Metric;
    expenses?: Metric;
    net_profit?: Metric;
    receivables?: Metric;
    payables?: Metric;
    employees?: Metric;
    active_products?: Metric;
    low_stock?: Metric;
    open_sales_orders?: Metric;
    pending_approvals?: Metric;
  };
  trend: TrendPoint[];
  sales_by_month?: { period: string; total: Money; count: number }[];
  by_branch?: { branch_id: number | null; name: string; revenue: Money; expenses: Money }[];
  by_department?: { department_id: number | null; name: string; headcount: number }[];
  recent_invoices?: { id: number; number: string; customer: string; total: Money; status: string; date: string }[];
  low_stock_items?: { id: number; sku: string; name: string; on_hand: string; reorder_level: string }[];
  pending_approvals?: { type: string; id: number; title: string; requested_by: string; url: string; created_at: string }[];
  recent_activity?: { id: number; action: string; entity_label: string | null; actor_name: string | null; created_at: string }[];
}

export interface DashboardParams {
  from?: string;
  to?: string;
  branch_id?: number | null;
}

export const dashboardApi = {
  get: (params: DashboardParams) =>
    get<{ data: DashboardPayload }>('/dashboard', {
      ...(params.from ? { from: params.from } : {}),
      ...(params.to ? { to: params.to } : {}),
      ...(params.branch_id ? { branch_id: params.branch_id } : {}),
    }),
};
