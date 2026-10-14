import { api, get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import type { Money, Paginated } from '@/lib/api/types';
import { resource } from '@/lib/crud/resource';

/** Quantities are 4dp decimal strings (same transport as money). */
export type Qty = string;

export type ProductType = 'goods' | 'service';
export type StockMovementType =
  | 'receipt' | 'issue' | 'transfer_in' | 'transfer_out' | 'adjustment_in' | 'adjustment_out'
  | 'sale' | 'purchase' | 'return_in' | 'return_out' | 'opening';
export type StockTransferStatus = 'draft' | 'in_transit' | 'received' | 'cancelled';
export type StockAdjustmentStatus = 'draft' | 'posted';
export type StockAdjustmentReason = 'count' | 'damage' | 'loss' | 'correction' | 'expiry';
export type StorageLocationType = 'zone' | 'aisle' | 'rack' | 'bin';

export const PRODUCT_TYPES: { value: ProductType; label: string }[] = [
  { value: 'goods', label: 'Goods' },
  { value: 'service', label: 'Service' },
];
export const MOVEMENT_TYPES: StockMovementType[] = [
  'receipt', 'issue', 'transfer_in', 'transfer_out', 'adjustment_in', 'adjustment_out', 'sale', 'purchase', 'return_in', 'return_out', 'opening',
];
export const ADJUSTMENT_REASONS: { value: StockAdjustmentReason; label: string }[] = [
  { value: 'count', label: 'Stock count' },
  { value: 'damage', label: 'Damage' },
  { value: 'loss', label: 'Loss / theft' },
  { value: 'correction', label: 'Correction' },
  { value: 'expiry', label: 'Expiry' },
];
export const LOCATION_TYPES: StorageLocationType[] = ['zone', 'aisle', 'rack', 'bin'];

type Ref = { id: number; name: string };
type WarehouseRef = { id: number; name: string; code: string };
type ProductRef = { id: number; sku: string; name: string };

export interface UnitOfMeasure {
  id: number;
  company_id: number;
  name: string;
  symbol: string;
  precision: number;
  is_active: boolean;
  products_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ProductCategory {
  id: number;
  company_id: number;
  parent_id: number | null;
  parent?: Ref | null;
  name: string;
  code: string;
  is_active: boolean;
  children_count?: number;
  products_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface CategoryNode {
  id: number;
  name: string;
  code: string;
  products_count: number;
  children: CategoryNode[];
}

export interface Product {
  id: number;
  company_id: number;
  sku: string;
  barcode: string | null;
  name: string;
  description: string | null;
  type: ProductType;
  category_id: number | null;
  category?: Ref | null;
  uom_id: number | null;
  uom?: { id: number; name: string; symbol: string } | null;
  cost_price: Money;
  sale_price: Money;
  currency: string;
  tax_rate: string | null;
  reorder_level: Qty;
  reorder_quantity: Qty;
  track_batches: boolean;
  track_serials: boolean;
  is_active: boolean;
  image_url: string | null;
  attributes: Record<string, string | number | boolean | null> | null;
  quantity_on_hand?: Qty;
  created_at: string | null;
  updated_at: string | null;
}

export interface Warehouse {
  id: number;
  company_id: number;
  branch_id: number | null;
  branch?: Ref | null;
  name: string;
  code: string;
  address: { line1: string | null; line2: string | null; city: string | null; state: string | null; postal_code: string | null; country: string | null };
  latitude: string | null;
  longitude: string | null;
  is_active: boolean;
  is_default: boolean;
  locations_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface StorageLocation {
  id: number;
  company_id: number;
  warehouse_id: number;
  parent_id: number | null;
  parent?: { id: number; code: string; name: string } | null;
  code: string;
  name: string;
  type: StorageLocationType;
  is_active: boolean;
  children_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface StockLevel {
  id: number;
  product_id: number;
  product?: ProductRef & { reorder_level: Qty; uom: { id: number; name: string; symbol: string } | null };
  warehouse_id: number;
  warehouse?: WarehouseRef;
  location_id: number | null;
  location?: { id: number; code: string; name: string } | null;
  quantity_on_hand: Qty;
  quantity_reserved: Qty;
  quantity_available: Qty;
  average_cost: Money;
  stock_value: Money;
  updated_at: string | null;
}

export interface StockMovement {
  id: number;
  company_id: number;
  product_id: number;
  product?: ProductRef;
  warehouse_id: number;
  warehouse?: WarehouseRef;
  location_id: number | null;
  batch_id: number | null;
  batch?: { id: number; batch_number: string; expires_at: string | null } | null;
  type: StockMovementType;
  /** Signed: positive = in, negative = out. */
  quantity: Qty;
  unit_cost: Money;
  total_cost: Money;
  balance_after: Qty;
  reference_type: string | null;
  reference_id: number | null;
  reason: string | null;
  moved_at: string | null;
  created_by: number | null;
  creator?: Ref | null;
  created_at: string | null;
}

export interface StockTransferLine {
  id: number;
  product_id: number;
  product: ProductRef | null;
  quantity: Qty;
  unit_cost: Money;
}

export interface StockTransfer {
  id: number;
  company_id: number;
  number: string;
  status: StockTransferStatus;
  from_warehouse_id: number;
  from_warehouse?: WarehouseRef;
  to_warehouse_id: number;
  to_warehouse?: WarehouseRef;
  notes: string | null;
  lines?: StockTransferLine[];
  lines_count?: number;
  shipped_at: string | null;
  received_at: string | null;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface StockAdjustmentLine {
  id: number;
  product_id: number;
  product: ProductRef | null;
  counted_quantity: Qty;
  system_quantity: Qty;
  difference: Qty;
  unit_cost: Money;
}

export interface StockAdjustment {
  id: number;
  company_id: number;
  number: string;
  status: StockAdjustmentStatus;
  reason: StockAdjustmentReason;
  warehouse_id: number;
  warehouse?: WarehouseRef;
  notes: string | null;
  lines?: StockAdjustmentLine[];
  lines_count?: number;
  posted_at: string | null;
  created_by: number | null;
  posted_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

// ---- Inputs ----------------------------------------------------------------

export type ProductInput = {
  sku: string;
  barcode?: string | null;
  name: string;
  description?: string | null;
  type?: ProductType;
  category_id?: number | null;
  uom_id?: number | null;
  cost_price?: string;
  sale_price?: string;
  tax_rate?: string | null;
  reorder_level?: string;
  reorder_quantity?: string;
  track_batches?: boolean;
  track_serials?: boolean;
  is_active?: boolean;
};

export type CategoryInput = { name: string; code: string; parent_id?: number | null; is_active?: boolean };
export type UomInput = { name: string; symbol: string; precision?: number; is_active?: boolean };

export type WarehouseInput = {
  name: string;
  code: string;
  branch_id?: number | null;
  address_line1?: string | null;
  address_line2?: string | null;
  city?: string | null;
  state?: string | null;
  postal_code?: string | null;
  country?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  is_active?: boolean;
  is_default?: boolean;
};

export type LocationInput = { code: string; name: string; type?: StorageLocationType; parent_id?: number | null; is_active?: boolean };

export type TransferInput = {
  from_warehouse_id: number;
  to_warehouse_id: number;
  notes?: string | null;
  lines: { product_id: number; quantity: string }[];
};

export type AdjustmentInput = {
  warehouse_id: number;
  reason: StockAdjustmentReason;
  notes?: string | null;
  lines: { product_id: number; counted_quantity: string; unit_cost?: string | null }[];
};

export type ReceiveLineInput = {
  product_id: number;
  quantity: string;
  unit_cost: string;
  batch_number?: string | null;
  manufactured_at?: string | null;
  expires_at?: string | null;
  serials?: string[] | null;
};
export type IssueLineInput = { product_id: number; quantity: string; batch_id?: number | null; serials?: string[] | null };
export type StockOperationInput<L> = {
  warehouse_id: number;
  location_id?: number | null;
  reason?: string | null;
  moved_at?: string | null;
  lines: L[];
};

// ---- Resources ---------------------------------------------------------------

export const products = resource<Product, ProductInput>('products', 'Product');
export const lowStockProducts = resource<Product, ProductInput>('products/low-stock', 'Product');
export const categories = resource<ProductCategory, CategoryInput>('product-categories', 'Category');
export const units = resource<UnitOfMeasure, UomInput>('units-of-measure', 'Unit');
export const warehouses = resource<Warehouse, WarehouseInput>('warehouses', 'Warehouse');
export const locations = resource<StorageLocation, LocationInput>('locations', 'Location');
export const stockLevels = resource<StockLevel>('stock/levels', 'Stock level');
export const stockMovements = resource<StockMovement>('stock/movements', 'Movement');
export const transfers = resource<StockTransfer, TransferInput>('stock-transfers', 'Transfer');
export const adjustments = resource<StockAdjustment, AdjustmentInput>('stock-adjustments', 'Adjustment');

/**
 * Query-key fragments touched by any stock-changing operation. Invalidate
 * these after receive/issue/ship/receive/post so every view refreshes.
 */
export const STOCK_KEYS = ['products', 'products/low-stock', 'stock/levels', 'stock/movements', 'stock-transfers', 'stock-adjustments', 'inventory-reports', 'product-stock', 'product-movements'];
export const isStockKey = (key: readonly unknown[]) => key.some((k) => typeof k === 'string' && STOCK_KEYS.includes(k));

// ---- Insufficient stock (409) ------------------------------------------------

export interface InsufficientStockDetails {
  product_id: number;
  sku: string;
  warehouse_id: number;
  location_id: number | null;
  available: Qty;
  requested: Qty;
}

/** ApiError carrying the 409 `details` payload the shared client normaliser drops. */
export interface InventoryApiError extends ApiError {
  details?: InsufficientStockDetails;
}

export const INSUFFICIENT_STOCK = 'inventory.insufficient_stock';

export const isInsufficientStock = (e: unknown): e is InventoryApiError & { details: InsufficientStockDetails } =>
  typeof e === 'object' && e !== null && (e as InventoryApiError).code === INSUFFICIENT_STOCK && !!(e as InventoryApiError).details;

/**
 * POST that keeps the 409 body intact. The shared Axios interceptor maps errors
 * to ApiError without `details`, so we accept 409 as a resolved response here
 * and rethrow it ourselves with the details attached.
 */
export async function postKeepingConflict<T>(url: string, body: unknown): Promise<T> {
  const res = await api.post<T & { message?: string; code?: string; details?: InsufficientStockDetails }>(url, body, {
    validateStatus: (s) => (s >= 200 && s < 300) || s === 409,
  });
  if (res.status === 409) {
    const b = res.data;
    const err: InventoryApiError = { status: 409, code: b.code ?? 'conflict', message: b.message ?? 'Conflict', details: b.details };
    throw err;
  }
  return res.data;
}

export const stockApi = {
  receive: (body: StockOperationInput<ReceiveLineInput>) => postKeepingConflict<{ data: StockMovement[] }>('/stock/receive', body).then((r) => r.data),
  issue: (body: StockOperationInput<IssueLineInput>) => postKeepingConflict<{ data: StockMovement[] }>('/stock/issue', body).then((r) => r.data),
  shipTransfer: (id: number) => postKeepingConflict<{ data: StockTransfer }>(`/stock-transfers/${id}/ship`, {}).then((r) => r.data),
  postAdjustment: (id: number) => postKeepingConflict<{ data: StockAdjustment }>(`/stock-adjustments/${id}/post`, {}).then((r) => r.data),
  productStock: (id: number | string) => get<{ data: StockLevel[] }>(`/products/${id}/stock`).then((r) => r.data),
  productMovements: (id: number | string, params: Record<string, unknown>) => get<Paginated<StockMovement>>(`/products/${id}/movements`, params),
  uploadImage: (id: number, file: File) => {
    const fd = new FormData();
    fd.append('image', file);
    return api.post<{ data: Product }>(`/products/${id}/image`, fd).then((r) => r.data.data);
  },
  categoryTree: () => get<{ data: CategoryNode[] }>('/product-categories/tree').then((r) => r.data),
  warehouseLocations: (warehouseId: number | string, params: Record<string, unknown> = {}) =>
    get<Paginated<StorageLocation>>(`/warehouses/${warehouseId}/locations`, params),
  createLocation: (warehouseId: number | string, body: LocationInput) =>
    api.post<{ data: StorageLocation }>(`/warehouses/${warehouseId}/locations`, body).then((r) => r.data.data),
};

// ---- Reports -------------------------------------------------------------------

export interface ValuationRow {
  warehouse_id: number;
  warehouse_name: string;
  warehouse_code: string;
  item_count: number;
  quantity_on_hand: Qty;
  stock_value: Money;
}
export interface ValuationCategoryRow {
  category_id: number | null;
  category_name: string;
  item_count: number;
  quantity_on_hand: Qty;
  stock_value: Money;
}
export interface Valuation {
  currency: string | null;
  as_of: string;
  warehouses: ValuationRow[];
  total: { item_count: number; quantity_on_hand: Qty; stock_value: Money };
  categories?: ValuationCategoryRow[];
}
export interface MovementSummaryRow {
  period: string;
  movements: number;
  quantity_in: Qty;
  quantity_out: Qty;
  value_in: Money;
  value_out: Money;
}
export interface MovementSummary {
  group: 'day' | 'month';
  from: string;
  to: string;
  currency: string | null;
  rows: MovementSummaryRow[];
  totals: { movements: number; quantity_in: Qty; quantity_out: Qty; value_in: Money; value_out: Money };
}

export const inventoryReports = {
  valuation: (p: Record<string, unknown>) => get<{ data: Valuation }>('/inventory/reports/valuation', p).then((r) => r.data),
  movementSummary: (p: Record<string, unknown>) => get<{ data: MovementSummary }>('/inventory/reports/movement-summary', p).then((r) => r.data),
};
