/**
 * Vox admin icon picker — FontAwesome (library bundled from the Start module).
 *
 * Enhances any <input data-vox-icon> by replacing the visible text field with
 * a clickable live preview that opens a searchable grid. Picking one writes
 * its name (e.g. "trophy") into the hidden input.
 *
 * Reads the icon list from window.VoxFA = { solid:[...], brands:[...] }.
 */
(function () {
  'use strict';
  if (window.__voxIconPicker) return;
  window.__voxIconPicker = true;

  var FA = window.VoxFA || { solid: [], brands: [] };
  var BRANDS = {};
  FA.brands.forEach(function (n) { BRANDS[n] = true; });

  function faClass(name) {
    return (BRANDS[name] ? 'fa-brands' : 'fa-solid') + ' fa-' + name;
  }

  // Shared modal (built once)
  var modal, grid, search, activeInput, activePreview, renderLimit = 300;

  function buildModal() {
    modal = document.createElement('div');
    modal.className = 'vox-ip-overlay ProcessVox';
    modal.innerHTML =
      '<div class="vox-ip-dialog">' +
        '<div class="vox-ip-head">' +
          '<input type="text" class="uk-input vox-ip-search" placeholder="Search icons…">' +
          '<button type="button" class="uk-icon-button vox-ip-close" uk-icon="close"></button>' +
        '</div>' +
        '<div class="vox-ip-grid"></div>' +
        '<div class="vox-ip-foot uk-text-meta"></div>' +
      '</div>';
    document.body.appendChild(modal);
    grid   = modal.querySelector('.vox-ip-grid');
    search = modal.querySelector('.vox-ip-search');

    modal.addEventListener('click', function (e) {
      if (e.target === modal) close();
    });
    modal.querySelector('.vox-ip-close').addEventListener('click', close);
    search.addEventListener('input', function () { render(search.value.trim().toLowerCase()); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('vox-ip-open')) close();
    });
  }

  function render(q) {
    var all = FA.solid.concat(FA.brands);
    var list = q ? all.filter(function (n) { return n.indexOf(q) !== -1; }) : all;
    var shown = list.slice(0, renderLimit);
    var html = '';
    for (var i = 0; i < shown.length; i++) {
      var n = shown[i];
      html += '<button type="button" class="vox-ip-item" data-icon="' + n + '" title="' + n + '">' +
                '<i class="' + faClass(n) + '"></i><span>' + n + '</span></button>';
    }
    grid.innerHTML = html || '<div class="uk-text-muted uk-padding-small">No icons match “' + q + '”.</div>';
    var foot = modal.querySelector('.vox-ip-foot');
    foot.textContent = list.length > renderLimit
      ? ('Showing ' + renderLimit + ' of ' + list.length + ' — keep typing to narrow down.')
      : (list.length + ' icon' + (list.length === 1 ? '' : 's'));
  }

  grid_click_delegate();
  function grid_click_delegate() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('.vox-ip-item');
      if (!btn || !modal || !modal.classList.contains('vox-ip-open')) return;
      var name = btn.getAttribute('data-icon');
      if (activeInput) {
        activeInput.value = name;
        activeInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      if (activePreview) activePreview.className = 'vox-ip-preview ' + faClass(name);
      close();
    });
  }

  function open(input, preview) {
    if (!modal) buildModal();
    activeInput = input;
    activePreview = preview;
    modal.classList.add('vox-ip-open');
    search.value = '';
    render('');
    setTimeout(function () { search.focus(); }, 30);
  }
  function close() { if (modal) modal.classList.remove('vox-ip-open'); }

  function enhance(input) {
    if (input.__voxIp) return;
    input.__voxIp = true;
    input.classList.add('vox-ip-input');
    input.type = 'hidden';

    var wrap = document.createElement('span');
    wrap.className = 'vox-ip-field';
    input.parentNode.insertBefore(wrap, input);

    var preview = document.createElement('button');
    preview.type = 'button';
    preview.setAttribute('aria-label', 'Choose icon');
    preview.setAttribute('title', 'Choose icon');
    var setPreview = function () {
      var value = input.value ? input.value.trim() : '';
      preview.className = 'vox-ip-preview ' + (value ? faClass(value) : 'fa-solid fa-icons');
      preview.setAttribute('title', value ? ('Change icon: ' + value) : 'Choose icon');
    };
    preview.addEventListener('click', function () { open(input, preview); });

    wrap.appendChild(preview);
    wrap.appendChild(input);
    input.addEventListener('input', setPreview);
    setPreview();
  }

  function scan(root) {
    (root || document).querySelectorAll('input[data-vox-icon]').forEach(enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(); });
  } else {
    scan();
  }
  // Expose for dynamically added inputs (e.g. new rank rows)
  window.voxIconPickerScan = scan;
  window.voxIconFaClass = faClass;
})();
