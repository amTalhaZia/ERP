// Builds docs/Enterprise-ERP-User-Guide.pdf from the chapter sources in ./guide
// and the screenshots in ./screenshots (run capture.mjs first).
//
//   node build-guide.mjs
//
// Steps
//   1. Screenshots are down-scaled (max 2000 px wide) and re-encoded as JPEG
//      through a headless browser canvas, cached in ./.cache.
//   2. Page numbers for the table of contents are measured: the front matter,
//      every chapter and every section prefix are printed separately (with
//      image placeholders of identical size) and their pages counted.
//   3. The full HTML (images embedded as data URIs) is printed to PDF with a
//      header/footer and a PDF outline (bookmarks).

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { setImageResolver, beginChapter, missingFigures, figuresUsed } from './guide/lib.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const SHOTS = path.join(HERE, 'screenshots');
const CACHE = path.join(HERE, '.cache');
const OUT_PDF = path.resolve(HERE, '..', 'Enterprise-ERP-User-Guide.pdf');
const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const MAX_W = 2000;
const QUALITY = 0.84;

const TITLE = 'Enterprise ERP — User &amp; Admin Guide';
const VERSION = '1.0';
const DATE = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });

fs.mkdirSync(CACHE, { recursive: true });

const browser = await chromium.launch({ executablePath: EDGE });
const ctx = await browser.newContext();
const page = await ctx.newPage();

// ---------------------------------------------------------------- 1. images
const images = new Map(); // name -> { src, width, height, placeholder }
{
  const files = fs.existsSync(SHOTS) ? fs.readdirSync(SHOTS).filter((f) => f.endsWith('.png')) : [];
  await page.setContent('<html><body></body></html>');
  let converted = 0;
  for (const f of files) {
    const name = f.replace(/\.png$/, '');
    const src = path.join(SHOTS, f);
    const jpg = path.join(CACHE, `${name}.jpg`);
    const meta = path.join(CACHE, `${name}.json`);
    const fresh = fs.existsSync(jpg) && fs.existsSync(meta) && fs.statSync(jpg).mtimeMs >= fs.statSync(src).mtimeMs;
    if (!fresh) {
      const b64 = fs.readFileSync(src).toString('base64');
      const res = await page.evaluate(async ({ b64, MAX_W, QUALITY }) => {
        const img = new Image();
        img.src = `data:image/png;base64,${b64}`;
        await img.decode();
        const scale = Math.min(1, MAX_W / img.naturalWidth);
        const w = Math.round(img.naturalWidth * scale);
        const h = Math.round(img.naturalHeight * scale);
        const cv = document.createElement('canvas');
        cv.width = w; cv.height = h;
        const g = cv.getContext('2d');
        g.imageSmoothingQuality = 'high';
        g.drawImage(img, 0, 0, w, h);
        return { data: cv.toDataURL('image/jpeg', QUALITY).split(',')[1], w, h };
      }, { b64, MAX_W, QUALITY });
      fs.writeFileSync(jpg, Buffer.from(res.data, 'base64'));
      fs.writeFileSync(meta, JSON.stringify({ width: res.w, height: res.h }));
      converted++;
    }
    const { width, height } = JSON.parse(fs.readFileSync(meta, 'utf8'));
    images.set(name, { width, height, file: jpg });
  }
  console.log(`images: ${images.size} (${converted} converted)`);
}

let placeholderMode = true;
setImageResolver((name) => {
  const im = images.get(name);
  if (!im) return null;
  const src = placeholderMode
    ? 'data:image/gif;base64,R0lGODlhAQABAAAAACw='
    : `data:image/jpeg;base64,${fs.readFileSync(im.file).toString('base64')}`;
  return { src, width: im.width, height: im.height };
});

// ---------------------------------------------------------------- chapters
const chapterFiles = fs.readdirSync(path.join(HERE, 'guide')).filter((f) => /^ch\d+.*\.mjs$|^appendix.*\.mjs$/.test(f)).sort();
const chapters = [];
for (const f of chapterFiles) {
  const mod = await import(pathToFileURL(path.join(HERE, 'guide', f)).href);
  chapters.push(mod.default);
}

function renderChapters() {
  return chapters.map((ch, i) => {
    const n = ch.appendix ? ch.letter : i + 1;
    beginChapter(n);
    const body = ch.render();
    const label = ch.appendix ? `Appendix ${ch.letter}` : `Chapter ${n}`;
    const sections = [...body.matchAll(/<h2 id="([^"]+)" data-toc="2">(.*?)<\/h2>/g)].map((m) => ({ id: m[1], title: m[2] }));
    const html = `<section class="chapter" id="${ch.id}">
      <header class="chapter-head"><div class="kicker">${label}</div><h1 data-toc="1">${ch.title}</h1>${ch.subtitle ? `<p class="chapter-sub">${ch.subtitle}</p>` : ''}</header>
      ${body}
    </section>`;
    return { ...ch, n, label, html, sections };
  });
}

// ---------------------------------------------------------------- document
const css = fs.readFileSync(path.join(HERE, 'guide', 'style.css'), 'utf8');

function cover() {
  return `<section class="cover">
    <div class="cover-band"></div>
    <div class="cover-logo"><svg viewBox="0 0 24 24" width="54" height="54" fill="none" stroke="#a5b4fc" stroke-width="1.6"><path d="M12 2 21 7v10l-9 5-9-5V7z"/></svg><span>Enterprise ERP</span></div>
    <div class="cover-main">
      <div class="cover-kicker">Official documentation · Version ${VERSION}</div>
      <h1 class="cover-title">Enterprise ERP —<br>User &amp; Admin Guide</h1>
      <p class="cover-sub">Setup, security, roles aur roz-marrah ka kaam — sab kuch step-by-step, aasaan Hinglish mein.</p>
      <ul class="cover-points">
        <li>Windows par installation &amp; database setup</li>
        <li>Pehla admin, companies, users, roles &amp; permissions</li>
        <li>HR, Payroll, Finance, Sales, Procurement, Inventory, Projects</li>
        <li>Employee self-service, mobile use, troubleshooting &amp; FAQ</li>
      </ul>
    </div>
    <div class="cover-foot">
      <div><b>Version</b> ${VERSION}</div>
      <div><b>Date</b> ${DATE}</div>
      <div><b>Audience</b> Business owners, administrators &amp; staff</div>
    </div>
  </section>`;
}

function toc(rendered, pages) {
  const pg = (id) => (pages ? pages[id] ?? '' : '000');
  const rows = rendered.map((ch) => {
    const secs = ch.sections.map((s) => `<li class="toc-sec"><a href="#${s.id}"><span class="t">${s.title}</span><span class="dots"></span><span class="p">${pg(s.id)}</span></a></li>`).join('');
    return `<li class="toc-ch"><a href="#${ch.id}"><span class="n">${ch.appendix ? ch.letter : ch.n}</span><span class="t">${ch.title}</span><span class="dots"></span><span class="p">${pg(ch.id)}</span></a><ul>${secs}</ul></li>`;
  }).join('');
  return `<section class="toc"><h1 class="toc-title">Table of contents</h1><p class="toc-hint">Kisi bhi heading par click karein — seedha us page par pahunch jayenge.</p><ol>${rows}</ol></section>`;
}

const doc = (body) => `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Enterprise ERP — User &amp; Admin Guide</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>${css}</style></head><body>${body}</body></html>`;

const pdfOptions = {
  format: 'A4',
  printBackground: true,
  margin: { top: '20mm', bottom: '18mm', left: '17mm', right: '17mm' },
  displayHeaderFooter: true,
  headerTemplate: `<div style="width:100%;font-family:'Segoe UI',Arial,sans-serif;font-size:7.5px;color:#64748b;padding:0 17mm;display:flex;justify-content:space-between;align-items:center;">
      <span style="color:#4f46e5;font-weight:600;letter-spacing:.04em;">ENTERPRISE ERP</span><span>User &amp; Admin Guide · v${VERSION}</span></div>`,
  footerTemplate: `<div style="width:100%;font-family:'Segoe UI',Arial,sans-serif;font-size:7.5px;color:#64748b;padding:0 17mm;display:flex;justify-content:space-between;align-items:center;">
      <span>${TITLE}</span><span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span></div>`,
};

const tmpHtml = path.join(CACHE, 'measure.html');
async function pdfPages(html) {
  fs.writeFileSync(tmpHtml, html);
  await page.goto(pathToFileURL(tmpHtml).href, { waitUntil: 'load', timeout: 60000 });
  await page.evaluate(() => document.fonts.ready);
  const buf = await page.pdf(pdfOptions);
  return (buf.toString('latin1').match(/\/Type\s*\/Page(?!s)/g) || []).length;
}

// ---------------------------------------------------------------- 2. measure
placeholderMode = true;
let rendered = renderChapters();
const pages = {};
{
  const front = await pdfPages(doc(cover() + toc(rendered, null)));
  let start = front + 1;
  for (const ch of rendered) {
    pages[ch.id] = start;
    // Section starts: print the chapter up to each <h2> plus the heading itself.
    for (const s of ch.sections) {
      const cut = ch.html.indexOf(`<h2 id="${s.id}"`);
      const end = ch.html.indexOf('</h2>', cut) + 5;
      const partial = ch.html.slice(0, end) + '<p style="margin:0">&nbsp;</p></section>';
      const n = await pdfPages(doc(partial));
      pages[s.id] = start + n - 1;
    }
    const total = await pdfPages(doc(ch.html));
    start += total;
    process.stdout.write(`measured ${ch.label} (${total} pages)\n`);
  }
  console.log(`front matter ${front} pages; estimated total ${start - 1}`);
}

// ---------------------------------------------------------------- 3. final
placeholderMode = false;
figuresUsed.length = 0;
missingFigures.length = 0;
rendered = renderChapters();
const finalHtml = doc(cover() + toc(rendered, pages) + rendered.map((c) => c.html).join('\n'));
const finalFile = path.join(CACHE, 'guide.html');
fs.writeFileSync(finalFile, finalHtml);
await page.goto(pathToFileURL(finalFile).href, { waitUntil: 'load', timeout: 120000 });
await page.evaluate(() => document.fonts.ready);
await page.evaluate(async () => { await Promise.all([...document.images].map((i) => i.decode().catch(() => {}))); });
const pdf = await page.pdf({ ...pdfOptions, path: OUT_PDF, outline: true, tagged: true });
const count = (pdf.toString('latin1').match(/\/Type\s*\/Page(?!s)/g) || []).length;
await browser.close();

const used = new Set(figuresUsed.map((f) => f.name));
const unused = [...images.keys()].filter((n) => !used.has(n));
console.log(`\nPDF: ${OUT_PDF}`);
console.log(`pages: ${count}, size: ${(pdf.length / 1024 / 1024).toFixed(1)} MB, figures: ${figuresUsed.length}`);
if (missingFigures.length) console.log('MISSING screenshots:', missingFigures.join(', '));
if (unused.length) console.log('Unused screenshots:', unused.join(', '));
