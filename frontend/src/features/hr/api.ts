'use client';

import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api, get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import type { Money, Paginated } from '@/lib/api/types';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { useCan } from '@/lib/auth/useCan';

// ---- Enums (mirror app/Enums/HR) ---------------------------------------------

export type EmployeeStatus = 'probation' | 'active' | 'on_leave' | 'suspended' | 'terminated';
export type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'intern';
export type Gender = 'male' | 'female' | 'other' | 'prefer_not_to_say';
export type MaritalStatus = 'single' | 'married' | 'divorced' | 'widowed';
export type ContractStatus = 'active' | 'expired' | 'terminated';
export type DocumentCategory = 'contract' | 'id' | 'certificate' | 'visa' | 'other';
export type AttendanceStatus = 'present' | 'absent' | 'late' | 'half_day' | 'on_leave' | 'holiday' | 'weekend';
export type AttendanceSource = 'manual' | 'import' | 'biometric' | 'self';
export type LeaveRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';
export type ReviewStatus = 'draft' | 'submitted' | 'acknowledged';

const opts = <T extends string>(values: readonly T[], labels: Partial<Record<T, string>> = {}) =>
  values.map((v) => ({ value: v, label: labels[v] ?? v.charAt(0).toUpperCase() + v.slice(1).replace(/_/g, ' ') }));

export const EMPLOYEE_STATUSES = opts<EmployeeStatus>(['probation', 'active', 'on_leave', 'suspended', 'terminated']);
/** Statuses an edit form may set (terminate/reactivate are explicit actions). */
export const EDITABLE_STATUSES = opts<EmployeeStatus>(['probation', 'active', 'on_leave', 'suspended']);
export const EMPLOYMENT_TYPES = opts<EmploymentType>(['full_time', 'part_time', 'contract', 'intern'], { full_time: 'Full-time', part_time: 'Part-time' });
export const GENDERS = opts<Gender>(['male', 'female', 'other', 'prefer_not_to_say']);
export const MARITAL_STATUSES = opts<MaritalStatus>(['single', 'married', 'divorced', 'widowed']);
export const CONTRACT_STATUSES = opts<ContractStatus>(['active', 'expired', 'terminated']);
export const DOCUMENT_CATEGORIES = opts<DocumentCategory>(['contract', 'id', 'certificate', 'visa', 'other'], { id: 'ID' });
export const ATTENDANCE_STATUSES = opts<AttendanceStatus>(['present', 'absent', 'late', 'half_day', 'on_leave', 'holiday', 'weekend']);
export const LEAVE_STATUSES = opts<LeaveRequestStatus>(['pending', 'approved', 'rejected', 'cancelled']);
export const REVIEW_STATUSES = opts<ReviewStatus>(['draft', 'submitted', 'acknowledged']);

export const typeLabel = (t: string | null | undefined) => EMPLOYMENT_TYPES.find((o) => o.value === t)?.label ?? (t ? t.replace(/_/g, ' ') : '—');

/** Accepted upload extensions (config/erp.php uploads.allowed_mimes) and size. */
export const UPLOAD_ACCEPT = '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt';
export const UPLOAD_MAX_BYTES = 10 * 1024 * 1024;
export const PHOTO_MAX_BYTES = 2 * 1024 * 1024;

// ---- Shapes (mirror app/Http/Resources/HR) -------------------------------------

type Ref = { id: number; name: string };

export interface Employee {
  id: number;
  company_id: number;
  employee_code: string;
  user_id: number | null;
  user?: { id: number; email: string } | null;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  phone: string | null;
  date_of_birth: string | null;
  gender: Gender | null;
  marital_status: MaritalStatus | null;
  address: { line1: string | null; line2: string | null; city: string | null; state: string | null; postal_code: string | null; country: string | null };
  emergency_contact: { name: string | null; phone: string | null; relationship: string | null };
  branch_id: number | null;
  branch?: Ref | null;
  department_id: number | null;
  department?: Ref | null;
  designation_id: number | null;
  designation?: { id: number; title: string } | null;
  manager_id: number | null;
  manager?: { id: number; name: string; employee_code: string } | null;
  employment_type: EmploymentType;
  status: EmployeeStatus;
  hired_at: string | null;
  probation_ends_at: string | null;
  terminated_at: string | null;
  termination_reason: string | null;
  photo_url: string | null;
  base_salary: Money | null;
  currency: string | null;
  bank_name: string | null;
  notes: string | null;
  reports_count?: number;
  /** Present only when the viewer may see sensitive data (employees.update). Masked. */
  sensitive?: { national_id: string | null; bank_account_number: string | null; bank_iban: string | null; tax_identifier: string | null };
  created_at?: string;
  updated_at?: string;
}

export type EmployeeInput = Record<string, unknown>;

export interface DirectoryRow {
  id: number;
  employee_code: string;
  name: string;
  email: string;
  status: EmployeeStatus;
  department_id: number | null;
  department: string | null;
  branch_id: number | null;
  designation_id: number | null;
  designation: string | null;
  manager_id: number | null;
  photo_url: string | null;
}

export interface OrgNode extends DirectoryRow {
  children: OrgNode[];
}

export interface Designation {
  id: number;
  title: string;
  code: string;
  level: number;
  is_active: boolean;
  employees_count?: number;
}

export interface Contract {
  id: number;
  employee_id: number;
  contract_type: EmploymentType;
  start_date: string;
  end_date: string | null;
  salary: Money;
  currency: string;
  working_hours_per_week: string | number | null;
  has_document: boolean;
  status: ContractStatus;
  notes: string | null;
  updated_at?: string;
}

export interface EmployeeDocument {
  id: number;
  employee_id: number;
  title: string;
  category: DocumentCategory;
  mime: string | null;
  size_bytes: number | null;
  expires_at: string | null;
  is_expired: boolean;
  is_confidential: boolean;
  uploader?: Ref | null;
  download_url: string;
  created_at: string;
}

export interface Shift {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  break_minutes: number;
  days_of_week: number[];
  is_default: boolean;
  assignments_count?: number;
  updated_at?: string;
}

export interface ShiftAssignment {
  id: number;
  employee_id: number;
  shift_id: number;
  shift?: Ref;
  effective_from: string;
  effective_to: string | null;
  created_at: string;
}

export interface Holiday {
  id: number;
  branch_id: number | null;
  branch?: Ref | null;
  name: string;
  date: string;
  is_recurring: boolean;
}

export interface AttendanceRecord {
  id: number;
  employee_id: number;
  employee?: { id: number; name: string; employee_code: string; department_id: number | null };
  date: string;
  check_in: string | null;
  check_out: string | null;
  status: AttendanceStatus;
  worked_minutes: number | null;
  late_minutes: number | null;
  source: AttendanceSource;
  notes: string | null;
  updated_at?: string;
}

export interface AttendanceSummaryRow {
  employee_id: number;
  employee_code: string;
  name: string;
  department_id: number | null;
  present_days: number;
  late_days: number;
  absent_days: number;
  half_days: number;
  leave_days: number;
  worked_minutes: number;
  worked_hours: number;
  late_minutes: number;
}

export interface LeaveType {
  id: number;
  name: string;
  code: string;
  days_per_year: string;
  is_paid: boolean;
  requires_approval: boolean;
  carry_forward: boolean;
  max_carry_forward_days: string | null;
  color: string | null;
  is_active: boolean;
}

export interface LeaveBalance {
  id: number;
  employee_id: number;
  employee?: { id: number; name: string; employee_code: string };
  leave_type_id: number;
  leave_type?: { id: number; name: string; code: string; color: string | null };
  year: number;
  entitled: string;
  used: string;
  carried_forward: string;
  adjusted: string;
  remaining: string | number;
  updated_at?: string;
}

export interface LeaveRequest {
  id: number;
  employee_id: number;
  employee?: { id: number; name: string; employee_code: string; department_id: number | null };
  leave_type_id: number;
  leave_type?: { id: number; name: string; code: string; is_paid: boolean; color: string | null };
  start_date: string;
  end_date: string;
  days: string;
  reason: string | null;
  status: LeaveRequestStatus;
  approver?: Ref | null;
  approved_at: string | null;
  rejection_reason: string | null;
  has_attachment: boolean;
  created_at: string;
}

export interface ReviewRating {
  criterion: string;
  rating: number | string;
  comment?: string | null;
}

export interface PerformanceReview {
  id: number;
  employee_id: number;
  employee?: { id: number; name: string; employee_code: string };
  reviewer_id: number;
  reviewer?: Ref | null;
  cycle: string;
  period_start: string;
  period_end: string;
  status: ReviewStatus;
  overall_rating: string | number | null;
  ratings: ReviewRating[];
  strengths: string | null;
  improvements: string | null;
  goals: string | null;
  submitted_at: string | null;
  acknowledged_at: string | null;
  created_at: string;
  updated_at?: string;
}

// ---- Resources -------------------------------------------------------------------

export const employees = resource<Employee, EmployeeInput>('employees', 'Employee');
export const designations = resource<Designation, Record<string, unknown>>('designations', 'Designation');
export const contracts = resource<Contract, Record<string, unknown>>('employment-contracts', 'Contract');
export const shifts = resource<Shift, Record<string, unknown>>('shifts', 'Shift');
export const holidays = resource<Holiday, Record<string, unknown>>('holidays', 'Holiday');
export const attendance = resource<AttendanceRecord, Record<string, unknown>>('attendance', 'Attendance record');
export const leaveTypes = resource<LeaveType, Record<string, unknown>>('leave-types', 'Leave type');
export const leaveBalances = resource<LeaveBalance, Record<string, unknown>>('leave-balances', 'Leave balance');
export const leaveRequests = resource<LeaveRequest, Record<string, unknown>>('leave-requests', 'Leave request');
export const reviews = resource<PerformanceReview, Record<string, unknown>>('performance-reviews', 'Review');

/** Append values to FormData: booleans as 1/0, null/undefined skipped. */
export function toFormData(body: Record<string, unknown>): FormData {
  const fd = new FormData();
  for (const [k, v] of Object.entries(body)) {
    if (v === undefined || v === null) continue;
    if (v instanceof Blob) fd.append(k, v);
    else if (typeof v === 'boolean') fd.append(k, v ? '1' : '0');
    else fd.append(k, String(v));
  }
  return fd;
}

export const hrApi = {
  directory: () => get<{ data: DirectoryRow[] }>('/employees/directory').then((r) => r.data),
  orgChart: () => get<{ data: OrgNode[] }>('/employees/org-chart').then((r) => r.data),
  terminate: (id: number, body: { terminated_at: string; reason: string }) =>
    api.post<{ data: Employee }>(`/employees/${id}/terminate`, body).then((r) => r.data.data),
  reactivate: (id: number) => api.post<{ data: Employee }>(`/employees/${id}/reactivate`, {}).then((r) => r.data.data),
  uploadPhoto: (id: number, file: File) => api.post<{ data: Employee }>(`/employees/${id}/photo`, toFormData({ photo: file })).then((r) => r.data.data),

  documents: (employeeId: number | string) => get<{ data: EmployeeDocument[] }>(`/employees/${employeeId}/documents`).then((r) => r.data),
  uploadDocument: (employeeId: number, body: { title: string; category: string; file: File; expires_at?: string | null; is_confidential: boolean }) =>
    api.post<{ data: EmployeeDocument }>(`/employees/${employeeId}/documents`, toFormData(body)).then((r) => r.data.data),
  deleteDocument: (id: number) => api.delete(`/employee-documents/${id}`).then(() => undefined),
  downloadDocument: async (doc: Pick<EmployeeDocument, 'id' | 'title'>) => {
    const res = await api.get<Blob>(`/employee-documents/${doc.id}/download`, { responseType: 'blob' });
    const disposition = String(res.headers['content-disposition'] ?? '');
    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);
    saveBlob(res.data, match?.[1] ? decodeURIComponent(match[1]) : doc.title);
  },

  /** Contract create/update; multipart only when a document is attached (method-spoofed PUT). */
  saveContract: (id: number | null, body: Record<string, unknown>, file: File | null) => {
    if (!file) {
      return (id ? api.put<{ data: Contract }>(`/employment-contracts/${id}`, body) : api.post<{ data: Contract }>('/employment-contracts', body)).then((r) => r.data.data);
    }
    const fd = toFormData({ ...body, document: file, ...(id ? { _method: 'PUT' } : {}) });
    return api.post<{ data: Contract }>(id ? `/employment-contracts/${id}` : '/employment-contracts', fd).then((r) => r.data.data);
  },

  shiftAssignments: (employeeId: number | string) => get<{ data: ShiftAssignment[] }>(`/employees/${employeeId}/shifts`).then((r) => r.data),
  assignShift: (employeeId: number, body: { shift_id: number; effective_from: string; effective_to?: string | null }) =>
    api.post<{ data: ShiftAssignment }>(`/employees/${employeeId}/shifts`, body).then((r) => r.data.data),

  checkIn: () => api.post<{ data: AttendanceRecord }>('/attendance/check-in', {}).then((r) => r.data.data),
  checkOut: () => api.post<{ data: AttendanceRecord }>('/attendance/check-out', {}).then((r) => r.data.data),
  bulkAttendance: (body: { date: string; records: { employee_id: number; status: AttendanceStatus; check_in?: string | null; check_out?: string | null; notes?: string | null }[] }) =>
    api.post<{ message: string; data: { written: number } }>('/attendance/bulk', body).then((r) => r.data),
  attendanceSummary: (p: { from: string; to: string; department_id?: number }) =>
    get<{ data: AttendanceSummaryRow[]; meta: { from: string; to: string } }>('/attendance/summary', p),

  myBalances: (year?: number) => get<{ data: LeaveBalance[] }>('/leave-balances/mine', year ? { year } : {}).then((r) => r.data),
  generateBalances: (year: number) => api.post<{ message: string; data: { year: number; created: number } }>('/leave-balances/generate', { year }).then((r) => r.data),
  adjustBalance: (id: number, body: { adjusted: string; entitled?: string; carried_forward?: string; note?: string | null }) =>
    api.put<{ data: LeaveBalance }>(`/leave-balances/${id}`, body).then((r) => r.data.data),
  requestLeave: (body: { leave_type_id: number; start_date: string; end_date: string; reason?: string | null; attachment?: File | null }) =>
    api.post<{ data: LeaveRequest }>('/leave-requests', toFormData(body)).then((r) => r.data.data),
};

export function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// ---- Reports ---------------------------------------------------------------------

export interface HeadcountReport {
  total: number;
  by_department: { id: number | null; name: string; total: number }[];
  by_branch: { id: number | null; name: string; total: number }[];
  by_employment_type: { key: string; total: number }[];
  by_status: { key: string; total: number }[];
  joiners: number | null;
  leavers: number | null;
  from: string | null;
  to: string | null;
}

export interface AttendanceReport {
  from: string;
  to: string;
  totals: { records: number; present: number; late: number; absent: number; worked_hours: number };
  by_department: { id: number | null; name: string; employees: number; records: number; present: number; late: number; absent: number; half_day: number; on_leave: number; worked_hours: number; late_minutes: number }[];
}

export interface LeaveReport {
  from: string;
  to: string;
  approved_by_type: { id: number; name: string; code: string; color: string | null; requests: number; days: string }[];
  by_status: { key: string; total: number; days: string }[];
}

export const hrReports = {
  headcount: (p: { from?: string; to?: string }) => get<{ data: HeadcountReport }>('/hr/reports/headcount', p).then((r) => r.data),
  attendance: (p: { from: string; to: string }) => get<{ data: AttendanceReport }>('/hr/reports/attendance', p).then((r) => r.data),
  leave: (p: { from: string; to: string }) => get<{ data: LeaveReport }>('/hr/reports/leave', p).then((r) => r.data),
};

// ---- Hooks -----------------------------------------------------------------------

export const useCompanyId = () => useAppSelector((s) => s.session.current_company?.id ?? 0);
export const useCompanyCurrency = () => useAppSelector((s) => s.session.current_company?.currency ?? 'USD');

/** Invalidate every cached query whose key mentions one of the given paths. */
export function useInvalidate() {
  const qc = useQueryClient();
  return (...paths: string[]) => qc.invalidateQueries({ predicate: (q) => paths.some((p) => q.queryKey.includes(p)) });
}

/** Everyone currently on the books (selectors, bulk attendance, manager picker). */
export function useDirectory(enabled = true) {
  const companyId = useCompanyId();
  return useQuery({ queryKey: [companyId, 'employees', 'directory'], queryFn: hrApi.directory, staleTime: 60_000, enabled });
}

export function useEmployeeOptions(enabled = true) {
  const q = useDirectory(enabled);
  return { query: q, options: (q.data ?? []).map((e) => ({ value: String(e.id), label: `${e.name} · ${e.employee_code}` })) };
}

export function useDesignationOptions(enabled = true) {
  const q = designations.useList({ per_page: 100, sort: 'title' }, { enabled });
  return { query: q, rows: q.data?.data ?? [], options: (q.data?.data ?? []).filter((d) => d.is_active).map((d) => ({ value: String(d.id), label: d.title })) };
}

export function useLeaveTypeOptions(activeOnly = true, enabled = true) {
  const q = leaveTypes.useList({ per_page: 100, sort: 'name', ...(activeOnly ? { 'filter[is_active]': 'true' } : {}) }, { enabled });
  return { query: q, rows: q.data?.data ?? [], options: (q.data?.data ?? []).map((t) => ({ value: String(t.id), label: t.name })) };
}

export function useShiftOptions(enabled = true) {
  const q = shifts.useList({ per_page: 100, sort: 'name' }, { enabled });
  return { query: q, rows: q.data?.data ?? [], options: (q.data?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.name} (${s.start_time}–${s.end_time})` })) };
}

/**
 * The signed-in user's own employee record, as far as the API lets us know.
 * There is no `/employees/me`; `/leave-balances/mine` answers 422 when the
 * account is not linked, and its rows carry the employee id. Without balances
 * we fall back to searching the employee list by the user's email (needs
 * employees.view).
 */
export function useMyEmployee() {
  const companyId = useCompanyId();
  const user = useAppSelector(selectUser);
  const can = useCan();
  const canSearch = can('employees.view');
  const mine = useQuery<LeaveBalance[], ApiError>({
    queryKey: [companyId, 'leave-balances', 'mine', 'probe'],
    queryFn: () => hrApi.myBalances(),
    retry: false,
    staleTime: 60_000,
  });
  const notLinked = mine.error?.status === 422;
  const fromBalances = mine.data?.[0]?.employee_id ?? null;
  const search = useQuery({
    queryKey: [companyId, 'employees', 'me', user?.id],
    queryFn: () => get<Paginated<Employee>>('/employees', { search: user?.email, per_page: 10 }).then((r) => r.data.find((e) => e.user_id === user?.id) ?? null),
    enabled: !!user && canSearch && !notLinked && mine.isSuccess && fromBalances === null,
    retry: false,
    staleTime: 5 * 60_000,
  });
  return {
    loading: mine.isLoading,
    /** True when the API said this account has no employee record. */
    notLinked,
    linked: mine.isSuccess,
    linkMessage: notLinked ? (mine.error?.errors?.employee?.[0] ?? mine.error?.message) : null,
    employeeId: fromBalances ?? search.data?.id ?? null,
    balances: mine.data,
    balancesQuery: mine,
  };
}
