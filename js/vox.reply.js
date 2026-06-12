/**
 * vox/reply.js — reply form toggle and stop word checker.
 * Requires: core.js
 * @version 1.0.0
 */
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
