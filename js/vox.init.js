/**
 * vox/init.js — main initialiser. Wires all modules together.
 * Requires: core.js, stars.js, vote.js, reply.js, entry.js,
 *           blocks.js, filters.js, photos.js, profile.js
 * @version 1.0.0
 */
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
