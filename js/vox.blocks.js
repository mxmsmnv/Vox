/**
 * vox/blocks.js — block comment counts, panel activation, inline/sidebar toggle.
 * Requires: core.js
 * @version 1.0.0
 */
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
