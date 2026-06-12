/**
 * vox/core.js — shared config, utilities, fetch wrappers.
 * Must be loaded first. Initialises window.Vox namespace.
 * @version 1.0.0
 */
(function () {
  'use strict';

  // ── Config (injected by PHP via window.VoxConfig) ─────────────────────
  const cfg = window.VoxConfig || {};

  const API        = cfg.apiUrl    || '/vox-api/';
  const CSRF_NAME  = cfg.csrfName  || '';
  const CSRF_VALUE = cfg.csrfValue || '';

  // ── DOM helpers ───────────────────────────────────────────────────────

  function qs(sel, ctx)  { return (ctx || document).querySelector(sel); }
  function qsa(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

  // ── Fetch ─────────────────────────────────────────────────────────────

  function csrfBody(extra) {
    const fd = new FormData();
    if (CSRF_NAME) fd.append(CSRF_NAME, CSRF_VALUE);
    if (extra) Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
    return fd;
  }

  async function post(endpoint, data) {
    const fd  = csrfBody(data);
    const res = await fetch(API + endpoint, { method: 'POST', body: fd });
    return parseResponse(res);
  }

  async function get(endpoint, params) {
    const url = API + endpoint + (params ? '?' + new URLSearchParams(params) : '');
    const res = await fetch(url);
    return parseResponse(res);
  }

  async function parseResponse(res) {
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || ('HTTP ' + res.status));
    }
    return data;
  }

  // ── String / DOM utils ────────────────────────────────────────────────

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function timeAgo(isoDate) {
    const diff = Math.floor((Date.now() - new Date(isoDate)) / 1000);
    if (diff < 60)    return diff + 's ago';
    if (diff < 3600)  return Math.floor(diff / 60)    + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600)  + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
  }

  function renderStars(n, max = 5) {
    let html = '';
    for (let i = 1; i <= max; i++) {
      html += i <= n
        ? '<span class="vox-star vox-star-on" aria-hidden="true">★</span>'
        : '<span class="vox-star" aria-hidden="true">★</span>';
    }
    return html;
  }

  function _normalizePercent(value) {
    const n = parseFloat(value);
    if (Number.isNaN(n)) return null;
    return Math.max(0, Math.min(100, n));
  }

  /** Apply [data-vox-width] and [data-vox-height] values to CSS variables. */
  function initDataBars(ctx = document) {
    qsa('[data-vox-width]', ctx).forEach(el => {
      const width = _normalizePercent(el.dataset.voxWidth);
      if (width === null) return;
      el.style.setProperty('--vox-width', width + '%');
    });

    qsa('[data-vox-height]', ctx).forEach(el => {
      const height = _normalizePercent(el.dataset.voxHeight);
      if (height === null) return;
      el.style.setProperty('--h', height + '%');
    });
  }

  /** Fade-in a newly inserted DOM element. */
  function initFade(el) {
    el.style.opacity   = '0';
    el.style.transform = 'translateY(6px)';
    el.style.transition= 'opacity .25s, transform .25s';
    requestAnimationFrame(() => {
      el.style.opacity   = '1';
      el.style.transform = 'none';
    });
  }

  /** Show inline feedback message, auto-hide after 4 s. */
  function showFeedback(el, msg, type) {
    if (!el) return;
    el.textContent  = msg;
    el.dataset.type = type;
    el.hidden       = false;
    clearTimeout(el._voxTimer);
    el._voxTimer = setTimeout(() => { el.hidden = true; }, 4000);
  }

  // ── Public namespace ──────────────────────────────────────────────────

  window.Vox = window.Vox || {};

  Object.assign(window.Vox, {
    cfg,
    API,
    CSRF_NAME,
    CSRF_VALUE,
    // DOM
    qs,
    qsa,
    // Fetch
    post,
    get,
    // Utils
    escHtml,
    timeAgo,
    renderStars,
    initFade,
    showFeedback,
    initDataBars,
  });

})();
