/**
 * @file
 * Command palette (Ctrl+K atau /): pintasan, pencarian terakhir, dan hasil cepat.
 *
 * Tanpa JS, form pencarian di header tetap bekerja seperti biasa. Palette memakai
 * <dialog> bawaan browser (fokus terkurung, Esc menutup) dengan pola combobox.
 */
(function (Drupal, once) {
  const RECENT_KEY = 'kh-recent-q';
  const MAX_RECENT = 5;
  const MIN_CHARS = 2;
  const DEBOUNCE_MS = 180;
  const SVG_SEARCH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';

  function readRecent() {
    try {
      const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
      return Array.isArray(list) ? list.filter((x) => typeof x === 'string').slice(0, MAX_RECENT) : [];
    } catch (e) { return []; }
  }
  function saveRecent(q) {
    const term = q.trim();
    if (term.length < MIN_CHARS) return;
    const list = [term].concat(readRecent().filter((x) => x.toLowerCase() !== term.toLowerCase())).slice(0, MAX_RECENT);
    try { localStorage.setItem(RECENT_KEY, JSON.stringify(list)); } catch (e) { /* abaikan */ }
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  /** Teks dengan kata kunci dibungkus <mark>, dibangun lewat DOM agar aman dari XSS. */
  function highlight(text, query) {
    const frag = document.createDocumentFragment();
    const words = query.split(/\s+/).filter(Boolean).map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    if (!words.length) {
      frag.append(text);
      return frag;
    }
    const re = new RegExp('(' + words.join('|') + ')', 'gi');
    text.split(re).forEach((part, i) => {
      if (i % 2 === 1) frag.append(el('mark', '', part));
      else if (part) frag.append(part);
    });
    return frag;
  }

  function buildDialog() {
    const dialog = el('dialog', 'palette');
    dialog.setAttribute('aria-label', 'Cari di Knowledge Hub');
    dialog.innerHTML =
      '<div class="palette-box">' +
        '<div class="palette-field">' + SVG_SEARCH +
          '<input class="palette-input" type="text" role="combobox" aria-expanded="true" aria-controls="palette-list" aria-autocomplete="list" ' +
          'autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Cari prosedur, referensi, tutorial…" aria-label="Cari di Knowledge Hub">' +
          '<kbd aria-hidden="true">Esc</kbd>' +
        '</div>' +
        '<p class="visually-hidden" role="status" aria-live="polite"></p>' +
        '<ul class="palette-list" id="palette-list" role="listbox" aria-label="Hasil pencarian"></ul>' +
        '<div class="palette-foot" aria-hidden="true">' +
          '<span><kbd>↑</kbd><kbd>↓</kbd> pilih</span><span><kbd>↵</kbd> buka</span><span><kbd>Esc</kbd> tutup</span>' +
        '</div>' +
      '</div>';
    document.body.appendChild(dialog);
    return dialog;
  }

  Drupal.behaviors.khPalette = {
    attach(context) {
      once('kh-palette', 'body', context).forEach(() => {
        if (typeof HTMLDialogElement === 'undefined') return;

        const searchPath = Drupal.url('cari');
        const dialog = buildDialog();
        const input = dialog.querySelector('.palette-input');
        const list = dialog.querySelector('.palette-list');
        const status = dialog.querySelector('[role="status"]');
        let options = [];
        let active = -1;
        let timer = null;
        let controller = null;
        let opener = null;
        let counter = 0;

        const go = (href, q) => {
          if (q) saveRecent(q);
          window.location.href = href;
        };
        const fullSearchUrl = (q) => searchPath + '?search_api_fulltext=' + encodeURIComponent(q);

        function setActive(index) {
          options.forEach((o, i) => {
            const on = i === index;
            o.el.setAttribute('aria-selected', on ? 'true' : 'false');
            o.el.classList.toggle('is-active', on);
          });
          active = index;
          if (index >= 0) {
            input.setAttribute('aria-activedescendant', options[index].el.id);
            options[index].el.scrollIntoView({ block: 'nearest' });
          } else {
            input.removeAttribute('aria-activedescendant');
          }
        }

        function addGroup(title) {
          list.appendChild(el('li', 'palette-group', title)).setAttribute('role', 'presentation');
        }

        function addOption(spec) {
          const li = el('li', 'palette-option');
          li.id = 'palette-opt-' + (++counter);
          li.setAttribute('role', 'option');
          li.setAttribute('aria-selected', 'false');
          const main = el('span', 'palette-main');
          main.appendChild(spec.title instanceof Node ? spec.title : document.createTextNode(spec.title));
          li.appendChild(main);
          if (spec.snippet) li.appendChild(el('span', 'palette-snippet', spec.snippet));
          if (spec.tag) li.appendChild(el('span', 'palette-tag', spec.tag));
          li.addEventListener('click', () => go(spec.href, spec.remember));
          li.addEventListener('mousemove', () => {
            const idx = options.findIndex((o) => o.el === li);
            if (idx !== active) setActive(idx);
          });
          list.appendChild(li);
          options.push({ el: li, href: spec.href, remember: spec.remember });
        }

        function reset() {
          list.textContent = '';
          options = [];
          active = -1;
          input.removeAttribute('aria-activedescendant');
        }

        function renderIdle() {
          reset();
          const links = Array.from(document.querySelectorAll('.primary-nav a[href]'));
          if (links.length) {
            addGroup('Pintasan');
            links.forEach((a) => addOption({ title: a.textContent.trim(), href: a.getAttribute('href'), tag: 'Buka' }));
          }
          const saved = Drupal.khBookmarks ? Drupal.khBookmarks.list().slice(0, 5) : [];
          if (saved.length) {
            addGroup('Tersimpan');
            saved.forEach((s) => addOption({ title: s.title || 'Tanpa judul', href: s.url, tag: s.jenis || 'Buka' }));
          }
          const recent = readRecent();
          if (recent.length) {
            addGroup('Pencarian terakhir');
            recent.forEach((r) => addOption({ title: r, href: fullSearchUrl(r), remember: r }));
          }
          status.textContent = '';
          if (options.length) setActive(0);
        }

        function renderResults(q, items) {
          reset();
          if (items.length) {
            addGroup('Konten');
            items.forEach((it) => addOption({
              title: highlight(it.title, q),
              snippet: it.snippet,
              tag: it.jenis || '',
              href: it.url,
              remember: q,
            }));
          }
          addGroup('Semua hasil');
          addOption({ title: 'Cari “' + q + '” di semua konten →', href: fullSearchUrl(q), remember: q });
          status.textContent = items.length
            ? items.length + ' hasil. Gunakan panah atas dan bawah untuk memilih.'
            : 'Tidak ada hasil cepat. Tekan Enter untuk mencari di semua konten.';
          setActive(0);
        }

        async function search(q) {
          if (controller) controller.abort();
          controller = new AbortController();
          list.setAttribute('aria-busy', 'true');
          try {
            const res = await fetch(Drupal.url('kh/cari-cepat') + '?q=' + encodeURIComponent(q), {
              credentials: 'same-origin',
              headers: { Accept: 'application/json' },
              signal: controller.signal,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (input.value.trim() === q) renderResults(q, Array.isArray(data.items) ? data.items : []);
          } catch (e) {
            if (e.name === 'AbortError') return;
            // Endpoint gagal: tetap beri jalan ke pencarian penuh.
            renderResults(q, []);
          } finally {
            list.removeAttribute('aria-busy');
          }
        }

        input.addEventListener('input', () => {
          clearTimeout(timer);
          const q = input.value.trim();
          if (q.length < MIN_CHARS) {
            if (controller) controller.abort();
            renderIdle();
            return;
          }
          timer = setTimeout(() => search(q), DEBOUNCE_MS);
        });

        input.addEventListener('keydown', (e) => {
          if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!options.length) return;
            e.preventDefault();
            const step = e.key === 'ArrowDown' ? 1 : -1;
            setActive((active + step + options.length) % options.length);
          } else if (e.key === 'Enter') {
            e.preventDefault();
            const q = input.value.trim();
            if (active >= 0 && options[active]) {
              go(options[active].href, options[active].remember);
            } else if (q) {
              go(fullSearchUrl(q), q);
            }
          }
        });

        // Klik di luar kotak (pada backdrop) menutup palette.
        dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
        dialog.addEventListener('close', () => {
          clearTimeout(timer);
          if (controller) controller.abort();
          document.documentElement.classList.remove('palette-open');
          if (opener && document.contains(opener)) opener.focus();
          opener = null;
        });

        function open(from) {
          if (dialog.open) return;
          opener = from || document.activeElement;
          input.value = '';
          renderIdle();
          document.documentElement.classList.add('palette-open');
          dialog.showModal();
          input.focus();
        }
        Drupal.khPalette = { open, close: () => dialog.close() };

        document.addEventListener('keydown', (e) => {
          const meta = e.ctrlKey || e.metaKey;
          if (meta && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (dialog.open) dialog.close(); else open();
            return;
          }
          if (e.key === '/' && !meta && !e.altKey && !dialog.open) {
            const t = e.target;
            const typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
            if (!typing) {
              e.preventDefault();
              open();
            }
          }
        });

        // Petunjuk tombol: ⌘ K di Mac, Ctrl K di lainnya.
        if (/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) {
          document.querySelectorAll('.kbd-hint').forEach((k) => { k.textContent = '⌘ K'; });
        }

        // Kolom cari di header jadi pemicu: klik, atau Enter/Spasi/panah bawah saat fokus.
        document.querySelectorAll('[data-kh-palette]').forEach((trigger) => {
          trigger.readOnly = true;
          trigger.addEventListener('click', (e) => { e.preventDefault(); open(trigger); });
          trigger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
              e.preventDefault();
              open(trigger);
            }
          });
        });
      });
    },
  };
})(Drupal, once);
