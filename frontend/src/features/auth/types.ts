export interface CompanyMembership {
  id: number;
  name: string;
  slug: string;
  logo_url: string | null;
  currency: string;
  timezone: string;
  role: string;
  /** Null = all branches */
  branch_ids: number[] | null;
  is_default: boolean;
}

export interface Branch {
  id: number;
  company_id: number;
  name: string;
  code: string;
  is_head_office: boolean;
}

export interface SessionUser {
  id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  is_platform_admin: boolean;
  platform_role: 'platform.owner' | 'platform.admin' | null;
  two_factor_enabled: boolean;
  email_verified_at: string | null;
  last_login_at: string | null;
  preferences: {
    theme: 'light' | 'dark' | 'system';
    high_contrast: boolean;
    locale: string;
  };
}

export interface SessionPayload {
  user: SessionUser;
  companies: CompanyMembership[];
  /** The company the server considers current (from header or default). */
  current_company: CompanyMembership | null;
  /** Permission slugs for the current company. */
  permissions: string[];
  branches: Branch[];
}

export interface LoginRequest {
  email: string;
  password: string;
  remember?: boolean;
}

export interface LoginResponse {
  /** When 2FA is enabled, the first step returns a challenge instead of a session. */
  two_factor: boolean;
  session?: SessionPayload;
}
