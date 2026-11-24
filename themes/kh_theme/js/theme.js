/**
 * @file
 * Mode gelap dan tombol menu mobile. Pilihan tema disimpan di localStorage (sama dengan prototipe).
 */
(function (Drupal, once) {
  Drupal.behaviors.khTheme = {
    attach(context) {
      const root = document.documentElement;
      once('kh-theme-toggle', '#theme-toggle', context).forEach((btn) => {
        btn.addEventListener('click', () => {
          root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
          localStorage.setItem('kh-theme', root.dataset.theme);
        });
      });
      once('kh-menu-toggle', '.menu-toggle', context).forEach((btn) => {
        btn.addEventListener('click', () => {
          const open = btn.getAttribute('aria-expanded') !== 'true';
          btn.setAttribute('aria-expanded', open);
          document.body.classList.toggle('nav-open', open);
        });
      });
    },
  };
})(Drupal, once);
