// Generic HTML → PDF builder shared by the smaller guides (build-access.mjs).
// Same pipeline as build-guide.mjs: JPEG conversion of screenshots, measured
// TOC page numbers (image placeholders of identical size), final PDF with
// header/footer and outline.

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { setImageResolver, beginChapter, missingFigures, figuresUsed } from './lib.mjs';

const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';

/**
 * @param {object} o
 * @param {string} o.shotsDir     folder with *.png screenshots
 * @param {string} o.cacheDir     cache folder (jpg + measure html)
 * @param {Array}  o.chapters     [{ id, title, subtitle?, render(), appendix?, letter? }]
 * @param {string} o.outPdf
 * @param {string} o.docTitle     plain text title (HTML-escaped)
 * @param {string} o.shortTitle   header text (right side)
 * @param {(date:string)=>string} o.cover  cover HTML
 * @param {string} o.css
 */
export async function buildPdf(o) {
  const MAX_W = 2000;
  const QUALITY = 0.84;
  fs.mkdirSync(o.cacheDir, { recursive: true });
  const browser = await chromium.launch({ executablePath: EDGE });
  const page = await (await browser.newContext()).newPage();

  // 1. images
  const images = new Map();
  await page.setContent('<html><body></body></html>');
  for (const f of fs.readdirSync(o.shotsDir).filter((x) => x.endsWith('.png'))) {
    const name = f.replace(/\.png$/, '');
    const src = path.join(o.shotsDir, f);
    const jpg = path.join(o.cacheDir, `${name}.jpg`);
    const meta = path.join(o.cacheDir, `${name}.json`);
    if (!(fs.existsSync(jpg) && fs.existsSync(meta) && fs.statSync(jpg).mtimeMs >= fs.statSync(src).mtimeMs)) {
      const b64 = fs.readFileSync(src).toString('base64');
      const res = await page.evaluate(async ({ b64, MAX_W, QUALITY }) => {
        const img = new Image();
        img.src = `data:image/png;base64,${b64}`;
        await img.decode();
        const s = Math.min(1, MAX_W / img.naturalWidth);
        const w = Math.round(img.naturalWidth * s); const h = Math.round(img.naturalHeight * s);
        const cv = document.createElement('canvas'); cv.width = w; cv.height = h;
        const g = cv.getContext('2d'); g.imageSmoothingQuality = 'high'; g.drawImage(img, 0, 0, w, h);
        return { data: cv.toDataURL('image/jpeg', QUALITY).split(',')[1], w, h };
      }, { b64, MAX_W, QUALITY });
      fs.writeFileSync(jpg, Buffer.from(res.data, 'base64'));
      fs.writeFileSync(meta, JSON.stringify({ width: res.w, height: res.h }));
    }
    const { width, height } = JSON.parse(fs.readFileSync(meta, 'utf8'));
    images.set(name, { width, height, file: jpg });
  }
  let placeholder = true;
  setImageResolver((name) => {
    const im = images.get(name);
    if (!im) return null;
    return {
      src: placeholder ? 'data:image/gif;base64,R0lGODlhAQABAAAAACw=' : `data:image/jpeg;base64,${fs.readFileSync(im.file).toString('base64')}`,
      width: im.width, height: im.height,
    };
  });

  const render = () => o.chapters.map((ch, i) => {
    const n = ch.appendix ? ch.letter : i + 1;
    beginChapter(n);
    const body = ch.render();
    const label = ch.appendix ? `Appendix ${ch.letter}` : `Chapter ${n}`;
    const sections = [...body.matchAll(/<h2 id="([^"]+)" data-toc="2">(.*?)<\/h2>/g)].map((m) => ({ id: m[1], title: m[2] }));
    const html = `<section class="chapter" id="${ch.id}"><header class="chapter-head"><div class="kicker">${label}</div><h1 data-toc="1">${ch.title}</h1>${ch.subtitle ? `<p class="chapter-sub">${ch.subtitle}</p>` : ''}</header>${body}</section>`;
    return { ...ch, n, label, html, sections };
  });

  const toc = (rendered, pages) => {
    const pg = (id) => (pages ? pages[id] ?? '' : '000');
    const rows = rendered.map((ch) => `<li class="toc-ch"><a href="#${ch.id}"><span class="n">${ch.appendix ? ch.letter : ch.n}</span><span class="t">${ch.title}</span><span class="dots"></span><span class="p">${pg(ch.id)}</span></a><ul>${ch.sections.map((s) => `<li class="toc-sec"><a href="#${s.id}"><span class="t">${s.title}</span><span class="dots"></span><span class="p">${pg(s.id)}</span></a></li>`).join('')}</ul></li>`).join('');
    return `<section class="toc"><h1 class="toc-title">Table of contents</h1><p class="toc-hint">Kisi bhi heading par click karein — seedha us page par pahunch jayenge.</p><ol>${rows}</ol></section>`;
  };
  const DATE = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });
  const doc = (body) => `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${o.docTitle}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>${o.css}</style></head><body>${body}</body></html>`;
  const tpl = (left, right) => `<div style="width:100%;font-family:'Segoe UI',Arial,sans-serif;font-size:7.5px;color:#64748b;padding:0 17mm;display:flex;justify-content:space-between;align-items:center;">${left}${right}</div>`;
  const pdfOptions = {
    format: 'A4', printBackground: true,
    margin: { top: '20mm', bottom: '18mm', left: '17mm', right: '17mm' },
    displayHeaderFooter: true,
    headerTemplate: tpl('<span style="color:#4f46e5;font-weight:600;letter-spacing:.04em;">ENTERPRISE ERP</span>', `<span>${o.shortTitle}</span>`),
    footerTemplate: tpl(`<span>${o.docTitle}</span>`, '<span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>'),
  };
  const tmp = path.join(o.cacheDir, 'measure.html');
  const count = (buf) => (buf.toString('latin1').match(/\/Type\s*\/Page(?!s)/g) || []).length;
  const pdfPages = async (html) => {
    fs.writeFileSync(tmp, html);
    await page.goto(pathToFileURL(tmp).href, { waitUntil: 'load', timeout: 60000 });
    await page.evaluate(() => document.fonts.ready);
    return count(await page.pdf(pdfOptions));
  };

  // 2. measure
  let rendered = render();
  const pages = {};
  let start = (await pdfPages(doc(o.cover(DATE) + toc(rendered, null)))) + 1;
  for (const ch of rendered) {
    pages[ch.id] = start;
    for (const s of ch.sections) {
      const cut = ch.html.indexOf(`<h2 id="${s.id}"`);
      const end = ch.html.indexOf('</h2>', cut) + 5;
      pages[s.id] = start + (await pdfPages(doc(ch.html.slice(0, end) + '<p style="margin:0">&nbsp;</p></section>'))) - 1;
    }
    start += await pdfPages(doc(ch.html));
    console.log(`measured ${ch.label}`);
  }

  // 3. final
  placeholder = false;
  figuresUsed.length = 0; missingFigures.length = 0;
  rendered = render();
  const file = path.join(o.cacheDir, 'final.html');
  fs.writeFileSync(file, doc(o.cover(DATE) + toc(rendered, pages) + rendered.map((c) => c.html).join('\n')));
  await page.goto(pathToFileURL(file).href, { waitUntil: 'load', timeout: 120000 });
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(async () => { await Promise.all([...document.images].map((i) => i.decode().catch(() => {}))); });
  const pdf = await page.pdf({ ...pdfOptions, path: o.outPdf, outline: true, tagged: true });
  await browser.close();
  const used = new Set(figuresUsed.map((f) => f.name));
  const result = { pages: count(pdf), bytes: pdf.length, figures: figuresUsed.length, missing: [...missingFigures], unused: [...images.keys()].filter((n) => !used.has(n)) };
  console.log(`\nPDF: ${o.outPdf}\npages: ${result.pages}, size: ${(result.bytes / 1048576).toFixed(1)} MB, figures: ${result.figures}`);
  if (result.missing.length) console.log('MISSING screenshots:', result.missing.join(', '));
  if (result.unused.length) console.log('Unused screenshots:', result.unused.join(', '));
  return result;
}
