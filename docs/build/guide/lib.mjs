// Small HTML helpers used by the chapter sources. Every helper returns an HTML
// string; figures are numbered per chapter automatically.

const state = { chapter: 0, figure: 0, table: 0, figures: [], missing: [] };
export const figuresUsed = state.figures;
export const missingFigures = state.missing;

/** Image resolver injected by build-guide.mjs: name -> { src, width, height } | null */
let resolveImage = () => null;
export function setImageResolver(fn) { resolveImage = fn; }

export function beginChapter(n) {
  state.chapter = n;
  state.figure = 0;
  state.table = 0;
}

export const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** Inline UI label (button, menu, field) exactly as on screen. */
export const ui = (label) => `<span class="ui">${esc(label)}</span>`;
/** Menu path: nav('HR', 'Employees') -> HR → Employees */
export const nav = (...parts) => `<span class="ui nav">${parts.map(esc).join(' <span class="arrow">→</span> ')}</span>`;
/** Inline code / file name / command. */
export const c = (s) => `<code>${esc(s)}</code>`;

export function fig(name, caption, opts = {}) {
  state.figure += 1;
  const num = `${state.chapter}.${state.figure}`;
  const img = resolveImage(name);
  state.figures.push({ name, num, caption });
  if (!img) {
    state.missing.push(name);
    return `<figure class="figure missing"><div class="ph">Screenshot not available: ${esc(name)}</div><figcaption><b>Figure ${num}</b> — ${caption}</figcaption></figure>`;
  }
  const cls = ['figure', opts.narrow ? 'narrow' : '', opts.half ? 'half' : ''].filter(Boolean).join(' ');
  const ratio = img.height / img.width;
  return `<figure class="${cls}"><div class="frame" style="--r:${ratio.toFixed(4)}"><img src="${img.src}" alt="${esc(caption.replace(/<[^>]+>/g, ''))}"></div><figcaption><b>Figure ${num}</b> — ${caption}</figcaption></figure>`;
}

/** Two figures side by side (used for mobile screenshots). */
export function figPair(a, b) {
  return `<div class="fig-pair">${fig(a[0], a[1], { half: true })}${fig(b[0], b[1], { half: true })}</div>`;
}

export const steps = (items) => `<ol class="steps">${items.map((i) => `<li>${i}</li>`).join('')}</ol>`;
export const bullets = (items) => `<ul class="bullets">${items.map((i) => `<li>${i}</li>`).join('')}</ul>`;
export const checklist = (items) => `<ul class="checklist">${items.map((i) => `<li><span class="box"></span><span>${i}</span></li>`).join('')}</ul>`;

const callout = (kind, title, body) => `<div class="callout ${kind}"><div class="callout-title">${title}</div><div class="callout-body">${body}</div></div>`;
export const tip = (body, title = 'Tip') => callout('tip', title, body);
export const warn = (body, title = 'Dhyan dein') => callout('warn', title, body);
export const security = (body, title = 'Security') => callout('sec', title, body);
export const note = (body, title = 'Note') => callout('note', title, body);

export function table(headers, rows, opts = {}) {
  let caption = '';
  if (opts.caption) {
    state.table += 1;
    caption = `<caption><b>Table ${state.chapter}.${state.table}</b> — ${opts.caption}</caption>`;
  }
  const cls = ['tbl', opts.compact ? 'compact' : '', opts.className ?? ''].filter(Boolean).join(' ');
  const head = `<thead><tr>${headers.map((h) => `<th>${h}</th>`).join('')}</tr></thead>`;
  const body = `<tbody>${rows.map((r) => `<tr>${r.map((cell) => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody>`;
  return `<table class="${cls}">${caption}${head}${body}</table>`;
}

export const pre = (text, label = '') => `<div class="code">${label ? `<div class="code-label">${esc(label)}</div>` : ''}<pre>${esc(text.replace(/^\n/, '').replace(/\s+$/, ''))}</pre></div>`;

/** Section heading (part of the TOC). */
export const h2 = (id, title) => `<h2 id="${id}" data-toc="2">${title}</h2>`;
/** Sub-section heading (not in the TOC). */
export const h3 = (title) => `<h3>${title}</h3>`;

export const qa = (q, a) => `<div class="qa"><div class="q"><span>Q</span>${q}</div><div class="a"><span>A</span><div>${a}</div></div></div>`;
