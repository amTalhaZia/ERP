'use client';

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api, get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';
import type { Money, Paginated } from '@/lib/api/types';
import type { Address } from '@/features/company/api';
import { useAppSelector } from '@/store';

// ---- Enums -----------------------------------------------------------------

export type SupplierType = 'individual' | 'company';
export type RequisitionStatus = 'draft' | 'submitted' | 'approved' | 'rejected' | 'ordered' | 'cancelled';
export type RequisitionPriority = 'low' | 'normal' | 'high' | 'urgent';
export type PurchaseOrderStatus = 'draft' | 'submitted' | 'approved' | 'partially_received' | 'received' | 'closed' | 'cancelled';
export type GoodsReceiptStatus = 'draft' | 'confirmed' | 'cancelled';
export type PurchaseReturnStatus = 'draft' | 'confirmed' | 'cancelled';
export type PurchaseReturnReason = 'damaged' | 'wrong_item' | 'quality' | 'excess' | 'other';

const opts = <T extends string>(values: readonly T[], labels: Partial<Record<T, string>> = {}) =>
  values.map((v) => ({ value: v, label: labels[v] ?? v.charAt(0).toUpperCase() + v.slice(1).replace(/_/g, ' ') }));

export const REQUISITION_STATUSES = opts<RequisitionStatus>(['draft', 'submitted', 'approved', 'rejected', 'ordered', 'cancelled']);
export const REQUISITION_PRIORITIES = opts<RequisitionPriority>(['low', 'normal', 'high', 'urgent']);
export const PO_STATUSES = opts<PurchaseOrderStatus>(['draft', 'submitted', 'approved', 'partially_received', 'received', 'closed', 'cancelled']);
export const GR_STATUSES = opts<GoodsReceiptStatus>(['draft', 'confirmed', 'cancelled']);
export const RETURN_REASONS = opts<PurchaseReturnReason>(['damaged', 'wrong_item', 'quality', 'excess', 'other']);

/** Status → StatusBadge variant overrides for procurement-specific states. */
export const STATUS_VARIANTS: Record<string, 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'primary'> = {
  submitted: 'warning',
  ordered: 'primary',
  partially_received: 'info',
  confirmed: 'success',
};
export const PRIORITY_VARIANTS: Record<RequisitionPriority, 'neutral' | 'info' | 'warning' | 'danger'> = {
  low: 'neutral',
  normal: 'info',
  high: 'warning',
  urgent: 'danger',
};

// ---- Suppliers ---------------------------------------------------------------

export interface SupplierContact {
  id: number;
  supplier_id: number;
  name: string;
  title: string | null;
  email: string | null;
  phone: string | null;
  is_primary: boolean;
}

export interface Supplier {
  id: number;
  code: string;
  name: string;
  type: SupplierType;
  email: string | null;
  phone: string | null;
  website: string | null;
  tax_number: string | null;
  currency: string;
  payment_terms_days: number;
  credit_limit: Money | null;
  address: Address;
  latitude: string | null;
  longitude: string | null;
  bank_name: string | null;
  bank_account_last4: string | null;
  rating: number | null;
  lead_time_days: number | null;
  categories: string[];
  notes: string | null;
  is_active: boolean;
  is_approved: boolean;
  approved_at: string | null;
  approver?: { id: number; name: string } | null;
  contacts?: SupplierContact[];
  contacts_count?: number;
  purchase_orders_count?: number;
  created_at?: string;
  updated_at?: string;
}

/** Lightweight shape returned by /suppliers/all. */
export type SupplierOption = Pick<Supplier, 'id' | 'code' | 'name' | 'type' | 'currency' | 'payment_terms_days' | 'is_approved' | 'is_active' | 'rating' | 'lead_time_days'>;

export type SupplierInput = Record<string, unknown>;

export interface SupplierSummary {
  supplier_id: number;
  currency: string;
  purchase_orders: {
    by_status: { status: PurchaseOrderStatus; count: number; total: Money }[];
    total_count: number;
    committed_count: number;
    committed_total: Money;
  };
  goods_receipts_count: number;
  avg_lead_time_days: number | null;
  promised_lead_time_days: number | null;
  last_order_date: string | null;
  last_received_date: string | null;
  outstanding_payable: Money | null;
}

// ---- Requisitions ------------------------------------------------------------

export interface RequisitionLine {
  id: number;
  line_no: number;
  product_id: number | null;
  description: string;
  quantity: string;
  uom: string | null;
  estimated_unit_price: Money | null;
  line_total: Money;
}

export interface Requisition {
  id: number;
  number: string;
  status: RequisitionStatus;
  priority: RequisitionPriority;
  requester_id: number;
  requester?: { id: number; name: string } | null;
  department_id: number | null;
  department?: { id: number; name: string } | null;
  branch_id: number | null;
  branch?: { id: number; name: string } | null;
  needed_by: string | null;
  justification: string | null;
  estimated_total: Money;
  submitted_at: string | null;
  approver_id: number | null;
  approver?: { id: number; name: string } | null;
  approved_at: string | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  lines?: RequisitionLine[];
  lines_count?: number;
  purchase_orders?: { id: number; number: string; status: PurchaseOrderStatus }[];
  created_at: string;
  updated_at?: string;
}

export interface RequisitionInput {
  department_id?: number | null;
  branch_id?: number | null;
  needed_by?: string | null;
  priority?: RequisitionPriority;
  justification?: string | null;
  lines: { product_id?: number | null; description: string; quantity: string; uom?: string | null; estimated_unit_price?: string | null }[];
}

// ---- Purchase orders -----------------------------------------------------------

export interface PurchaseOrderLine {
  id: number;
  line_no: number;
  product_id: number | null;
  description: string;
  quantity: string;
  uom: string | null;
  unit_price: Money;
  discount_percent: string;
  tax_rate: string;
  line_subtotal: Money;
  tax_amount: Money;
  line_total: Money;
  quantity_received: string;
  quantity_returned: string;
  quantity_remaining: string;
}

export interface PurchaseOrder {
  id: number;
  number: string;
  status: PurchaseOrderStatus;
  supplier_id: number;
  supplier?: { id: number; code: string; name: string } | null;
  requisition_id: number | null;
  requisition?: { id: number; number: string } | null;
  branch_id: number | null;
  order_date: string;
  expected_date: string | null;
  currency: string;
  subtotal: Money;
  discount_total: Money;
  tax_total: Money;
  shipping_cost: Money;
  total: Money;
  warehouse_id: number | null;
  delivery_address: string | null;
  payment_terms_days: number | null;
  notes: string | null;
  terms: string | null;
  created_by: number | null;
  submitted_at: string | null;
  approved_by: number | null;
  approver?: { id: number; name: string } | null;
  approved_at: string | null;
  sent_at: string | null;
  closed_at: string | null;
  cancelled_at: string | null;
  cancelled_reason: string | null;
  lines?: PurchaseOrderLine[];
  lines_count?: number;
  goods_receipts_count?: number;
  created_at: string;
  updated_at?: string;
}

export interface PurchaseOrderInput {
  supplier_id: number;
  branch_id?: number | null;
  order_date: string;
  expected_date?: string | null;
  currency?: string | null;
  shipping_cost?: string | null;
  warehouse_id?: number | null;
  delivery_address?: string | null;
  payment_terms_days?: number | null;
  notes?: string | null;
  terms?: string | null;
  lines: {
    product_id?: number | null;
    description: string;
    quantity: string;
    uom?: string | null;
    unit_price: string;
    discount_percent?: string | null;
    tax_rate?: string | null;
  }[];
}

// ---- Goods receipts --------------------------------------------------------------

export interface GoodsReceiptLine {
  id: number;
  purchase_order_line_id: number;
  product_id: number | null;
  description: string;
  quantity_ordered: string;
  quantity_received: string;
  quantity_rejected: string;
  rejection_reason: string | null;
  unit_cost: Money;
  batch_number: string | null;
  expires_at: string | null;
}

export interface GoodsReceipt {
  id: number;
  number: string;
  status: GoodsReceiptStatus;
  purchase_order_id: number;
  purchase_order?: { id: number; number: string; status: PurchaseOrderStatus; supplier_id: number } | null;
  received_date: string;
  warehouse_id: number | null;
  received_by: number | null;
  receiver?: { id: number; name: string } | null;
  supplier_delivery_note: string | null;
  notes: string | null;
  confirmed_at: string | null;
  cancelled_at: string | null;
  lines?: GoodsReceiptLine[];
  lines_count?: number;
  created_at: string;
  updated_at?: string;
}

export interface GoodsReceiptInput {
  received_date: string;
  warehouse_id?: number | null;
  supplier_delivery_note?: string | null;
  notes?: string | null;
  lines: {
    purchase_order_line_id: number;
    quantity_received: string;
    quantity_rejected?: string | null;
    rejection_reason?: string | null;
    batch_number?: string | null;
    expires_at?: string | null;
  }[];
}

// ---- Purchase returns ------------------------------------------------------------

export interface PurchaseReturnLine {
  id: number;
  purchase_order_line_id: number;
  product_id: number | null;
  description: string;
  quantity: string;
  unit_cost: Money;
  line_total: Money;
}

export interface PurchaseReturn {
  id: number;
  number: string;
  status: PurchaseReturnStatus;
  reason: PurchaseReturnReason;
  purchase_order_id: number;
  purchase_order?: { id: number; number: string; status: PurchaseOrderStatus; supplier_id: number } | null;
  goods_receipt_id: number | null;
  goods_receipt?: { id: number; number: string } | null;
  return_date: string;
  notes: string | null;
  total: Money;
  confirmed_at: string | null;
  cancelled_at: string | null;
  lines?: PurchaseReturnLine[];
  created_at: string;
  updated_at?: string;
}

export interface PurchaseReturnInput {
  purchase_order_id: number;
  goods_receipt_id?: number | null;
  return_date: string;
  reason: PurchaseReturnReason;
  notes?: string | null;
  lines: { purchase_order_line_id: number; quantity: string; unit_cost?: string | null }[];
}

// ---- Resources -----------------------------------------------------------------

export const suppliers = resource<Supplier, SupplierInput>('suppliers', 'Supplier');
export const requisitions = resource<Requisition, RequisitionInput>('requisitions', 'Requisition');
export const purchaseOrders = resource<PurchaseOrder, PurchaseOrderInput>('purchase-orders', 'Purchase order');
export const goodsReceipts = resource<GoodsReceipt, GoodsReceiptInput>('goods-receipts', 'Goods receipt');
export const purchaseReturns = resource<PurchaseReturn, PurchaseReturnInput>('purchase-returns', 'Purchase return');

export const procurementApi = {
  supplierSummary: (id: number | string) => get<{ data: SupplierSummary }>(`/suppliers/${id}/summary`).then((r) => r.data),
  createContact: (supplierId: number, body: Record<string, unknown>) =>
    api.post<{ data: SupplierContact }>(`/suppliers/${supplierId}/contacts`, body).then((r) => r.data.data),
  updateContact: (supplierId: number, id: number, body: Record<string, unknown>) =>
    api.put<{ data: SupplierContact }>(`/suppliers/${supplierId}/contacts/${id}`, body).then((r) => r.data.data),
  deleteContact: (supplierId: number, id: number) => api.delete(`/suppliers/${supplierId}/contacts/${id}`).then(() => undefined),
  createReceipt: (orderId: number, body: GoodsReceiptInput) =>
    api.post<{ data: GoodsReceipt }>(`/purchase-orders/${orderId}/goods-receipts`, body).then((r) => r.data.data),
  createOrderFromRequisition: (requisitionId: number, body: Record<string, unknown>) =>
    api.post<{ data: PurchaseOrder }>(`/requisitions/${requisitionId}/create-purchase-order`, body).then((r) => r.data.data),
};

// ---- Lookups owned by other modules (read-only, minimal shapes) ----------------------

export interface ProductOption {
  id: number;
  sku: string;
  name: string;
  cost_price: Money | null;
  uom?: { id: number; name: string; symbol: string } | null;
}

export interface WarehouseOption {
  id: number;
  name: string;
  code: string;
}

/** Invalidate every cached query whose key mentions one of the given resource paths. */
export function useInvalidate() {
  const qc = useQueryClient();
  return (...paths: string[]) => qc.invalidateQueries({ predicate: (q) => paths.some((p) => q.queryKey.includes(p)) });
}

const useCompanyId = () => useAppSelector((s) => s.session.current_company?.id ?? 0);

export function useProductSearch(term: string, enabled: boolean) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, 'procurement', 'product-picker', term],
    queryFn: () => get<Paginated<ProductOption>>('/products', { search: term || undefined, per_page: 20 }).then((r) => r.data),
    enabled,
    staleTime: 30_000,
    retry: false,
  });
}

export function useWarehouses() {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, 'procurement', 'warehouses'],
    queryFn: () => get<Paginated<WarehouseOption>>('/warehouses', { per_page: 100 }).then((r) => r.data),
    staleTime: 5 * 60_000,
    retry: false,
  });
}

// ---- Reports -------------------------------------------------------------------

export type SpendGroupBy = 'supplier' | 'month' | 'department';

export interface SpendReport {
  group_by: SpendGroupBy;
  rows: { key: number | string | null; label: string; order_count: number; total: Money }[];
  total: Money;
  order_count: number;
}

export interface StatusRow {
  status: string;
  count: number;
  total: Money;
}

export interface StatusSummary {
  purchase_orders: StatusRow[];
  requisitions: StatusRow[];
  avg_requisition_approval_hours: number | null;
  on_time_receipt: { receipts: number; on_time: number; rate: number | null };
}

export interface SupplierPerformanceRow {
  supplier_id: number;
  code: string | null;
  name: string | null;
  rating: number | null;
  promised_lead_time_days: number | null;
  order_count: number;
  total: Money;
  receipt_count: number;
  avg_lead_time_days: number | null;
  on_time_rate: number | null;
  quantity_received: string;
  quantity_rejected: string;
  rejected_ratio: number | null;
}

interface ReportMeta {
  from: string | null;
  to: string | null;
  currency: string | null;
}

const report = <T>(name: string, params: Record<string, unknown>) =>
  get<{ data: T; meta: ReportMeta }>(`/procurement/reports/${name}`, params);

export const procurementReports = {
  spend: (p: { from?: string; to?: string; group_by: SpendGroupBy }) => report<SpendReport>('spend', p),
  statusSummary: (p: { from?: string; to?: string }) => report<StatusSummary>('status-summary', p),
  supplierPerformance: (p: { from?: string; to?: string }) => report<SupplierPerformanceRow[]>('supplier-performance', p),
};
