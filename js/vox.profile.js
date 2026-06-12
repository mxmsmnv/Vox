/**
 * vox/profile.js — profile page tabs and leaderboard period switcher.
 * Requires: core.js
 * @version 1.0.0
 */
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
