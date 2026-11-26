/**
 * @file
 * Bookmark tanpa login: daftar disimpan di localStorage browser.
 *
 * - Tombol [data-kh-bookmark] di kartu dan halaman konten menyimpan/menghapus.
 * - Lencana jumlah di header ([data-kh-bookmarks-count]) ikut diperbarui, juga
 *   saat daftar berubah di tab lain.
 * - Halaman /tersimpan ([data-kh-bookmarks-page]) menampilkan daftar, lalu
 *   menyegarkan judul dan membuang konten yang sudah tidak tersedia.
 *
 * Data dari localStorage dianggap tidak tepercaya: tiap item dibersihkan dan
 * hanya alamat relatif ("/...") yang diterima, supaya tautan tidak bisa
 * diarahkan ke situs lain atau ke javascript:.
 */
(function (Drupal, once) {
  const KEY = 'kh-bookmarks';
  const MAX = 100;
  const EVENT = 'kh:bookmarks';

  function validUrl(u) {
    return typeof u === 'string' && /^\/(?!\/)/.test(u) && u.length < 500;
  }

  function clean(item) {
    if (!item || typeof item !== 'object') return null;
    const id = String(item.id || '').replace(/\D/g, '');
    if (!id || !validUrl(item.url)) return null;
    return {
      id,
      title: String(item.title || '').slice(0, 200),
      url: item.url,
      jenis: String(item.jenis || '').slice(0, 40),
      snippet: String(item.snippet || '').slice(0, 200),
      saved: Number(item.saved) || 0,
    };
  }

  function read() {
    try {
      const raw = JSON.parse(localStorage.getItem(KEY) || '[]');
      const seen = new Set();
      return (Array.isArray(raw) ? raw : []).map(clean).filter((i) => {
        if (!i || seen.has(i.id)) return false;
        seen.add(i.id);
        return true;
      });
    } catch (e) { return []; }
  }

  function write(list) {
    try {
      localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX)));
    } catch (e) { return false; }
    document.dispatchEvent(new CustomEvent(EVENT));
    return true;
  }

  function has(id) {
    return read().some((i) => i.id === String(id));
  }

  function add(item) {
    const c = clean(Object.assign({ saved: Date.now() }, item));
    if (!c) return false;
    return write([c].concat(read().filter((i) => i.id !== c.id)));
  }

  function remove(id) {
    return write(read().filter((i) => i.id !== String(id)));
  }

  function toast(message) {
    if (Drupal.khToast) Drupal.khToast(message);
  }

  Drupal.khBookmarks = { list: read, has, add, remove, replaceAll: write, max: MAX };

  /** Samakan tombol dan lencana dengan isi daftar. */
  function syncUi() {
    const items = read();
    const ids = new Set(items.map((i) => i.id));
    document.querySelectorAll('[data-kh-bookmark]').forEach((btn) => {
      const on = ids.has(btn.dataset.nid);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      const label = btn.querySelector('.bookmark-label');
      if (label) label.textContent = on ? 'Tersimpan' : 'Simpan';
    });
    document.querySelectorAll('[data-kh-bookmarks-count]').forEach((badge) => {
      badge.textContent = String(items.length);
      badge.hidden = items.length === 0;
    });
    document.querySelectorAll('.bookmarks-link').forEach((link) => {
      link.setAttribute('aria-label', items.length ? 'Tersimpan, ' + items.length + ' konten' : 'Tersimpan');
    });
  }

  document.addEventListener(EVENT, syncUi);
  window.addEventListener('storage', (e) => {
    if (e.key === KEY || e.key === null) document.dispatchEvent(new CustomEvent(EVENT));
  });

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  Drupal.behaviors.khBookmarks = {
    attach(context) {
      once('kh-bookmark-btn', '[data-kh-bookmark]', context).forEach((btn) => {
        btn.addEventListener('click', (e) => {
          // Tombol berada di dalam kartu yang seluruhnya bisa diklik.
          e.preventDefault();
          e.stopPropagation();
          const id = btn.dataset.nid;
          if (has(id)) {
            toast(remove(id) ? 'Dihapus dari Tersimpan.' : 'Tidak bisa mengubah daftar di browser ini.');
            return;
          }
          if (read().length >= MAX) {
            toast('Daftar penuh (' + MAX + '). Hapus beberapa konten dulu.');
            return;
          }
          const ok = add({
            id,
            title: btn.dataset.title,
            url: btn.dataset.url,
            jenis: btn.dataset.jenis,
            snippet: btn.dataset.snippet,
          });
          toast(ok ? 'Disimpan ke Tersimpan.' : 'Tidak bisa menyimpan di browser ini.');
        });
      });
      syncUi();

      once('kh-bookmark-page', '[data-kh-bookmarks-page]', context).forEach((root) => {
        const toolbar = root.querySelector('.saved-toolbar');
        const countEl = root.querySelector('[data-count]');
        const heading = root.querySelector('.saved-count');
        const note = root.querySelector('.saved-note');
        const list = root.querySelector('.saved-list');
        const empty = root.querySelector('.saved-empty');
        const clearBtn = root.querySelector('.saved-clear');
        let focusHeading = false;

        function setNote(text) {
          note.textContent = text || '';
          note.hidden = !text;
        }

        function render() {
          const items = read();
          toolbar.hidden = items.length === 0;
          list.hidden = items.length === 0;
          empty.hidden = items.length !== 0;
          countEl.textContent = String(items.length);
          list.textContent = '';

          items.forEach((item) => {
            const li = el('li', 'teaser saved-item');
            const row = el('div', 'teaser-row');
            if (item.jenis) row.appendChild(el('span', 'teaser-type', item.jenis));
            li.appendChild(row);

            const h3 = el('h3');
            const a = el('a', '', item.title || 'Tanpa judul');
            a.href = item.url;
            h3.appendChild(a);
            li.appendChild(h3);
            if (item.snippet) li.appendChild(el('p', '', item.snippet));

            const rm = el('button', 'saved-remove', 'Hapus');
            rm.type = 'button';
            rm.setAttribute('aria-label', 'Hapus dari Tersimpan: ' + item.title);
            rm.addEventListener('click', () => {
              focusHeading = true;
              remove(item.id);
            });
            li.appendChild(rm);
            list.appendChild(li);
          });

          if (focusHeading) {
            focusHeading = false;
            (items.length ? heading : empty.querySelector('h2')).setAttribute('tabindex', '-1');
            (items.length ? heading : empty.querySelector('h2')).focus();
          }
        }

        clearBtn.addEventListener('click', () => {
          if (window.confirm('Hapus semua konten dari daftar Tersimpan?')) {
            focusHeading = true;
            write([]);
          }
        });

        document.addEventListener(EVENT, render);
        render();

        // Segarkan dari server: judul terbaru, dan buang konten yang tidak lagi tersedia.
        const saved = read();
        if (!saved.length) return;
        fetch(Drupal.url('kh/tersimpan-data') + '?ids=' + saved.map((i) => i.id).join(','), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        })
          .then((res) => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
          })
          .then((data) => {
            const fresh = new Map((Array.isArray(data.items) ? data.items : []).map((i) => [String(i.id), i]));
            const before = read();
            const next = [];
            before.forEach((old) => {
              const f = fresh.get(old.id);
              if (!f) return;
              const merged = clean(Object.assign({}, old, f));
              if (merged) next.push(merged);
            });
            const removed = before.length - next.length;
            const changed = removed > 0 || JSON.stringify(before) !== JSON.stringify(next);
            if (changed) write(next);
            if (removed > 0) {
              setNote(removed + ' konten tidak lagi tersedia dan dihapus dari daftar.');
            }
          })
          .catch(() => {
            setNote('Menampilkan data yang tersimpan di browser. Pembaruan dari server belum berhasil.');
          });
      });
    },
  };
})(Drupal, once);
