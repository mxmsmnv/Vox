/**
 * vox/stars.js — interactive rating picker and recommendation buttons.
 * Requires: core.js
 * @version 1.0.0
 */
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
