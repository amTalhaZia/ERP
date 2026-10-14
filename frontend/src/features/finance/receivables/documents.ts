import type { Permission } from '@/lib/auth/permissions';
import type { resource } from '@/lib/crud/resource';
import { bills, invoices, type AllocationType, type Bill, type BillInput, type Invoice, type InvoiceInput, type PartnerType, type PaymentType } from './api';

export type DocKind = 'invoice' | 'bill';
export type AnyDoc = Invoice | Bill;

export interface DocConfig {
  kind: DocKind;
  label: string;
  plural: string;
  path: string;
  partnerType: Extract<PartnerType, 'customer' | 'supplier'>;
  partnerLabel: string;
  dateLabel: string;
  allocationType: AllocationType;
  paymentType: PaymentType;
  taxScope: 'sales' | 'purchase';
  accountTypes: string;
  accountPlaceholder: string;
  priceField: 'sale_price' | 'cost_price';
  perm: { view: Permission; create: Permission; update: Permission; approve: Permission; delete: Permission };
}

export const DOC_CONFIG: Record<DocKind, DocConfig> = {
  invoice: {
    kind: 'invoice',
    label: 'Invoice',
    plural: 'Invoices',
    path: '/finance/invoices',
    partnerType: 'customer',
    partnerLabel: 'Customer',
    dateLabel: 'Invoice date',
    allocationType: 'invoice',
    paymentType: 'receipt',
    taxScope: 'sales',
    accountTypes: 'revenue',
    accountPlaceholder: 'Default sales revenue',
    priceField: 'sale_price',
    perm: { view: 'invoices.view', create: 'invoices.create', update: 'invoices.update', approve: 'invoices.approve', delete: 'invoices.delete' },
  },
  bill: {
    kind: 'bill',
    label: 'Bill',
    plural: 'Supplier bills',
    path: '/finance/bills',
    partnerType: 'supplier',
    partnerLabel: 'Supplier',
    dateLabel: 'Bill date',
    allocationType: 'bill',
    paymentType: 'payment',
    taxScope: 'purchase',
    accountTypes: 'expense,asset',
    accountPlaceholder: 'Default expense',
    priceField: 'cost_price',
    perm: { view: 'bills.view', create: 'bills.create', update: 'bills.create', approve: 'bills.approve', delete: 'bills.create' },
  },
};

export type DocInput = InvoiceInput | BillInput;
export type DocResource = ReturnType<typeof resource<AnyDoc, DocInput>>;
/** Invoice or bill resource, widened to one callable shape. */
export const docResource = (kind: DocKind) => (kind === 'invoice' ? invoices : bills) as unknown as DocResource;
export const isInvoice = (d: AnyDoc): d is Invoice => 'invoice_date' in d;
export const docDate = (d: AnyDoc) => (isInvoice(d) ? d.invoice_date : d.bill_date);
export const docPartnerId = (d: AnyDoc) => (isInvoice(d) ? d.customer_id : d.supplier_id);
export const docPartner = (d: AnyDoc) => (isInvoice(d) ? d.customer : d.supplier);
