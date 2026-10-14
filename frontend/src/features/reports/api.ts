import { api, get } from '@/lib/api/client';

export type ReportFormat = 'csv' | 'xlsx' | 'pdf';

export interface ReportParam {
  name: string;
  label: string;
  type: 'date' | 'select' | 'branch';
  required: boolean;
  options?: { value: string; label: string }[];
}

export interface ReportDefinition {
  key: string;
  module: string;
  name: string;
  description: string;
  params: ReportParam[];
  formats: ReportFormat[];
}

export interface ReportColumn {
  key: string;
  label: string;
  type: 'money' | 'number' | 'date' | 'text';
}

export interface ReportPreview {
  report: string;
  params: Record<string, unknown>;
  currency: string;
  columns: ReportColumn[];
  rows: Record<string, unknown>[];
  totals: Record<string, unknown> | null;
}

export interface ExportJob {
  id: string;
  report_key: string;
  report_name: string | null;
  format: ReportFormat;
  params: Record<string, unknown>;
  status: 'queued' | 'running' | 'completed' | 'failed';
  row_count: number | null;
  error: string | null;
  is_expired: boolean;
  download_url: string | null;
  created_at: string;
  finished_at: string | null;
  expires_at: string | null;
}

type Pagination = { page: number; per_page: number; total: number; last_page: number };

export const reportsApi = {
  catalogue: () => get<{ data: ReportDefinition[] }>('/reports').then((r) => r.data),
  preview: (key: string, params: Record<string, unknown>) =>
    get<{ data: ReportPreview; meta: { pagination: Pagination; total_rows: number; truncated: boolean } }>(`/reports/${key}`, params),

  /**
   * Small exports stream back as a file (200); large ones are queued (202 + job).
   * Returns either a downloaded file or the queued job.
   */
  async export(key: string, format: ReportFormat, params: Record<string, unknown>): Promise<{ kind: 'file' } | { kind: 'queued'; job: ExportJob }> {
    const res = await api.post(`/reports/${key}/export`, { format, params }, { responseType: 'blob' });
    if (res.status === 202) {
      const text = await (res.data as Blob).text();
      return { kind: 'queued', job: (JSON.parse(text) as { data: ExportJob }).data };
    }
    saveBlob(res.data as Blob, filenameFrom(res.headers['content-disposition'] as string | undefined) ?? `${key}.${format}`);
    return { kind: 'file' };
  },

  jobs: () => get<{ data: ExportJob[] }>('/report-exports').then((r) => r.data),
  async downloadJob(job: ExportJob) {
    const res = await api.get(`/report-exports/${job.id}/download`, { responseType: 'blob' });
    saveBlob(res.data as Blob, filenameFrom(res.headers['content-disposition'] as string | undefined) ?? `${job.report_key}.${job.format}`);
  },
};

function filenameFrom(disposition?: string) {
  if (!disposition) return null;
  const star = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (star?.[1]) return decodeURIComponent(star[1]);
  const plain = /filename="?([^";]+)"?/i.exec(disposition);
  return plain?.[1] ?? null;
}

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
