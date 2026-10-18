import { api, get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';

export interface RoleRef {
  id: number;
  name: string;
  slug: string;
}

export interface CompanyUser {
  id: number; // user id
  membership_id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar_url: string | null;
  user_is_active: boolean;
  email_verified_at: string | null;
  last_login_at: string | null;
  is_active: boolean;
  is_default: boolean;
  branch_ids: number[] | null;
  roles?: RoleRef[];
  joined_at: string | null;
}

export interface PermissionItem {
  id: number;
  slug: string;
  description: string;
  module?: string;
}

export interface PermissionModule {
  module: string;
  permissions: PermissionItem[];
}

export interface Role {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  is_system: boolean;
  is_locked: boolean;
  users_count?: number;
  permissions_count?: number;
  permissions?: PermissionItem[];
}

export interface AuditLog {
  id: number;
  actor: { id: number | null; name: string } | null;
  actor_name: string | null;
  action: string;
  entity_type: string | null;
  entity_id: number | null;
  entity_label: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  context: Record<string, unknown> | null;
  ip_address: string | null;
  country_code?: string | null;
  city?: string | null;
  user_agent: string | null;
  created_at: string;
}

export interface AppNotification {
  id: string;
  type: string;
  title: string | null;
  body: string | null;
  url: string | null;
  is_read: boolean;
  read_at: string | null;
  created_at: string;
}

export type InviteInput = { name: string; email: string; role_ids: number[]; branch_ids: number[] | null };
export type MembershipInput = { role_ids?: number[]; branch_ids?: number[] | null; is_active?: boolean };
export type RoleInput = { name: string; description?: string | null; permission_ids: number[] };

export const companyUsers = resource<CompanyUser, InviteInput | MembershipInput>('users', 'User');
export const roles = resource<Role, RoleInput>('roles', 'Role');
export const auditLogs = resource<AuditLog>('audit-logs', 'Audit entry');

export const accessApi = {
  permissions: () => get<{ data: PermissionModule[] }>('/permissions').then((r) => r.data),
  resendInvite: (userId: number) => api.post(`/users/${userId}/resend-invite`),
};

export const notificationsApi = {
  list: (params: Record<string, unknown>) =>
    get<{ data: AppNotification[]; meta: { pagination: { page: number; per_page: number; total: number; last_page: number } } }>('/notifications', params),
  markRead: (id: string) => api.patch(`/notifications/${id}/read`),
  markAllRead: () => api.post('/notifications/read-all'),
  remove: (id: string) => api.delete(`/notifications/${id}`),
};
