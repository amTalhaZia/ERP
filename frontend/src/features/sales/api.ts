import { api, get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';
import type { Money, Paginated } from '@/lib/api/types';

// ---------------------------------------------------------------------------
// Enums (mirror app/Enums/Sales/*.php)
// ---------------------------------------------------------------------------

export const CUSTOMER_TYPES = ['company', 'individual'] as const;
export type CustomerType = (typeof CUSTOMER_TYPES)[number];

export const COMMUNICATION_TYPES = ['call', 'email', 'meeting', 'note'] as const;
export type CommunicationType = (typeof COMMUNICATION_TYPES)[number];

export const LEAD_SOURCES = ['website', 'referral', 'campaign', 'cold_call', 'event', 'other'] as const;
export type LeadSource = (typeof LEAD_SOURCES)[number];

export const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'] as const;
export type LeadStatus = (typeof LEAD_STATUSES)[number];

export type OpportunityStatus = 'open' | 'won' | 'lost';

export const QUOTATION_STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'] as const;
export type QuotationStatus = (typeof QUOTATION_STATUSES)[number];

export const SALES_ORDER_STATUSES = ['draft', 'confirmed', 'approved', 'partially_delivered', 'delivered', 'cancelled'] as const;
export type SalesOrderStatus = (typeof SALES_ORDER_STATUSES)[number];

export const DELIVERY_STATUSES = ['pending', 'in_transit', 'delivered', 'cancelled'] as const;
export type DeliveryStatus = (typeof DELIVERY_STATUSES)[number];

// ---------------------------------------------------------------------------
// Shared shapes
// ---------------------------------------------------------------------------

export interface UserRef {
  id: number;
  name: string;
}

export interface CustomerRef {
  id: number;
  name: string;
  code: string;
}

export interface PostalAddress {
  line1: string | null;
  line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
}

// ---------------------------------------------------------------------------
// Customers & CRM
// ---------------------------------------------------------------------------

export interface CustomerContact {
  id: number;
  customer_id: number;
  name: string;
  title: string | null;
  email: string | null;
  phone: string | null;
  is_primary: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface Customer {
  id: number;
  company_id: number;
  code: string;
  name: string;
  type: CustomerType;
  email: string | null;
  phone: string | null;
  website: string | null;
  tax_number: string | null;
  currency: string;
  payment_terms_days: number;
  credit_limit: Money | null;
  billing_address: PostalAddress;
  shipping_address: PostalAddress;
  latitude: string | null;
  longitude: string | null;
  sales_rep_id: number | null;
  sales_rep?: UserRef | null;
  source: string | null;
  tags: string[];
  notes: string | null;
  is_active: boolean;
  contacts?: CustomerContact[];
  contacts_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export type CustomerInput = Record<string, unknown>;

export interface CustomerMapPoint {
  id: number;
  name: string;
  code: string;
  latitude: string | number;
  longitude: string | number;
  city: string | null;
  country: string | null;
}

interface StatusBucket {
  count: number;
  total: Money;
  by_status: Record<string, { count: number; total: Money }>;
}

export interface CustomerSummary {
  customer_id: number;
  quotations: StatusBucket;
  sales_orders: StatusBucket;
  opportunities: StatusBucket;
  last_order_date: string | null;
  outstanding_balance: Money | null;
  outstanding_balance_note?: string;
}

export interface CustomerCommunication {
  id: number;
  customer_id: number;
  user_id: number | null;
  user?: UserRef | null;
  type: CommunicationType;
  subject: string;
  body: string | null;
  happened_at: string | null;
  follow_up_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

// ---------------------------------------------------------------------------
// Pipeline
// ---------------------------------------------------------------------------

export interface PipelineStage {
  id: number;
  name: string;
  probability: number;
  position: number;
  is_won: boolean;
  is_lost: boolean;
  color: string | null;
  opportunities_count?: number;
}

export type StageRef = Pick<PipelineStage, 'id' | 'name' | 'color' | 'probability'> & { is_won?: boolean; is_lost?: boolean };

export interface Lead {
  id: number;
  company_id: number;
  name: string;
  company_name: string | null;
  email: string | null;
  phone: string | null;
  source: LeadSource;
  status: LeadStatus;
  estimated_value: Money | null;
  assigned_to: number | null;
  assignee?: UserRef | null;
  notes: string | null;
  converted_customer_id: number | null;
  converted_customer?: CustomerRef | null;
  converted_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface Opportunity {
  id: number;
  company_id: number;
  title: string;
  customer_id: number | null;
  customer?: CustomerRef | null;
  lead_id: number | null;
  stage_id: number;
  stage?: StageRef;
  value: Money;
  currency: string;
  probability: number;
  expected_close_date: string | null;
  status: OpportunityStatus;
  lost_reason: string | null;
  owner_id: number | null;
  owner?: UserRef | null;
  closed_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface BoardColumn {
  stage: StageRef & { is_won: boolean; is_lost: boolean };
  count: number;
  total_value: Money;
  items: Opportunity[];
}

export interface PipelineSummary {
  stages: { stage: StageRef & { is_won: boolean; is_lost: boolean }; count: number; total_value: Money; weighted_value: Money }[];
  open: { count: number; total_value: Money; weighted_value: Money };
  win_rate_90d: { won: number; lost: number; rate: string | null };
}

export interface LeadConvertResult {
  lead: Lead;
  customer: Customer;
  customer_created: boolean;
  opportunity: Opportunity | null;
}

// ---------------------------------------------------------------------------
// Documents
// ---------------------------------------------------------------------------

export interface DocumentLine {
  id: number;
  line_no: number;
  product_id: number | null;
  description: string;
  quantity: Money;
  quantity_delivered?: Money;
  uom: string | null;
  unit_price: Money;
  discount_percent: Money;
  tax_rate: Money;
  line_subtotal: Money;
  tax_amount: Money;
  line_total: Money;
}

export interface LineInput {
  product_id: number | null;
  description: string;
  quantity: string;
  uom: string | null;
  unit_price: string;
  discount_percent: string | null;
  tax_rate: string | null;
}

export interface Quotation {
  id: number;
  company_id: number;
  number: string;
  customer_id: number;
  customer?: CustomerRef;
  date: string;
  valid_until: string | null;
  status: QuotationStatus;
  currency: string;
  subtotal: Money;
  discount_total: Money;
  tax_total: Money;
  total: Money;
  notes: string | null;
  terms: string | null;
  rejection_reason: string | null;
  sales_rep_id: number | null;
  sales_rep?: UserRef | null;
  created_by: number | null;
  converted_sales_order_id: number | null;
  lines?: DocumentLine[];
  lines_count?: number;
  sent_at: string | null;
  accepted_at: string | null;
  rejected_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface QuotationInput {
  customer_id: number;
  date?: string;
  valid_until?: string | null;
  notes?: string | null;
  terms?: string | null;
  sales_rep_id?: number | null;
  lines: LineInput[];
}

export interface Delivery {
  id: number;
  company_id: number;
  number: string;
  sales_order_id: number;
  sales_order?: { id: number; number: string; status: SalesOrderStatus; customer_id: number };
  delivery_date: string | null;
  status: DeliveryStatus;
  tracking_mode: 'manual';
  carrier: string | null;
  tracking_number: string | null;
  shipped_at: string | null;
  delivered_at: string | null;
  cancelled_at: string | null;
  notes: string | null;
  lines?: { id: number; sales_order_line_id: number; quantity: Money; description: string | null; product_id: number | null; uom: string | null }[];
  created_at: string | null;
  updated_at: string | null;
}

export interface SalesOrder {
  id: number;
  company_id: number;
  number: string;
  customer_id: number;
  customer?: CustomerRef;
  quotation_id: number | null;
  order_date: string;
  expected_delivery_date: string | null;
  status: SalesOrderStatus;
  currency: string;
  subtotal: Money;
  discount_total: Money;
  tax_total: Money;
  total: Money;
  shipping_address: PostalAddress & { name: string | null };
  warehouse_id: number | null;
  notes: string | null;
  terms: string | null;
  sales_rep_id: number | null;
  sales_rep?: UserRef | null;
  created_by: number | null;
  confirmed_at: string | null;
  approved_by: number | null;
  approver?: UserRef | null;
  approved_at: string | null;
  cancelled_reason: string | null;
  cancelled_at: string | null;
  lines?: DocumentLine[];
  lines_count?: number;
  deliveries?: Delivery[];
  deliveries_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export type SalesOrderInput = Record<string, unknown> & { customer_id: number; lines: LineInput[] };

// ---------------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------------

export type PerformanceGroup = 'rep' | 'customer' | 'month';

export interface PerformanceRow {
  key: number | string | null;
  rep?: UserRef | null;
  customer?: CustomerRef | null;
  month?: string | null;
  quotations: { count: number; total: Money; converted: number };
  orders: { count: number; total: Money };
  conversion_rate: string | null;
}

export interface PerformanceReport {
  from: string;
  to: string;
  group_by: PerformanceGroup;
  rows: PerformanceRow[];
  totals: { quotations: { count: number; total: Money; converted: number }; orders: { count: number; total: Money }; conversion_rate: string | null };
}

// ---------------------------------------------------------------------------
// Pickers (other modules' endpoints)
// ---------------------------------------------------------------------------

export interface UserOption {
  id: number;
  name: string;
  email?: string;
}

export interface ProductOption {
  id: number;
  sku: string;
  name: string;
  sale_price: Money;
  tax_rate: Money | null;
  uom?: { id: number; name: string; symbol: string } | null;
  is_active?: boolean;
}

export interface WarehouseOption {
  id: number;
  name: string;
  code: string;
}

// ---------------------------------------------------------------------------
// Resources
// ---------------------------------------------------------------------------

export const customers = resource<Customer, CustomerInput>('customers', 'Customer');
export const leads = resource<Lead, Record<string, unknown>>('leads', 'Lead');
export const opportunities = resource<Opportunity, Record<string, unknown>>('opportunities', 'Opportunity');
export const quotations = resource<Quotation, QuotationInput>('quotations', 'Quotation');
export const salesOrders = resource<SalesOrder, SalesOrderInput>('sales-orders', 'Sales order');
export const deliveries = resource<Delivery, Record<string, unknown>>('deliveries', 'Delivery');

export const salesApi = {
  customerSummary: (id: number) => get<{ data: CustomerSummary }>(`/customers/${id}/summary`).then((r) => r.data),
  customerMap: () => get<{ data: CustomerMapPoint[] }>('/customers/map').then((r) => r.data),
  customersAll: () => get<{ data: CustomerRef[] }>('/customers/all').then((r) => r.data),

  createContact: (customerId: number, body: Record<string, unknown>) =>
    api.post<{ data: CustomerContact }>(`/customers/${customerId}/contacts`, body).then((r) => r.data.data),
  updateContact: (customerId: number, id: number, body: Record<string, unknown>) =>
    api.put<{ data: CustomerContact }>(`/customers/${customerId}/contacts/${id}`, body).then((r) => r.data.data),
  deleteContact: (customerId: number, id: number) => api.delete(`/customers/${customerId}/contacts/${id}`).then(() => undefined),

  communications: (customerId: number, params: Record<string, unknown> = {}) =>
    get<Paginated<CustomerCommunication>>(`/customers/${customerId}/communications`, params),
  createCommunication: (customerId: number, body: Record<string, unknown>) =>
    api.post<{ data: CustomerCommunication }>(`/customers/${customerId}/communications`, body).then((r) => r.data.data),
  updateCommunication: (customerId: number, id: number, body: Record<string, unknown>) =>
    api.put<{ data: CustomerCommunication }>(`/customers/${customerId}/communications/${id}`, body).then((r) => r.data.data),
  deleteCommunication: (customerId: number, id: number) => api.delete(`/customers/${customerId}/communications/${id}`).then(() => undefined),

  stages: () => get<{ data: PipelineStage[] }>('/pipeline-stages').then((r) => r.data),
  board: (params: Record<string, unknown> = {}) => get<{ data: BoardColumn[] }>('/opportunities/board', params).then((r) => r.data),
  moveStage: (id: number, body: { stage_id: number; lost_reason?: string | null }) =>
    api.patch<{ data: Opportunity }>(`/opportunities/${id}/stage`, body).then((r) => r.data.data),
  pipelineSummary: () => get<{ data: PipelineSummary }>('/sales/pipeline-summary').then((r) => r.data),

  assignLead: (id: number, userId: number) => api.post<{ data: Lead }>(`/leads/${id}/assign`, { user_id: userId }).then((r) => r.data.data),
  convertLead: (id: number, body: { create_opportunity: boolean; stage_id?: number | null }) =>
    api.post<{ data: LeadConvertResult }>(`/leads/${id}/convert`, body).then((r) => r.data.data),

  createDelivery: (orderId: number, body: Record<string, unknown>) =>
    api.post<{ data: Delivery }>(`/sales-orders/${orderId}/deliveries`, body).then((r) => r.data.data),
  delivery: (id: number) => get<{ data: Delivery }>(`/deliveries/${id}`).then((r) => r.data),

  performance: (params: { from?: string; to?: string; group_by: PerformanceGroup }) =>
    get<{ data: PerformanceReport }>('/sales/reports/performance', params).then((r) => r.data),
  pipelineReport: () => get<{ data: PipelineSummary }>('/sales/reports/pipeline').then((r) => r.data),

  users: () => get<Paginated<UserOption>>('/users', { per_page: 100 }).then((r) => r.data),
  products: (search: string) => get<Paginated<ProductOption>>('/products', { search, per_page: 20, 'filter[is_active]': 'true' }).then((r) => r.data),
  warehouses: () => get<Paginated<WarehouseOption>>('/warehouses', { per_page: 100 }).then((r) => r.data),
};
