/**
 * vox/filters.js — ratings histogram filter, tag filter, sort select, comment toggles.
 * Requires: core.js
 * @version 1.0.0
 */
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
