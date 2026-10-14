import { api, get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';

export interface Address {
  line1: string | null;
  line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
}

export interface Company {
  id: number;
  name: string;
  legal_name: string | null;
  slug: string;
  code: string;
  email: string | null;
  phone: string | null;
  website: string | null;
  tax_number: string | null;
  registration_number: string | null;
  address: Address;
  currency: string;
  timezone: string;
  locale: string;
  date_format: string;
  fiscal_year_start_month: number;
  logo_url: string | null;
  primary_color: string | null;
  is_active: boolean;
  is_demo: boolean;
  branches_count?: number;
  users_count?: number;
}

export interface Branch {
  id: number;
  company_id: number;
  name: string;
  code: string;
  is_head_office: boolean;
  email: string | null;
  phone: string | null;
  address: Address;
  latitude: string | null;
  longitude: string | null;
  timezone: string | null;
  is_active: boolean;
  departments_count?: number;
}

export interface Department {
  id: number;
  name: string;
  code: string;
  description: string | null;
  branch_id: number | null;
  branch?: { id: number; name: string } | null;
  parent_id: number | null;
  parent?: { id: number; name: string } | null;
  is_active: boolean;
  children_count?: number;
}

export interface DepartmentNode {
  id: number;
  name: string;
  code: string;
  children: DepartmentNode[];
}

export type BranchInput = {
  name: string;
  code: string;
  is_head_office?: boolean;
  email?: string | null;
  phone?: string | null;
  address_line1?: string | null;
  address_line2?: string | null;
  city?: string | null;
  state?: string | null;
  postal_code?: string | null;
  country?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  is_active?: boolean;
};

export type DepartmentInput = {
  name: string;
  code: string;
  description?: string | null;
  branch_id?: number | null;
  parent_id?: number | null;
  is_active?: boolean;
};

export const branches = resource<Branch, BranchInput>('branches', 'Branch');
export const departments = resource<Department, DepartmentInput>('departments', 'Department');

export const companyApi = {
  get: () => get<{ data: Company }>('/company').then((r) => r.data),
  update: (body: Record<string, unknown>) => api.put<{ data: Company }>('/company', body).then((r) => r.data.data),
  uploadLogo: (file: File) => {
    const fd = new FormData();
    fd.append('logo', file);
    return api.post<{ data: Company }>('/company/logo', fd).then((r) => r.data.data);
  },
  deleteLogo: () => api.delete('/company/logo'),
  departmentTree: () => get<{ data: DepartmentNode[] }>('/departments/tree').then((r) => r.data),
};
