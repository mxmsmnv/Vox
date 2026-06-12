/**
 * Vox — vox.bundle.js (generated 2026-06-11)
 * Source files: vox.core.js, vox.stars.js, vox.vote.js, vox.reply.js, vox.entry.js, vox.blocks.js, vox.filters.js, vox.photos.js, vox.profile.js, vox.init.js
 * Edit the source files, not this bundle.
 */

/* ── vox.core.js ─────────────────────────────────────── */
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


/* ── vox.stars.js ────────────────────────────────────── */
(function () {
  'use strict';

  const { qs, qsa } = window.Vox;

  // ── Rating picker ─────────────────────────────────────────────────────
  //
  // Expected HTML:
  //   <div data-vox-stars-wrap data-val="3">
  //     <span class="vox-star-pick">★</span> or <span class="vox-dot-pick">●</span> × 5
  //     <input type="hidden" name="rating" data-vox-rating value="3">
  //   </div>

  function initStarPicker(wrap) {
    if (wrap.dataset.voxStarsInit) return;
    wrap.dataset.voxStarsInit = '1';

    const stars   = qsa('.vox-star-pick, .vox-dot-pick', wrap);
    const input   = qs('[data-vox-rating]', wrap);
    let   current = parseInt(wrap.dataset.val || input?.value || '0', 10);

    function paint(n) {
      stars.forEach((s, i) => {
        const on = i < n;
        s.classList.toggle('vox-star-on', on);
        s.classList.toggle('vox-dot-on', on);
        if (s.getAttribute('role') === 'radio') {
          s.setAttribute('aria-checked', String(i === n - 1));
          s.tabIndex = i === Math.max(n - 1, 0) ? 0 : -1;
        }
      });
    }

    function setRating(n) {
      current = Math.max(1, Math.min(stars.length, n));
      wrap.dataset.val = String(current);
      if (input) input.value = String(current);
      paint(current);
    }

    stars.forEach((star, idx) => {
      star.addEventListener('mouseenter', () => paint(idx + 1));
      star.addEventListener('mouseleave', () => paint(current));
      star.addEventListener('click', e => {
        e.preventDefault();
        setRating(idx + 1);
      });
      star.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          setRating(idx + 1);
        } else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
          e.preventDefault();
          const next = Math.min(stars.length, current + 1 || 1);
          setRating(next);
          stars[next - 1].focus();
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
          e.preventDefault();
          const prev = Math.max(1, current - 1 || 1);
          setRating(prev);
          stars[prev - 1].focus();
        }
      });
    });

    paint(current);
  }

  // ── Recommendation buttons ─────────────────────────────────────────────
  //
  // Expected HTML:
  //   <div data-vox-rec>
  //     <button type="button" data-rec-value="1">Recommends</button>
  //     <button type="button" data-rec-value="0">Does not</button>
  //     <input type="hidden" name="recommend" data-vox-rec-input value="">
  //   </div>

  function initRecButtons(wrap) {
    if (wrap.dataset.voxRecInit) return;
    wrap.dataset.voxRecInit = '1';

    const btns  = qsa('[data-rec-value]', wrap);
    const input = qs('[data-vox-rec-input]', wrap);

    btns.forEach(btn => {
      btn.addEventListener('click', () => {
        btns.forEach(b => {
          b.classList.remove('vox-rec-btn--active', 'vox-rec-btn--yes', 'vox-rec-btn--no');
        });
        btn.classList.add('vox-rec-btn--active');
        btn.classList.add(btn.dataset.recValue === '1' ? 'vox-rec-btn--yes' : 'vox-rec-btn--no');
        if (input) input.value = btn.dataset.recValue;
      });
    });
  }

  // ── Init all pickers inside a root element ────────────────────────────

  function initStarsIn(root) {
    qsa('[data-vox-stars-wrap]', root).forEach(initStarPicker);
    qsa('[data-vox-rec]', root).forEach(initRecButtons);
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initStarPicker,
    initRecButtons,
    initStarsIn,
  });

})();


/* ── vox.vote.js ─────────────────────────────────────── */
(function () {
  'use strict';

  const { post, qs, qsa, showFeedback } = window.Vox;

  // Surface an action error in the entry's inline feedback slot (not just console).
  function actionError(btn, e, fallback) {
    const wrap = btn.closest('.vox-entry__actions');
    showFeedback(wrap && qs('[data-vox-action-feedback]', wrap), (e && e.message) || fallback, 'error');
  }

  // ── Like / vote ────────────────────────────────────────────────────────
  //
  // Expected HTML:
  //   <button class="vox-vote-btn" data-entry-key="vox_…" data-value="1">
  //     <i class="fa-regular fa-heart" data-vox-heart></i>
  //     <span data-vox-likes>7</span>
  //   </button>

  function initVote(btn) {
    if (btn.dataset.voxVoteInit) return;
    btn.dataset.voxVoteInit = '1';

    btn.addEventListener('click', async () => {
      const entryKey = btn.dataset.entryKey || btn.dataset.entryId;
      const value   = btn.dataset.value || '1';
      if (!entryKey) return;

      btn.disabled = true;
      try {
        const data    = await post('entries/vote', { entry_key: entryKey, value });
        const counter = qs('[data-vox-likes]', btn) || btn.nextElementSibling;
        if (counter) counter.textContent = data.total;

        const icon = qs('[data-vox-heart]', btn);
        if (icon) {
          const liked = data.user_vote !== 0;
          icon.classList.toggle('fa-regular', !liked);
          icon.classList.toggle('fa-solid',    liked);
          btn.classList.toggle('vox-vote-btn--liked', liked);
        }
      } catch (e) {
        console.error('[Vox] vote error', e);
        actionError(btn, e, 'Could not register your vote.');
      } finally {
        btn.disabled = false;
      }
    });
  }

  // ── Report ────────────────────────────────────────────────────────────
  //
  // Expected HTML:
  //   <button class="vox-report-btn" data-entry-key="vox_…" data-reason="inappropriate">
  //     <i class="fa-regular fa-flag" data-vox-flag></i>
  //     <span data-vox-report-label>Report</span>
  //   </button>

  function initReport(btn) {
    if (btn.dataset.voxReportInit) return;
    btn.dataset.voxReportInit = '1';

    btn.addEventListener('click', async () => {
      if (btn.dataset.r) return; // already reported

      const entryKey = btn.dataset.entryKey || btn.dataset.entryId;
      const reason  = btn.dataset.reason || 'inappropriate';
      if (!entryKey) return;

      try {
        await post('entries/report', { entry_key: entryKey, reason });
        btn.dataset.r = '1';
        btn.title     = 'Reported';
        btn.classList.add('vox-report-btn--reported');

        const icon  = qs('[data-vox-flag]', btn);
        const label = qs('[data-vox-report-label]', btn);
        if (icon)  { icon.classList.replace('fa-regular', 'fa-solid'); }
        if (label) label.textContent = 'Reported';
      } catch (e) {
        console.error('[Vox] report error', e);
        actionError(btn, e, 'Could not submit your report.');
      }
    });
  }

  // ── Best answer ───────────────────────────────────────────────────────
  //
  // Expected HTML:
  //   <button data-vox-best-btn data-entry-key="vox_…">Mark as best</button>

  function initBestAnswer(btn) {
    if (btn.dataset.voxBestInit) return;
    btn.dataset.voxBestInit = '1';

    btn.addEventListener('click', async () => {
      const entryKey = btn.dataset.entryKey || btn.dataset.entryId;
      if (!entryKey) return;

      try {
        const data = await post('entries/best', { entry_key: entryKey });
        if (!data.success) return;

        // Hide all existing best-answer badges in the thread
        qsa('[data-vox-best-badge]').forEach(b => {
          b.hidden = true;
          const card = b.closest('[data-vox-entry]');
          if (card) card.classList.remove('vox-entry--best');
        });

        // Show badge on this entry
        const card  = btn.closest('[data-vox-entry]');
        const badge = card && qs('[data-vox-best-badge]', card);
        if (badge) {
          badge.hidden = false;
          card.classList.add('vox-entry--best');
        }

        btn.hidden = true;
      } catch (e) {
        console.error('[Vox] best-answer error', e);
      }
    });
  }

  // ── Init all vote/report/best elements inside a root ─────────────────

  function initVotesIn(root) {
    qsa('.vox-vote-btn',   root).forEach(initVote);
    qsa('.vox-report-btn', root).forEach(initReport);
    qsa('[data-vox-best-btn]', root).forEach(initBestAnswer);
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initVote,
    initReport,
    initBestAnswer,
    initVotesIn,
  });

})();


/* ── vox.reply.js ────────────────────────────────────── */
(function () {
  'use strict';

  const { cfg, qs, qsa } = window.Vox;

  // ── Reply toggle ──────────────────────────────────────────────────────
  //
  // Expected HTML:
  //   <button class="vox-reply-btn" data-reply-target="reply-42">Reply</button>
  //   <div id="reply-42" data-vox-reply-form="reply-42" hidden>…</div>

  function initReplyToggle(btn) {
    if (btn.dataset.voxReplyInit) return;
    btn.dataset.voxReplyInit = '1';

    btn.addEventListener('click', () => {
      const targetId = btn.dataset.replyTarget;
      const form     = qs('[data-vox-reply-form="' + targetId + '"]') ||
                       document.getElementById(targetId);
      if (!form) return;

      const isHidden = form.hidden;
      form.hidden = !isHidden;

      if (isHidden) {
        const ta = qs('textarea', form);
        if (ta) ta.focus();
      }
    });
  }

  // ── Stop word checker ─────────────────────────────────────────────────
  //
  // Expected HTML (inside [data-vox-form]):
  //   <textarea name="body"></textarea>
  //   <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>

  function initStopWordChecker(textarea) {
    if (textarea.dataset.voxSwInit) return;
    textarea.dataset.voxSwInit = '1';

    const stopWords = (cfg.stopWords || []).map(w => w.toLowerCase());
    if (!stopWords.length) return;

    const form    = textarea.closest('[data-vox-form]');
    const warning = form && qs('[data-vox-stopword-warning]', form);
    if (!warning) return;

    function check() {
      const body = textarea.value.toLowerCase();
      const hit  = stopWords.find(w => body.includes(w));
      warning.hidden = !hit;
      if (hit) warning.textContent = '⚠ Contains prohibited word: "' + hit + '"';
    }

    textarea.addEventListener('input', check);
  }

  // ── Init all reply/stopword elements inside a root ────────────────────

  function initRepliesIn(root) {
    qsa('.vox-reply-btn',   root).forEach(initReplyToggle);
    qsa('textarea[name="body"]', root).forEach(initStopWordChecker);
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initReplyToggle,
    initStopWordChecker,
    initRepliesIn,
  });

})();


/* ── vox.entry.js ────────────────────────────────────── */
(function () {
  'use strict';

  const {
    cfg, API, CSRF_NAME, CSRF_VALUE,
    qs, qsa,
    get, escHtml,
    renderStars, timeAgo,
    initFade, showFeedback,
  } = window.Vox;

  // ── Entry form submission ─────────────────────────────────────────────
  //
  // Expected HTML:
  //   <form data-vox-form data-entry-list="my-list-id">
  //     <input type="hidden" name="page_key" value="vox_…">
  //     <input type="hidden" name="type"    value="review">
  //     <textarea name="body"></textarea>
  //     <span data-vox-stopword-warning hidden></span>
  //     <span data-vox-feedback hidden></span>
  //     <button type="submit">…</button>
  //   </form>

  function initEntryForm(form) {
    if (form.dataset.voxFormInit) return;
    form.dataset.voxFormInit = '1';

    // Init sub-components inside this form
    Vox.initStarsIn(form);
    Vox.initRepliesIn(form);

    const warning   = qs('[data-vox-stopword-warning]', form);
    const feedback  = qs('[data-vox-feedback]', form);
    const submitBtn = qs('[type="submit"]', form);

    form.addEventListener('submit', async e => {
      e.preventDefault();

      // Block if stop word warning is visible
      if (warning && !warning.hidden) return;

      const fd = new FormData(form);
      if (CSRF_NAME) fd.set(CSRF_NAME, CSRF_VALUE);

      if (submitBtn) submitBtn.disabled = true;

      try {
        const res  = await fetch(API + 'entries/add', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.error) {
          showFeedback(feedback, data.error, 'error');
          return;
        }

        showFeedback(feedback, 'Your entry has been submitted.', 'success');
        form.reset();
        qsa('[data-vox-photo-preview]', form).forEach(preview => { preview.innerHTML = ''; });

        // Reset rating pickers
        qsa('[data-vox-stars-wrap]', form).forEach(wrap => {
          wrap.dataset.val = '0';
          const inp = qs('[data-vox-rating]', wrap);
          if (inp) inp.value = '0';
          qsa('.vox-star-pick, .vox-dot-pick', wrap).forEach((s, i) => {
            s.classList.remove('vox-star-on');
            s.classList.remove('vox-dot-on');
            if (s.getAttribute('role') === 'radio') {
              s.setAttribute('aria-checked', 'false');
              s.tabIndex = i === 0 ? 0 : -1;
            }
          });
        });

        // Prepend new card to list
        const listId = form.dataset.entryList;
        const list   = listId ? document.getElementById(listId) : null;
        if (list && data.entry) {
          const el = buildEntryCard(data.entry);
          list.prepend(el);
          initFade(el);
          initEntryInteractions(el);
        }

        // Close reply form if this is a nested reply
        const replyWrap = form.closest('[data-vox-reply-form]');
        if (replyWrap) replyWrap.hidden = true;

      } catch (err) {
        showFeedback(feedback, 'Something went wrong. Please try again.', 'error');
        console.error('[Vox] entry submit error', err);
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  // ── Build entry card from API response ────────────────────────────────

  function buildEntryCard(entry) {
    const el       = document.createElement('div');
    el.className   = 'vox-entry vox-entry--' + entry.type;
    el.dataset.voxEntry  = entry.id;
    el.dataset.created   = entry.created || '';
    el.dataset.rating    = entry.values?.rating || 0;
    el.dataset.likes     = entry.likes || 0;

    const initials = (entry.author_name || 'An').slice(0, 2).toUpperCase();
    const stars    = entry.type === 'review' ? renderStars(parseInt(entry.values?.rating || 0)) : '';
    const pageKey  = entry.page_key  || '';
    const blockId  = entry.block_id  || '';
    const photos   = renderEntryPhotos(entry.photos || []);

    el.innerHTML = `
      <div class="vox-entry__head">
        <span class="vox-av">${initials}</span>
        <span class="vox-entry__author">${escHtml(entry.author_name || 'Anonymous')}</span>
        ${entry.author_rank ? `<span class="vox-rank-badge">${escHtml(entry.author_rank)}</span>` : ''}
        <span class="vox-entry__time">${timeAgo(entry.created)}</span>
        ${entry.is_best_answer
          ? '<span class="vox-best-badge" data-vox-best-badge><i class="fa-solid fa-star" aria-hidden="true"></i> Best Answer</span>'
          : '<span class="vox-best-badge" data-vox-best-badge hidden></span>'}
      </div>
      ${stars ? `<div class="vox-entry__stars">${stars}</div>` : ''}
      <div class="vox-entry__body">${escHtml(entry.body || '').replace(/\n/g, '<br>')}</div>
      ${photos}
      <div class="vox-entry__actions">
        <button class="vox-vote-btn" data-entry-key="${entry.id}" data-value="1" aria-label="Like">
          <i class="fa-regular fa-heart" data-vox-heart aria-hidden="true"></i>
          <span data-vox-likes>${entry.likes || 0}</span>
        </button>
        <button class="vox-reply-btn" data-reply-target="reply-new-${entry.id}" aria-label="Reply">
          <i class="fa-solid fa-reply" aria-hidden="true"></i> Reply
        </button>
        <button class="vox-report-btn" data-entry-key="${entry.id}" data-reason="inappropriate" aria-label="Report">
          <i class="fa-regular fa-flag" data-vox-flag aria-hidden="true"></i>
          <span data-vox-report-label>Report</span>
        </button>
        <span class="vox-action-feedback" data-vox-action-feedback hidden></span>
      </div>
      <div id="reply-new-${entry.id}"
           data-vox-reply-form="reply-new-${entry.id}" hidden
           class="vox-reply-form">
        <form data-vox-form data-entry-list="replies-${entry.id}">
          <input type="hidden" name="page_key"  value="${escHtml(String(pageKey))}">
          <input type="hidden" name="block_id"  value="${escHtml(blockId)}">
          <input type="hidden" name="type"      value="comment">
          <input type="hidden" name="parent_key" value="${entry.id}">
          <textarea name="body" class="vox-textarea" rows="3"
                    placeholder="Write a reply…"></textarea>
          <span data-vox-stopword-warning hidden class="vox-stopword-warn"></span>
          <div class="vox-form__actions">
            <button type="submit" class="vox-btn vox-btn--primary vox-btn--sm">
              <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Post
            </button>
            <button type="button" class="vox-btn vox-btn--sm"
                    onclick="this.closest('[data-vox-reply-form]').hidden=true">Cancel</button>
          </div>
          <span data-vox-feedback hidden></span>
        </form>
      </div>
      <div id="replies-${entry.id}" data-vox-replies="${entry.id}"></div>
    `;

    return el;
  }

  function renderEntryPhotos(photos) {
    if (!Array.isArray(photos) || !photos.length) return '';
    const items = photos.map(photo => {
      const url = photo && photo.url ? String(photo.url) : '';
      if (!url) return '';
      const alt = photo.original_name || 'Attached photo';
      return `
        <a class="vox-entry__photo" href="${escHtml(url)}" target="_blank" rel="noopener">
          <img src="${escHtml(url)}" alt="${escHtml(alt)}" loading="lazy">
        </a>
      `;
    }).join('');
    return items ? `<div class="vox-entry__photos">${items}</div>` : '';
  }

  // ── Init all interactions inside a root element ───────────────────────

  function initEntryInteractions(root) {
    qsa('[data-vox-form]',     root).forEach(initEntryForm);
    Vox.initVotesIn(root);
    Vox.initRepliesIn(root);
    Vox.initStarsIn(root);
    if (Vox.initPhotosIn) Vox.initPhotosIn(root);
  }

  // ── "Show more" comments ──────────────────────────────────────────────
  //
  // Expected HTML:
  //   <button data-vox-show-more
  //           data-entry-key="vox_…"
  //           data-page-key="vox_…"
  //           data-block-id=""
  //           data-type="comment"
  //           data-loaded="3"
  //           data-per-page="5">
  //     Show more replies
  //   </button>

  function initShowMore() {
    qsa('[data-vox-show-more]').forEach(btn => {
      if (btn.dataset.voxSmInit) return;
      btn.dataset.voxSmInit = '1';

      btn.addEventListener('click', async () => {
        const entryKey = btn.dataset.entryKey || btn.dataset.entryId;
        const pageKey  = btn.dataset.pageKey || btn.dataset.pageId;
        const blockId = btn.dataset.blockId || '';
        const type    = btn.dataset.type    || 'comment';
        const loaded  = parseInt(btn.dataset.loaded  || '0', 10);
        const perPage = parseInt(btn.dataset.perPage || '5', 10);

        btn.disabled    = true;
        btn.textContent = 'Loading…';

        try {
          const data = await get('entries/', {
            page_key:  pageKey,
            block_id:  blockId,
            parent_key: entryKey,
            type,
            page:      Math.floor(loaded / perPage) + 2,
            per_page:  perPage,
          });

          const list = document.getElementById('replies-' + entryKey) ||
                       qs('[data-vox-replies="' + entryKey + '"]');

          if (list && data.entries) {
            data.entries.forEach(entry => {
              const el = buildEntryCard(entry);
              list.appendChild(el);
              initFade(el);
              initEntryInteractions(el);
            });
            btn.dataset.loaded = loaded + data.entries.length;
          }

          const newLoaded = parseInt(btn.dataset.loaded, 10);
          if (newLoaded >= data.total) {
            btn.hidden = true;
          } else {
            btn.disabled    = false;
            btn.textContent = 'Show more (' + (data.total - newLoaded) + ')';
          }
        } catch (err) {
          btn.disabled    = false;
          btn.textContent = 'Failed to load — try again';
          console.error('[Vox] show-more error', err);
        }
      });
    });
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initEntryForm,
    buildEntryCard,
    initEntryInteractions,
    initShowMore,
  });

})();


/* ── vox.blocks.js ───────────────────────────────────── */
(function () {
  'use strict';

  const { cfg, get, qs, qsa } = window.Vox;

  // ── Batch block comment counts ────────────────────────────────────────
  //
  // Finds all [data-discuss-block] on the page and fetches counts in one request.
  // Sets [data-vox-block-count] text inside each block.

  function initBlockCounts() {
    const blocks = qsa('[data-discuss-block]');
    if (!blocks.length) return;

    const pageRoot = qs('[data-discuss-page-key], [data-discuss-page-id]');
    const pageKey = (pageRoot && (pageRoot.dataset.discussPageKey || pageRoot.dataset.discussPageId)) ||
                    document.body.dataset.discussPageKey ||
                    document.body.dataset.discussPageId;
    if (!pageKey) return;

    const ids    = blocks.map(b => b.dataset.discussBlock);
    const params = { page_key: pageKey };
    ids.forEach((id, i) => { params['blocks[' + i + ']'] = id; });

    get('blocks/', params)
      .then(data => {
        blocks.forEach(block => {
          const id      = block.dataset.discussBlock;
          const cnt     = data[id] || 0;
          const counter = qs('[data-vox-block-count]', block);
          if (counter) counter.textContent = cnt + ' comment' + (cnt !== 1 ? 's' : '');
        });
      })
      .catch(err => console.warn('[Vox] block counts error', err));
  }

  // ── Block panel activation ────────────────────────────────────────────
  //
  // [data-vox-block-trigger="tasting-notes"] toggles
  // [data-vox-block-panel="tasting-notes"].
  // In sidebar mode only one panel is open at a time.

  function initBlockPanelActivation() {
    qsa('[data-vox-block-trigger]').forEach(trigger => {
      if (trigger.dataset.voxBpInit) return;
      trigger.dataset.voxBpInit = '1';

      trigger.addEventListener('click', () => {
        const blockId = trigger.dataset.voxBlockTrigger;
        const panel   = qs('[data-vox-block-panel="' + blockId + '"]');
        if (!panel) return;

        const isSidebar = document.body.dataset.voxPanelMode === 'sidebar';

        if (isSidebar) {
          // Close other open panels
          qsa('[data-vox-block-panel]').forEach(p => {
            if (p !== panel) p.classList.remove('vox-panel--active');
          });
        }

        const willOpen = !panel.classList.contains('vox-panel--active');
        panel.classList.toggle('vox-panel--active', willOpen);

        if (willOpen) {
          panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    });
  }

  // ── Inline / Sidebar panel mode toggle ───────────────────────────────
  //
  // Expected HTML:
  //   <div data-vox-panel-toggle>
  //     <button data-panel-mode="inline">Inline</button>
  //     <button data-panel-mode="sidebar">Sidebar</button>
  //   </div>

  function initPanelToggle() {
    const toggle = qs('[data-vox-panel-toggle]');
    if (!toggle) return;

    const saved = localStorage.getItem('vox_panel_mode') || cfg.panelMode || 'inline';
    applyPanelMode(saved, toggle);

    qsa('[data-panel-mode]', toggle).forEach(btn => {
      btn.classList.toggle('vox-panel-btn--active', btn.dataset.panelMode === saved);

      btn.addEventListener('click', () => {
        const mode = btn.dataset.panelMode;
        applyPanelMode(mode, toggle);
        localStorage.setItem('vox_panel_mode', mode);
      });
    });
  }

  function applyPanelMode(mode, toggle) {
    document.body.dataset.voxPanelMode = mode;
    qsa('[data-vox-block-panel]').forEach(p => { p.dataset.mode = mode; });

    if (toggle) {
      qsa('[data-panel-mode]', toggle).forEach(b =>
        b.classList.toggle('vox-panel-btn--active', b.dataset.panelMode === mode)
      );
    }
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initBlockCounts,
    initBlockPanelActivation,
    initPanelToggle,
    applyPanelMode,
  });

})();


/* ── vox.filters.js ──────────────────────────────────── */
(function () {
  'use strict';

  const { qs, qsa } = window.Vox;

  // ── Ratings histogram filter ──────────────────────────────────────────
  //
  // [data-rating-filter="4"] — click to show only 4-star entries.
  // Click again to clear filter.

  function initRatingsFilter() {
    const bars = qsa('[data-rating-filter]');
    if (!bars.length) return;

    bars.forEach(bar => {
      bar.addEventListener('click', () => {
        const rating = parseInt(bar.dataset.ratingFilter, 10);
        const active = bar.classList.contains('vox-bar--active');

        bars.forEach(b => b.classList.remove('vox-bar--active'));

        if (!active) {
          bar.classList.add('vox-bar--active');
          _filterByRating(rating);
        } else {
          _filterByRating(0);
        }
      });
    });
  }

  function _filterByRating(rating) {
    qsa('[data-vox-entries-list]').forEach(list => {
      Array.from(list.children)
        .filter(entry => entry.matches('[data-vox-entry]'))
        .forEach(entry => {
          if (!rating) { entry.hidden = false; return; }
          const stars = qsa('.vox-star-on', entry).length;
          entry.hidden = stars !== rating;
        });
    });

    qsa('[data-vox-replies] [data-vox-entry]').forEach(entry => {
      if (!rating) { entry.hidden = false; return; }
      entry.hidden = false;
    });

    const noResults = qs('[data-vox-no-results]');
    if (noResults) {
      const list = noResults.closest('[data-vox-entries-list]');
      const visible = list
        ? Array.from(list.children).filter(entry => entry.matches('[data-vox-entry]:not([hidden])')).length
        : qsa('[data-vox-entry]:not([hidden])').length;
      noResults.hidden = visible > 0;
    }
  }

  // ── Tag / keyword filter ──────────────────────────────────────────────
  //
  // [data-vox-tag="oak"] — click to filter entries containing that word.

  function initTagFilter() {
    qsa('[data-vox-tag]').forEach(tag => {
      if (tag.dataset.voxTagInit) return;
      tag.dataset.voxTagInit = '1';

      tag.addEventListener('click', () => {
        const active = tag.classList.contains('vox-tag--active');
        qsa('[data-vox-tag]').forEach(t => t.classList.remove('vox-tag--active'));

        if (!active) {
          tag.classList.add('vox-tag--active');
          const word = tag.dataset.voxTag.toLowerCase();
          qsa('[data-vox-entry]').forEach(entry => {
            const body = qs('.vox-entry__body', entry);
            entry.hidden = body ? !body.textContent.toLowerCase().includes(word) : true;
          });
        } else {
          qsa('[data-vox-entry]').forEach(entry => { entry.hidden = false; });
        }
      });
    });
  }

  // ── Sort select ───────────────────────────────────────────────────────
  //
  // <select data-vox-sort> with options: newest, oldest, highest, lowest, helpful

  function initSortSelect() {
    const sel = qs('[data-vox-sort]');
    const btns = qsa('[data-vox-sort-btn]');
    if (!sel && !btns.length) return;
    if (sel && sel.dataset.voxSortInit) return;

    if (sel) {
      sel.dataset.voxSortInit = '1';
      sel.addEventListener('change', () => applySort(sel.value));
    }

    if (btns.length) {
      btns.forEach(btn => {
        if (btn.dataset.voxSortBtnInit) return;
        btn.dataset.voxSortBtnInit = '1';
        btn.addEventListener('click', () => {
          const val = btn.dataset.voxSortBtn;
          if (!val) return;
          btns.forEach(b => b.classList.toggle('vox-sort-btn--active', b === btn));
          if (sel) sel.value = val;
          applySort(val);
        });
      });
    }

    const initialBtn = btns.find(b => b.classList.contains('vox-sort-btn--active'));
    let initialVal = sel ? sel.value : 'newest';

    if (sel && initialBtn) {
      sel.value = initialBtn.dataset.voxSortBtn;
      initialVal = sel.value;
    } else if (btns.length) {
      const firstBtn = btns[0];
      const btnVal = firstBtn.dataset.voxSortBtn || initialVal;
      firstBtn.classList.add('vox-sort-btn--active');
      if (!initialBtn) {
        if (sel) sel.value = btnVal;
        initialVal = btnVal;
      }
    }

    applySort(initialVal);
  }

  function _date(el)         { return new Date(el.dataset.created || 0).getTime(); }
  function _int(el, key)     { return parseInt(el.dataset[key] || '0', 10); }

  function applySort(val) {
    const list = qs('[data-vox-entries-list]');
    if (!list) return;

    const entries = Array.from(list.children)
      .filter(entry => entry.matches('[data-vox-entry]'));

    entries.sort((a, b) => {
      switch (val) {
        case 'newest':  return _date(b) - _date(a);
        case 'oldest':  return _date(a) - _date(b);
        case 'highest': return _int(b, 'rating') - _int(a, 'rating');
        case 'lowest':  return _int(a, 'rating') - _int(b, 'rating');
        case 'helpful': return _int(b, 'likes')  - _int(a, 'likes');
        default:        return 0;
      }
    });

    entries.forEach(e => list.appendChild(e));
  }

  // ── Comment section toggles ───────────────────────────────────────────
  //
  // <button data-vox-comments-toggle="42">
  //   <span data-vox-toggle-label>Comments</span>
  // </button>
  // <div data-vox-comments-section="42" hidden>…</div>

  function initCommentToggles() {
    qsa('[data-vox-comments-toggle]').forEach(btn => {
      if (btn.dataset.voxCtInit) return;
      btn.dataset.voxCtInit = '1';

      btn.addEventListener('click', () => {
        const target  = btn.dataset.voxCommentsToggle;
        const section = qs('[data-vox-comments-section="' + target + '"]');
        if (!section) return;

        const isOpen = !section.hidden;
        section.hidden = isOpen;
        btn.setAttribute('aria-expanded', String(!isOpen));

        const label = qs('[data-vox-toggle-label]', btn);
        if (label) label.textContent = isOpen ? 'Comments' : 'Hide';
      });
    });
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initRatingsFilter,
    initTagFilter,
    initSortSelect,
    initCommentToggles,
  });

})();


/* ── vox.photos.js ───────────────────────────────────── */
(function () {
  'use strict';

  const { cfg, qs, qsa, escHtml } = window.Vox;

  // ── Photo input preview ───────────────────────────────────────────────
  //
  // Expected HTML (inside [data-vox-form]):
  //   <input type="file" name="photos[]" multiple accept="image/*"
  //          data-vox-photo-input data-max="6">
  //   <div data-vox-photo-preview></div>

  function initPhotoInput(input) {
    if (input.dataset.voxPhotoInit) return;
    input.dataset.voxPhotoInit = '1';

    const form    = input.closest('[data-vox-form]');
    const preview = form && qs('[data-vox-photo-preview]', form);
    if (!preview) return;

    input.addEventListener('change', () => _renderPreview(input, preview));
  }

  function _renderPreview(input, preview) {
    preview.innerHTML = '';
    const max = parseInt(input.dataset.max || cfg.photoMax || '6', 10);

    Array.from(input.files).slice(0, max).forEach(file => {
      if (!file.type.startsWith('image/')) return;

      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src       = e.target.result;
        img.className = 'vox-photo-thumb';
        img.alt       = escHtml(file.name);
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  }

  // ── Drag-and-drop zone ────────────────────────────────────────────────
  //
  // Expected HTML:
  //   <div class="vox-dropzone" data-vox-dropzone>
  //     …
  //     <input type="file" name="photos[]" data-vox-photo-input>
  //   </div>

  function initDropzone(zone) {
    if (zone.dataset.voxDropInit) return;
    zone.dataset.voxDropInit = '1';

    zone.addEventListener('dragover', e => {
      e.preventDefault();
      zone.classList.add('vox-dropzone--over');
    });

    zone.addEventListener('dragleave', () => {
      zone.classList.remove('vox-dropzone--over');
    });

    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('vox-dropzone--over');

      const input   = qs('[data-vox-photo-input]', zone.closest('[data-vox-form]') || zone);
      const preview = input && qs('[data-vox-photo-preview]',
                                   input.closest('[data-vox-form]') || zone);

      if (!input || !e.dataTransfer.files.length) return;

      // Assign dropped files and re-render preview
      // (We create a fake DataTransfer to assign to input.files)
      const dt = new DataTransfer();
      const max = parseInt(input.dataset.max || cfg.photoMax || '6', 10);
      Array.from(e.dataTransfer.files).slice(0, max).forEach(f => dt.items.add(f));

      try {
        input.files = dt.files;
      } catch (_) {
        // Some browsers don't allow assigning to input.files directly
      }

      if (preview) _renderPreview(input, preview);
    });
  }

  // ── Init all photo upload elements inside a root ─────────────────────

  function initPhotosIn(root) {
    qsa('[data-vox-photo-input]', root).forEach(initPhotoInput);
    qsa('[data-vox-dropzone]',    root).forEach(initDropzone);
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initPhotoInput,
    initDropzone,
    initPhotosIn,
  });

})();


/* ── vox.profile.js ──────────────────────────────────── */
(function () {
  'use strict';

  const { get, qs, qsa, escHtml } = window.Vox;

  // ── Profile tabs ──────────────────────────────────────────────────────
  //
  // Expected HTML:
  //   <button data-profile-tab="activity">Activity</button>
  //   <button data-profile-tab="badges">Badges</button>
  //   <div data-profile-panel="activity">…</div>
  //   <div data-profile-panel="badges" hidden>…</div>

  function initProfileTabs() {
    const tabs = qsa('[data-profile-tab]');
    if (!tabs.length) return;

    tabs.forEach(tab => {
      if (tab.dataset.voxTabInit) return;
      tab.dataset.voxTabInit = '1';

      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('vox-profile-tab--active'));
        tab.classList.add('vox-profile-tab--active');

        const target = tab.dataset.profileTab;
        qsa('[data-profile-panel]').forEach(panel => {
          panel.hidden = panel.dataset.profilePanel !== target;
        });
      });
    });
  }

  // ── Leaderboard period switch ─────────────────────────────────────────
  //
  // Expected HTML:
  //   <button data-lb-period="month">This month</button>
  //   <button data-lb-period="week">This week</button>
  //   <button data-lb-period="all">All time</button>
  //   <div data-vox-leaderboard>…</div>

  function initLeaderboard() {
    const btns      = qsa('[data-lb-period]');
    const container = qs('[data-vox-leaderboard]');
    if (!btns.length || !container) return;

    btns.forEach(btn => {
      if (btn.dataset.voxLbInit) return;
      btn.dataset.voxLbInit = '1';

      btn.addEventListener('click', async () => {
        btns.forEach(b => b.classList.remove('vox-lb-btn--active'));
        btn.classList.add('vox-lb-btn--active');

        try {
          const data = await get('leaderboard/', {
            period: btn.dataset.lbPeriod,
            limit:  10,
          });
          renderLeaderboard(container, data);
        } catch (e) {
          console.error('[Vox] leaderboard error', e);
        }
      });
    });
  }

function renderLeaderboard(container, rows) {
    if (!Array.isArray(rows) || !rows.length) {
      container.innerHTML = '<div class="vox-empty">No data yet.</div>';
      return;
    }

    container.innerHTML = rows.map((row, i) => {
      const posClass = i === 0 ? 'vox-lb-pos--gold' : (i === 1 ? 'vox-lb-pos--silver' : 'vox-lb-pos--muted');
      const isYou    = row.is_current;
      return `
        <div class="vox-lb-row ${isYou ? 'vox-lb-row--you' : ''}">
          <span class="vox-lb-pos ${posClass}">${i + 1}</span>
          <span class="vox-av">${escHtml((row.name || 'U').slice(0, 2).toUpperCase())}</span>
          <span class="vox-lb-name">
            ${escHtml(row.name)}${isYou ? ' <span class="vox-lb-you">← you</span>' : ''}
          </span>
          <span class="vox-rank-badge">${escHtml(row.rank?.label || '')}</span>
          <span class="vox-lb-pts">${Number(row.points).toLocaleString()}</span>
        </div>
      `;
    }).join('');
  }

  // ── Export ────────────────────────────────────────────────────────────

  Object.assign(window.Vox, {
    initProfileTabs,
    initLeaderboard,
    renderLeaderboard,
  });

})();


/* ── vox.init.js ─────────────────────────────────────── */
(function () {
  'use strict';

  function init() {
    const V = window.Vox;

    V.initDataBars();

    // Entry forms and interactions (reviews, Q&A, threads, block comments)
    V.qsa('[data-vox-form]').forEach(V.initEntryForm);
    V.initEntryInteractions(document);

    // Block comment counts + panel toggle
    V.initBlockCounts();
    V.initBlockPanelActivation();
    V.initPanelToggle();

    // "Show more" buttons
    V.initShowMore();

    // Ratings page
    V.initRatingsFilter();
    V.initTagFilter();
    V.initSortSelect();
    V.initCommentToggles();

    // Photo uploads
    V.initPhotosIn(document);

    // Profile page
    V.initProfileTabs();
    V.initLeaderboard();
  }

  // Run after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose init for manual re-runs (e.g. after AJAX page loads)
  window.Vox.init = init;

})();


