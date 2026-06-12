/**
 * vox/vote.js — like/vote, report and best-answer buttons.
 * Requires: core.js
 * @version 1.0.0
 */
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
