import type { Metadata, Viewport } from 'next';
import { Inter, JetBrains_Mono } from 'next/font/google';
import { Providers } from './providers';
import '@/styles/globals.scss';

const inter = Inter({ subsets: ['latin'], variable: '--font-sans', display: 'swap' });
const mono = JetBrains_Mono({ subsets: ['latin'], variable: '--font-mono', display: 'swap' });

export const metadata: Metadata = {
  title: { default: 'Enterprise ERP', template: '%s · Enterprise ERP' },
  description: 'Multi-company enterprise resource planning',
  icons: { icon: '/favicon.ico' },
};

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  themeColor: [
    { media: '(prefers-color-scheme: light)', color: '#f8fafc' },
    { media: '(prefers-color-scheme: dark)', color: '#080d1a' },
  ],
};

/**
 * Inline script applies the persisted theme before first paint to avoid a
 * flash of the wrong theme. It only reads a non-sensitive preference key.
 */
const themeInit = `
(function(){try{
  var t=localStorage.getItem('erp.theme');var c=localStorage.getItem('erp.contrast');
  if(t==='light'||t==='dark'){document.documentElement.setAttribute('data-theme',t);}
  if(c==='high'){document.documentElement.setAttribute('data-contrast','high');}
}catch(e){}})();`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" suppressHydrationWarning className={`${inter.variable} ${mono.variable}`}>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeInit }} />
      </head>
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
