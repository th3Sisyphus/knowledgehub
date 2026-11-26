/**
 * @file
 * Mode gelap, menu mobile, toast, dan umpan balik "Apakah ini membantu?".
 * Pilihan tema disimpan di localStorage (sama dengan prototipe).
 */
(function (Drupal, once) {
  /**
   * Toast singkat di bawah layar. Satu elemen dipakai ulang, dibacakan pembaca layar.
   */
  let toastTimer;
  function khToast(message) {
    let el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      document.body.appendChild(el);
    }
    el.textContent = message;
    // Paksa reflow agar transisi berjalan walau toast dipanggil beruntun.
    void el.offsetWidth;
    el.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('is-on'), 3200);
  }
  Drupal.khToast = khToast;

  function storageGet(key) {
    try { return sessionStorage.getItem(key); } catch (e) { return null; }
  }
  function storageSet(key, value) {
    try { sessionStorage.setItem(key, value); } catch (e) { /* abaikan */ }
  }

  Drupal.behaviors.khTheme = {
    attach(context) {
      const root = document.documentElement;

      once('kh-theme-toggle', '#theme-toggle', context).forEach((btn) => {
        const sync = () => btn.setAttribute('aria-pressed', root.dataset.theme === 'dark' ? 'true' : 'false');
        sync();
        btn.addEventListener('click', () => {
          root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
          try { localStorage.setItem('kh-theme', root.dataset.theme); } catch (e) { /* abaikan */ }
          sync();
        });
      });

      once('kh-menu-toggle', '.menu-toggle', context).forEach((btn) => {
        const setOpen = (open) => {
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
          document.body.classList.toggle('nav-open', open);
        };
        btn.addEventListener('click', () => setOpen(btn.getAttribute('aria-expanded') !== 'true'));
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            btn.focus();
          }
        });
      });

      once('kh-feedback', '[data-kh-feedback]', context).forEach((box) => {
        const nid = box.dataset.khFeedback;
        const storeKey = 'kh-feedback-' + nid;
        const more = box.querySelector('.feedback-more');
        const thanks = box.querySelector('.feedback-thanks');
        const area = box.querySelector('textarea');
        const buttons = box.querySelectorAll('[data-helpful]');
        const sendBtn = box.querySelector('[data-send]');
        let helpful = null;
        let busy = false;

        const finish = () => {
          box.classList.add('is-done');
          thanks.hidden = false;
        };

        // Sudah menjawab di sesi ini: langsung tampilkan ucapan terima kasih.
        if (storageGet(storeKey)) {
          finish();
          return;
        }

        const submit = async (comment) => {
          if (busy) return;
          busy = true;
          try {
            const tokenRes = await fetch(Drupal.url('session/token'), { credentials: 'same-origin' });
            const token = await tokenRes.text();
            const res = await fetch(Drupal.url('kh/feedback/' + nid), {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
              body: JSON.stringify({ helpful: helpful ? 1 : 0, comment }),
            });
            if (res.status === 429) {
              khToast('Terlalu banyak masukan dari perangkat ini. Coba lagi nanti.');
              return;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            storageSet(storeKey, '1');
            finish();
            khToast('Terima kasih, masukan Anda tercatat.');
          } catch (e) {
            khToast('Masukan belum terkirim. Coba lagi.');
          } finally {
            busy = false;
          }
        };

        buttons.forEach((btn) => {
          btn.addEventListener('click', () => {
            helpful = btn.dataset.helpful === '1';
            buttons.forEach((b) => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
            if (helpful) {
              more.hidden = true;
              submit('');
            } else {
              more.hidden = false;
              area.focus();
            }
          });
        });

        sendBtn.addEventListener('click', () => submit(area.value.trim()));
      });
    },
  };
})(Drupal, once);
