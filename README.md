# Project Akhir MKW: Knowledge Hub Organisasi (Drupal)

Implementasi IA Knowledge Hub ke CMS Drupal 11. Prototipe acuan ada di `../Mock_Web_Knowledge_Hub/`.

Folder ini hanya untuk Drupal. Laporan dan presentasi menyusul nanti.

## Arahan dosen (review Milestone 3)

- Tanpa login. Semua konten terbuka dan gratis.
- Tanpa penyimpanan progres belajar. Pembelajaran dipelajari sekali jalan.
- Navigasi global dipikirkan ulang. Prototipe memakai departemen (HR, Finance, IT, Operations) dan memindahkan lima area ke sub-bar Jenis konten.
- Tujuan: konten terbaca manusia dan dipahami mesin.

## Tiga kriteria nilai presentasi akhir

- [ ] **Mudah ditemukan.** Setiap konten dan kategori punya permanent link (alias URL via Pathauto) yang bisa di-bookmark.
- [ ] **Metadata terbaca mesin.** Schema.org JSON-LD di setiap konten (Schema.org Metatag) dan meta description.
- [ ] **Entri admin tidak salah kategori.** Taxonomy terkontrol, field wajib, dan area terisi otomatis dari jenis konten.

## Pemetaan prototipe ke Drupal

| Prototipe | Drupal |
|-----------|--------|
| 13 jenis konten (SOP, FAQ, Template, dan lainnya) | Content type atau satu content type dengan field Jenis |
| Departemen, Audience, Topik, Difficulty | Vocabulary taxonomy |
| Facet filter di kolom kiri | Views dengan exposed filter (Facets) |
| Konten terkait di kolom kanan | Views blok berdasarkan term Topik |
| Status Published / Draft | Content moderation |
| `#admin` Tambah Konten | Form tambah konten Drupal |

## Docker lokal

Drupal 11 dan MariaDB 11. Image resmi mendukung arm64, jadi jalan di Mac M3 maupun Windows.

### Cara cepat (Windows)

Pasang dulu [Docker Desktop](https://www.docker.com/products/docker-desktop/) dan [Git for Windows](https://git-scm.com/download/win). Lalu klik dua kali `setup.bat`. Skrip itu menyalakan Docker bila belum jalan, memasang semuanya, dan mencetak alamat serta akun di akhir. Pertama kali butuh 5 sampai 10 menit. Aman dijalankan ulang.

### Mulai (instalasi bersih, manual)

```bash
docker compose up -d
bash setup/restore.sh
```

`restore.sh` memasang Drupal dari `config/`, mengisi term, role, 33 konten, alias, metadata, dan indeks pencarian. Tanpa langkah manual. Buka http://127.0.0.1:8080.

Periksa kapan saja: `bash setup/verify.sh` (24 pemeriksaan, 26 bila `CHROME` diisi untuk uji browser).

Login lokal (jangan dipakai di server publik):

| Akun | Sandi | Role |
|------|-------|------|
| `admin` | `admin` | pembuat situs |
| `demo_superadmin` | `demo` | Super Admin |
| `demo_admin` | `demo` | Admin |
| `demo_auditor` | `demo` | Auditor |

Panduan admin panel (alamat, hak tiap role, cara menambah konten): `docs/admin-panel.md`. Model konten, field, vocabulary, role, dan workflow: `docs/content-model.md`.

Frontend (responsif, aksesibilitas, SEO, cara uji di browser): `docs/frontend.md`.

Tampilan: tema `themes/kh_theme` (modern minimalis: token warna, header pill, kartu rounded, satu aksen indigo, font Inter lokal, mode terang dan gelap). Beranda dari modul `modules/kh_home`, bentuk isi per jenis dari `setup/body.php`.

### Beranda (`modules/kh_home`)

`HomeController` hanya menyiapkan data (hero, lima area, konten terbaru, FAQ). Markup ada di Twig:

| Berkas | Isi |
|--------|-----|
| `templates/kh-home.html.twig` | Susunan beranda: hero, kartu area, Baru diperbarui, Pertanyaan populer, banner bantuan. Teks tetap bisa diubah langsung di sini. |
| `templates/kh-home-icon.html.twig` | Ikon SVG kartu area (`steps`, `book`, `play`, `help`, `doc`). |
| `kh_home.module` | Mendaftarkan theme hook `kh_home`. |

Untuk mengubah tampilan beranda tanpa menyentuh modul, salin `kh-home.html.twig` ke `themes/kh_theme/templates/` lalu `drush cr`. Salinan di tema yang dipakai.

### Fitur antarmuka

| Fitur | Cara pakai atau memeriksa | Berkas |
|-------|---------------------------|--------|
| Command palette | Tekan **Ctrl+K** (⌘K di Mac) atau **/**, atau klik kolom cari di header. Kolom kosong menampilkan pintasan menu dan pencarian terakhir. Mengetik dua huruf atau lebih memunculkan hasil langsung. Panah memilih, Enter membuka, Esc menutup. | `themes/kh_theme/js/palette.js`, `modules/kh_search` |
| Filter di mobile | Di layar ≤860px muncul tombol **Filter** (dengan jumlah filter aktif) di atas hasil. Ketuk untuk membuka panel dari bawah; Esc, Tutup, atau ketuk di luar panel menutupnya. Setiap pilihan memuat ulang halaman dan panel terbuka lagi. Filter aktif tampil sebagai chip di semua ukuran layar, bisa dihapus satu per satu atau lewat "Hapus semua". | `themes/kh_theme/js/facets.js` |
| Bookmark (Tersimpan) | Ikon penanda di tiap kartu dan tombol **Simpan** di halaman konten. Ikon di header menunjukkan jumlahnya dan membuka `/tersimpan`. Daftar juga muncul di command palette (Ctrl+K). Tanpa login: daftar disimpan di browser (maksimal 100 konten) dan tidak ikut berpindah perangkat. | `themes/kh_theme/js/bookmarks.js`, `modules/kh_bookmark` |
| Umpan balik "Apakah halaman ini membantu?" | Muncul di bawah isi konten Published. Pilih 👍 atau 👎 (👎 membuka kolom komentar). Laporan: `/admin/reports/kh-feedback`. | `modules/kh_feedback`, `templates/node--kh--full.html.twig` |
| Banner kesegaran | Di halaman konten: "Perlu review" bila Review Date terlewat, "Segera direview" 30 hari sebelumnya, "Diarsipkan", dan "Pratinjau" untuk konten belum terbit. | `kh_theme_freshness()` di `kh_theme.theme` |
| Cetak | Ctrl+P di halaman konten. Header, footer, facet, dan umpan balik disembunyikan, warna dipaksa terang, URL tautan eksternal dicetak. | `@media print` di `css/style.css` |
| Mode gelap | Tombol di header. Pilihan disimpan di browser. | `js/theme.js`, `templates/html.html.twig` |
| Branding | Logo (simbol, horizontal, putih, mono), favicon, apple-touch-icon, dan gambar OG. Aturan pakai ada di `branding/README.md`. | `themes/kh_theme/branding/` |

Catatan teknis:

- **Umpan balik tanpa login**, sesuai arahan dosen. Dibatasi 5 kiriman per jam per perangkat per konten, komentar maksimal 500 karakter, dan hanya untuk konten Published dari lima content type Knowledge Hub. Data disimpan di tabel `kh_feedback` dan ikut terhapus bersama kontennya. Laporan perlu role dengan izin *access content overview*.
- **Pencarian cepat** (`/kh/cari-cepat?q=...`) menggabungkan dua sumber: judul yang memuat kata (supaya kata yang belum selesai diketik cocok) dan teks lengkap dari indeks Search API `kh_index` (yang memakai `matching: words`). Hanya konten yang boleh dilihat pengguna yang dikembalikan.
- **Bookmark** disimpan di localStorage, bukan di server, sesuai arahan "tanpa login". Halaman `/tersimpan` menampilkan daftar dari browser, lalu memanggil `/kh/tersimpan-data?ids=...` untuk menyegarkan judul dan membuang konten yang sudah tidak Published atau tidak boleh dilihat. Isi localStorage dianggap tidak tepercaya: hanya alamat relatif yang diterima.
- Favicon dan gambar OG dipasang oleh `kh_theme_page_attachments_alter()`. Gambar OG tidak ditimpa bila modul metatag sudah mengisinya.
- Banner kesegaran membatasi cache node menjadi satu hari agar tanggalnya ikut berganti.

### Memperbarui situs yang sudah berjalan

Instalasi bersih (`setup.bat` atau `setup/restore.sh`) sudah mengaktifkan semuanya. Untuk situs yang sudah jalan:

```bash
docker compose exec drupal vendor/bin/drush en kh_feedback kh_search kh_bookmark -y
docker compose exec drupal vendor/bin/drush cr
```

Lalu tekan Ctrl+F5 di browser agar CSS dan JS baru terbaca. Jalankan `bash setup/verify.sh` untuk memastikan tidak ada yang rusak.

### Cek tiga kriteria nilai

| Kriteria | Cara memeriksa |
|----------|----------------|
| Mudah ditemukan | Buka `/prosedur/sop-perjalanan-dinas` dan `/topik/cuti`. Alamat tetap dan bisa di-bookmark. |
| Metadata terbaca mesin | Lihat sumber halaman konten: `<meta name="description">` dan `<script type="application/ld+json">`. |
| Entri admin tidak salah kategori | Masuk sebagai `demo_admin`, tambah Bantuan: pilihan Jenis hanya FAQ dan Troubleshooting. Kosongkan Departemen, Topik, atau Ringkasan: form menolak. |

### Ekspor konfigurasi ke repo

```bash
docker compose exec drupal vendor/bin/drush config:export -y
```

Hasilnya masuk ke `config/` dan bisa di-commit.

### Perintah harian

```bash
docker compose stop            # berhenti, data aman
docker compose down            # hapus container, data tetap di volume
docker compose down -v         # hapus semua data (instalasi mulai dari nol)
```

### Catatan

- Kode inti Drupal ada di volume `drupal-code`, bukan di folder ini. Yang masuk repo: `config/`, `modules/` (modul custom), `themes/` (tema `kh_theme`), dan `setup/`. Ketiganya di-mount ke container, jadi perubahan file langsung terbaca setelah `drush cr`.
- Kata sandi di file ini hanya untuk lokal. Jangan dipakai di server publik.
- Port 8080 dipakai aplikasi lain? Set `DRUPAL_PORT=8081` (dan `BASE_URL=http://127.0.0.1:8081` untuk `verify.sh`) sebelum `docker compose up -d`.
- Drupal core diperbarui dengan `docker compose exec drupal composer update "drupal/core-*" -W`, lalu `drush updatedb -y` dan `drush cr`.
