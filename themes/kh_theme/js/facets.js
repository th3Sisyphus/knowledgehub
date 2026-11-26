/**
 * @file
 * Facet di mobile: tombol "Filter" membuka panel dari bawah (drawer), dan filter
 * aktif tampil sebagai chip yang bisa dihapus satu per satu atau sekaligus.
 *
 * Tanpa JS, facet tetap tampil biasa di atas hasil. Di desktop hanya chip filter
 * aktif yang ditambahkan; sidebar tidak berubah.
 */
(function (Drupal, once) {
  const MOBILE = '(max-width: 860px)';
  const REOPEN_KEY = 'kh-facets-reopen';
  const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function store(action, value) {
    try {
      if (action === 'set') sessionStorage.setItem(REOPEN_KEY, value);
      else if (action === 'clear') sessionStorage.removeItem(REOPEN_KEY);
      else return sessionStorage.getItem(REOPEN_KEY);
    } catch (e) { /* abaikan */ }
    return null;
  }

  /** Facet aktif: tautan is-active (tanpa JS facets) atau kotak centang terpilih (dengan JS facets). */
  function isActive(item) {
    return item.classList.contains('is-active')
      || !!item.querySelector('a.is-active, .facet-item__status.is-active, input:checked');
  }

  function itemLabel(item) {
    const value = item.querySelector('.facet-item__value');
    let text;
    if (value) {
      text = value.textContent;
    } else {
      const link = item.querySelector('a') || item;
      const clone = link.cloneNode(true);
      clone.querySelectorAll('.facet-item__count, .count, input').forEach((n) => n.remove());
      text = clone.textContent;
    }
    return text.replace(/\s+/g, ' ').trim();
  }

  function groupLabel(item) {
    const block = item.closest('.block-facets, .block');
    const heading = block && block.querySelector('h2, h3, legend');
    return heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
  }

  /** Alamat halaman ini tanpa parameter facet (f[0], f[1], ...) dan tanpa nomor halaman. */
  function clearAllUrl() {
    const url = new URL(window.location.href);
    Array.from(url.searchParams.keys()).forEach((key) => {
      if (key === 'page' || /^f(\[\d*\])?$/.test(key)) url.searchParams.delete(key);
    });
    return url.pathname + (url.search || '');
  }

  Drupal.behaviors.khFacets = {
    attach(context) {
      once('kh-facets', '.sidebar.facets', context).forEach((panel) => {
        const items = Array.from(panel.querySelectorAll('.facet-item'));
        if (!items.length) return;

        const root = document.documentElement;
        const mq = window.matchMedia ? window.matchMedia(MOBILE) : { matches: false, addEventListener() {} };
        panel.id = panel.id || 'facets-panel';
        root.classList.add('facets-js');

        const active = items.filter(isActive);

        // ---- Panel: kepala (judul + tutup) dan kaki (terapkan + hapus semua).
        const head = el('div', 'facets-head');
        head.appendChild(el('strong', '', 'Filter'));
        const closeBtn = el('button', 'facets-close', 'Tutup');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Tutup filter');
        head.appendChild(closeBtn);
        panel.insertBefore(head, panel.firstChild);

        const foot = el('div', 'facets-foot');
        const applyBtn = el('button', 'button button--primary', 'Tampilkan hasil');
        applyBtn.type = 'button';
        foot.appendChild(applyBtn);
        if (active.length) {
          const clear = el('a', 'facets-clear', 'Hapus semua');
          clear.href = clearAllUrl();
          foot.appendChild(clear);
        }
        panel.appendChild(foot);

        const backdrop = el('div', 'facets-backdrop');
        document.body.appendChild(backdrop);

        // ---- Bar di atas hasil: tombol Filter (mobile) dan chip filter aktif.
        const bar = el('div', 'facets-bar');
        const toggle = el('button', 'facets-toggle');
        toggle.type = 'button';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', panel.id);
        toggle.appendChild(el('span', '', 'Filter'));
        const badge = el('span', 'facets-count', String(active.length));
        badge.hidden = active.length === 0;
        toggle.appendChild(badge);
        toggle.setAttribute('aria-label', active.length ? 'Filter, ' + active.length + ' aktif' : 'Filter');
        bar.appendChild(toggle);

        if (active.length) {
          const wrap = el('div', 'active-filters');
          wrap.setAttribute('role', 'group');
          wrap.setAttribute('aria-label', 'Filter aktif');
          const list = el('ul');
          active.forEach((item) => {
            const link = item.querySelector('a[href]');
            if (!link) return;
            const group = groupLabel(item);
            const label = itemLabel(item);
            const li = el('li');
            const chip = el('a', 'chip chip--removable');
            chip.href = link.getAttribute('href');
            chip.setAttribute('aria-label', 'Hapus filter ' + (group ? group + ': ' : '') + label);
            chip.appendChild(el('span', '', label));
            const x = el('span', 'chip-x', '×');
            x.setAttribute('aria-hidden', 'true');
            chip.appendChild(x);
            li.appendChild(chip);
            list.appendChild(li);
          });
          wrap.appendChild(list);
          const clearAll = el('a', 'active-filters-clear', 'Hapus semua');
          clearAll.href = clearAllUrl();
          wrap.appendChild(clearAll);
          bar.appendChild(wrap);
        } else {
          bar.classList.add('is-empty');
        }

        const main = document.querySelector('.main-col');
        if (main) {
          const view = main.querySelector('.view');
          if (view && view.parentNode) view.parentNode.insertBefore(bar, view);
          else main.insertBefore(bar, main.firstChild);
        }

        // ---- Buka dan tutup drawer.
        let opener = null;
        function open() {
          if (!mq.matches || root.classList.contains('facets-open')) return;
          opener = document.activeElement;
          root.classList.add('facets-open');
          panel.setAttribute('role', 'dialog');
          panel.setAttribute('aria-modal', 'true');
          toggle.setAttribute('aria-expanded', 'true');
          closeBtn.focus();
        }
        function close(returnFocus) {
          if (!root.classList.contains('facets-open')) return;
          root.classList.remove('facets-open');
          panel.removeAttribute('role');
          panel.removeAttribute('aria-modal');
          toggle.setAttribute('aria-expanded', 'false');
          store('clear');
          if (returnFocus !== false) (opener && document.contains(opener) ? opener : toggle).focus();
          opener = null;
        }

        toggle.addEventListener('click', open);
        closeBtn.addEventListener('click', () => close());
        applyBtn.addEventListener('click', () => close());
        backdrop.addEventListener('click', () => close());

        document.addEventListener('keydown', (e) => {
          if (!root.classList.contains('facets-open')) return;
          if (e.key === 'Escape') {
            e.preventDefault();
            close();
          } else if (e.key === 'Tab') {
            // Fokus tetap di dalam panel selama terbuka.
            const nodes = Array.from(panel.querySelectorAll(FOCUSABLE)).filter((n) => !n.closest('[hidden], [inert]'));
            if (!nodes.length) return;
            const first = nodes[0];
            const last = nodes[nodes.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
          }
        });

        // Tiap pilihan facet memuat ulang halaman: ingat agar panel terbuka lagi.
        panel.addEventListener('click', (e) => {
          if (e.target.closest('.facet-item') && root.classList.contains('facets-open')) store('set', '1');
        });

        const onChange = (e) => { if (!e.matches) close(false); };
        if (mq.addEventListener) mq.addEventListener('change', onChange);
        else if (mq.addListener) mq.addListener(onChange);

        if (store('get') === '1') {
          store('clear');
          open();
        }
      });
    },
  };
})(Drupal, once);
