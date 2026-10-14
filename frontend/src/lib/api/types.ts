/** Shared API envelope types matching backend API Resources. */

export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface Paginated<T> {
  data: T[];
  meta: { pagination: Pagination };
}

export interface Single<T> {
  data: T;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  /** e.g. `-created_at` for descending */
  sort?: string;
  from?: string;
  to?: string;
  branch_id?: number;
  [key: `filter[${string}]`]: string | number | boolean | undefined;
}

/** Money is always a decimal string from the API (never a float). */
export type Money = string;

export type ISODate = string;
export type ISODateTime = string;
