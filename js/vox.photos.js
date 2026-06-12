/**
 * vox/photos.js — photo upload preview and drag-and-drop zone.
 * Requires: core.js
 * @version 1.0.0
 */
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
