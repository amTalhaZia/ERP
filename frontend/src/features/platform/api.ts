import { get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';

export interface RoleRef {
  slug: string;
  name: string;
}

/** A company login account as seen by platform staff — account data only, never HR/financial records. */
export interface CompanyMember {
  id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  roles: RoleRef[];
  is_active: boolean;
  branch_restricted: boolean;
  two_factor_enabled: boolean;
  last_login_at: string | null;
  joined_at: string | null;
}

export interface PlatformCompany {
  id: number;
  name: string;
  legal_name: string | null;
  slug: string;
  code: string;
  email: string | null;
  phone: string | null;
  country: string | null;
  city: string | null;
  currency: string;
  timezone: string;
  logo_url: string | null;
  is_active: boolean;
  is_demo: boolean;
  branches_count?: number;
  users_count?: number;
  employees_count?: number | null;
  owners?: { id: number; name: string; email: string }[];
  created_at: string;
  deleted_at: string | null;
}

export interface PlatformUser {
  id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  is_active: boolean;
  platform_role: 'platform.owner' | 'platform.admin' | null;
  is_platform_admin: boolean;
  email_verified_at: string | null;
  two_factor_enabled: boolean;
  last_login_at: string | null;
  deactivated_at: string | null;
  memberships_count?: number;
  memberships?: { company_id: number; company_name: string; roles?: RoleRef[]; is_active: boolean; is_default: boolean; joined_at: string | null }[];
}

export interface SystemOverview {
  app: { name: string; version: string; framework: string; environment: string; debug: boolean; timezone: string; maintenance: boolean };
  php: { version: string; memory_limit: string; max_execution_time: number; upload_max_filesize: string };
  database: { driver: string; version: string | null; reachable: boolean };
  services: Record<string, string>;
  counts: { companies: number; active_companies: number; users: number; active_users: number; platform_users: number };
  storage: { disk?: string; path_free_bytes: number | null; path_total_bytes: number | null };
  tables: { name: string; rows: number | null; size_bytes: number | null }[];
  generated_at: string;
}

export interface CompanyUsage {
  id: number;
  name: string;
  code: string;
  is_active: boolean;
  is_demo: boolean;
  archived: boolean;
  rows: number;
  db_bytes: number;
  file_bytes: number;
  tables: { name: string; rows: number }[];
}

export type CompanyInput = {
  name: string;
  legal_name?: string | null;
  email?: string | null;
  country?: string | null;
  currency?: string;
  timezone?: string;
  is_demo?: boolean;
  owner?: { name: string; email: string };
};

export const platformCompanies = resource<PlatformCompany, CompanyInput>('platform/companies', 'Company');
export const platformUsers = resource<PlatformUser, { is_active?: boolean; platform_role?: string | null }>('platform/users', 'User');

export const platformApi = {
  members: (companyId: number) => get<{ data: CompanyMember[] }>(`/platform/companies/${companyId}/members`).then((r) => r.data),
  usage: (refresh = false) =>
    get<{ data: { companies: CompanyUsage[]; shared_bytes: number; generated_at: string } }>('/platform/system/usage', refresh ? { refresh: 1 } : undefined).then((r) => r.data),
  system: () => get<{ data: SystemOverview }>('/platform/system').then((r) => r.data),
};
