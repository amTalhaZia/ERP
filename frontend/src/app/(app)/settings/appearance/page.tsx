'use client';

import { Contrast, Monitor, Moon, Sun } from 'lucide-react';
import { Card, CardBody, CardHeader, PageHeader, SegmentedControl, Switch } from '@/components/ui';
import { authApi } from '@/features/auth/api';
import { useAppDispatch, useAppSelector } from '@/store';
import { setHighContrast, setSidebarCollapsed, setTheme, type ThemeMode } from '@/store/uiSlice';

export default function AppearanceSettingsPage() {
  const dispatch = useAppDispatch();
  const theme = useAppSelector((s) => s.ui.theme);
  const highContrast = useAppSelector((s) => s.ui.highContrast);
  const collapsed = useAppSelector((s) => s.ui.sidebarCollapsed);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 860 }}>
      <PageHeader title="Appearance" description="Saved to your account and applied on every device you sign in from." />
      <Card>
        <CardHeader title="Theme" />
        <CardBody>
          <SegmentedControl<ThemeMode>
            aria-label="Theme"
            value={theme}
            onChange={(t) => {
              dispatch(setTheme(t));
              void authApi.updatePreferences({ theme: t }).catch(() => undefined);
            }}
            options={[
              { value: 'light', label: 'Light', icon: <Sun size={14} /> },
              { value: 'dark', label: 'Dark', icon: <Moon size={14} /> },
              { value: 'system', label: 'System', icon: <Monitor size={14} /> },
            ]}
          />
        </CardBody>
      </Card>
      <Card>
        <CardHeader title="Accessibility" />
        <CardBody>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <Switch
              checked={highContrast}
              onCheckedChange={(v) => {
                dispatch(setHighContrast(v));
                void authApi.updatePreferences({ high_contrast: v }).catch(() => undefined);
              }}
              label={<span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><Contrast size={14} /> High-contrast mode</span>}
              description="Stronger borders, text and focus indicators."
            />
            <Switch checked={collapsed} onCheckedChange={(v) => dispatch(setSidebarCollapsed(v))} label="Compact sidebar" description="Show icons only on large screens (this device)." />
            <p style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
              Animations follow your operating system’s “reduce motion” setting automatically.
            </p>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
