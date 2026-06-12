/**
 * vox/entry.js — entry form submission, DOM card builder, show-more.
 * Requires: core.js, stars.js, reply.js, vote.js
 * @version 1.0.0
 */
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
