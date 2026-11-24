# Desain: Knowledge Hub Organisasi di Drupal 11

Tanggal: 2026-11-16. Status: menunggu persetujuan.

## Maksud dan alasan

Prototipe `../Mock_Web_Knowledge_Hub/` (HTML/JS statis, IA Milestone 3) dipindahkan ke Drupal 11. Hasilnya dinilai di Milestone 4 (Content Model & Metadata, 15%), Milestone 5 (CMS Implementation, 15%), dan UAS (demo produk akhir, 15%). Rubrik RPS memberi nilai rendah untuk Page/Post generik, field free text, dan role atau workflow bawaan tanpa konfigurasi. Desain ini menjawab rubrik itu.

Arahan dosen (review Milestone 3), tertulis di `README.md`:
- Tanpa login pembaca. Semua konten terbuka dan gratis.
- Tanpa penyimpanan progres belajar.
- Navigasi global dipikirkan ulang.
- Konten terbaca manusia dan dipahami mesin.

Tiga kriteria nilai presentasi akhir:
1. Mudah ditemukan: permanent link (Pathauto) untuk setiap konten dan kategori.
2. Metadata terbaca mesin: JSON-LD schema.org per konten (Schema.org Metatag) dan meta description.
3. Entri admin tidak salah kategori: taxonomy terkontrol, field wajib, area terisi otomatis dari jenis konten.

## Tujuan, bukan tujuan, anti-tujuan

Tujuan:
- Seluruh 35 konten di `data.js` ada di Drupal dengan struktur field yang benar.
- Tiga kriteria nilai lulus dan dapat didemokan.
- Tiga role dengan workflow yang teruji.
- Seluruh konfigurasi tersimpan di `config/` dan dapat dipulihkan dengan `drush config:import`.
- Satu dokumen model konten (tabel content type, field, vocabulary) sebagai bahan Milestone 4.

Bukan tujuan:
- Laporan dan slide presentasi (menyusul, di luar folder ini).
- Login atau akun untuk pembaca. Progres belajar. Komentar. Multibahasa.
- Deployment ke server publik. Hanya Docker lokal.
- Tema dari nol. Tema `kh_theme` (di `themes/`) meniru tampilan prototipe bergaya Olivero: header dengan blok branding biru, hero, kartu 5 area, facet kiri, konten terkait kanan, mode gelap. Perubahan dari rancangan awal (Olivero polos) atas permintaan pengguna.

Anti-tujuan (dianggap gagal walau jalan):
- Konten berada di Basic page atau satu content type generik.
- Admin bisa memilih Jenis yang tidak cocok dengan grupnya.
- Konfigurasi hanya ada di volume Docker, tidak di repo.

## Batasan

- Drupal 11 dan MariaDB 11 lewat `docker-compose.yml` yang sudah ada. Mesin pengguna M3 Air (arm64), image resmi mendukung.
- Kode Drupal ada di volume `drupal-code`. Yang masuk repo: `config/`, `setup/`, `modules/` (modul custom pengunci form Auditor), `docs/`.
- Drupal belum terinstal. Folder `config/` kosong.
- Jangan commit atau push tanpa permintaan pengguna.

## Pendekatan

Struktur content type:
- **A. Lima content type, satu per grup (dipilih).** Prosedur, Referensi, Pembelajaran, Bantuan, Alat Kerja. Field Jenis berupa taxonomy terkontrol. Facet tiap grup berbeda, jadi field tiap grup memang berbeda. Jenis baru cukup menambah term.
- B. Tiga belas content type. Terlalu banyak form hampir identik dan Views berlipat.
- C. Satu content type dengan field Jenis. Mirip Page generik, berisiko nilai rendah.

Pengguna memilih A.

## Keputusan

### Role dan workflow

Tiga role. Pembaca anonim tidak punya akun dan hanya melihat konten Published.

| Role | Hak |
|------|-----|
| Super Admin | Semua akses: modul, konfigurasi, role, workflow. Bisa memakai role administrator bawaan Drupal. |
| Admin | Buat dan edit konten, kelola term taxonomy, kirim ke In Review. Tidak bisa Publish dan tidak bisa ubah konfigurasi. |
| Auditor | Lihat semua konten termasuk Draft dan In Review. Setujui (Published) atau kembalikan ke Draft. Isi konten tidak diubah (lihat catatan di bawah). |

Workflow (Content Moderation): Draft, In Review, Published, Archived.
- Draft ke In Review: Admin.
- Published ke Draft: Admin, lalu kirim ulang ke In Review (revisi konten terbit).
- In Review ke Published atau kembali ke Draft: Auditor.
- In Review ke Archived dan Published ke Archived: Auditor.
- Archived ke Draft: Auditor.
- Super Admin boleh semua transisi.

Akses node:
- Admin hanya melihat dan mengedit Draft miliknya sendiri. Admin melihat semua konten Published.
- Auditor melihat semua status.
- Anonim hanya melihat Published. Archived tidak tampil ke anonim.

Catatan hak Auditor (keputusan pengguna: opsi longgar): Drupal menaruh status moderasi di form edit node, sehingga Auditor mendapat izin edit. Semua field isi dikunci (read-only atau disembunyikan) untuk Auditor. Hanya status moderasi dan catatan revisi yang bisa diubah. Opsi ketat (Auditor tanpa izin edit, approval lewat Views bulk action) ditolak karena kerja lebih besar dengan hasil penilaian sama.

Dasar nama: RPS tidak memuat "auditor". RPS Milestone 6 memakai owner, author, reviewer, publisher. Auditor di sini setara reviewer dan publisher. Pengguna memastikan tiga role saja.

### Content type dan field

Lima content type, nama mesin dalam huruf kecil (`prosedur`, `referensi`, `pembelajaran`, `bantuan`, `alat_kerja`).

Field bersama semua tipe: Judul, Jenis (wajib), Departemen (wajib), Topik (wajib), Ringkasan (wajib, dipakai sebagai meta description), Isi, tanggal, status moderasi.

Field khusus (hanya di tipe yang memakainya di `data.js`, builder memetakan dari data):
- Audience (multi nilai): Prosedur, Referensi, Pembelajaran.
- Difficulty: hanya Pembelajaran.
- Versi, nomor dokumen, wajib dipatuhi (boolean), callout, sumber, durasi: mengikuti kolom `version`, `number`, `wajib`, `callout`, `source`, `duration` di `data.js`.

Grup dan jenis (dari `GROUPS` di `data.js`):
- Prosedur: SOP, Panduan Kerja, Checklist.
- Referensi: Knowledge Article, Kebijakan, Glossary, Studi Kasus.
- Pembelajaran: Tutorial, Video Tutorial.
- Bantuan: FAQ, Troubleshooting.
- Alat Kerja: Template.

Pencegahan salah kategori: pilihan Jenis di form hanya menampilkan jenis milik grup content type itu. Cara teknis (satu vocabulary hierarkis dengan filter, atau lima vocabulary) diserahkan ke builder. Hasilnya harus tidak mungkin memilih Jenis dari grup lain.

### Vocabulary

Semua vocabulary terkontrol. Admin tidak bisa membuat term baru dari form konten.
- Departemen: HR, Finance, IT, Operations.
- Audience: Pegawai Baru, Pegawai Lama, Supervisor, SME.
- Topik: Cuti, Keselamatan Kerja, Kinerja, Komunikasi, Pelaporan, Penggunaan Sistem, Perjalanan Dinas, Rekrutmen.
- Difficulty: Dasar, Menengah, Lanjutan.
- Jenis: 13 term di atas, dikelompokkan per grup.

Hanya Super Admin dan Admin yang boleh menambah atau mengubah term.

### Penemuan dan navigasi

- Daftar per grup dengan facet di kolom kiri. Facet per grup mengikuti prototipe: Prosedur dan Referensi (Departemen, Audience, Topik), Pembelajaran (Audience, Difficulty, Topik), Bantuan (Departemen, Topik), Alat Kerja (Departemen).
- Pencarian teks.
- Konten terkait di halaman konten: blok Views berdasarkan Topik yang sama.
- Alias URL lewat Pathauto, pola per content type (contoh `/prosedur/sop-perjalanan-dinas`) dan per term. Alias stabil dan dapat di-bookmark.

### Metadata mesin

- Meta description dari field Ringkasan.
- JSON-LD schema.org lewat Schema.org Metatag. Tipe schema dipilih builder per content type (contoh: FAQPage untuk FAQ, Article untuk Referensi). Syarat: JSON-LD valid di setiap konten Published.

### Data awal

35 entri di `data.js` diimpor ke Drupal sebagai node. Entri berstatus Draft di data (contoh `sop-lembur`) masuk sebagai Draft. Nilai lain masuk sebagai Published. Cara impor (skrip Drush atau migrate) diserahkan ke builder. `data.js` tetap di prototipe, tidak diubah.

### Konfigurasi dan dokumentasi

- `config:export` ke `config/` setelah tiap tahap selesai.
- Satu berkas `docs/content-model.md`: tabel content type, field, tipe data, wajib atau tidak, vocabulary, dan relasi. Berkas ini memenuhi kriteria "Dokumentasi model" Milestone 4.
- `README.md` diperbarui bila langkah pemasangan berubah.

### Navigasi global

Prototipe memakai departemen (HR, Finance, IT, Operations) di bar global dan lima grup di sub-bar. Dosen meminta ini dipikirkan ulang. Keputusan: bar global berisi lima grup (Prosedur, Referensi, Pembelajaran, Bantuan, Alat Kerja) dan Pencarian. Departemen menjadi facet, bukan menu. Alasan: pengguna mencari berdasarkan jenis kebutuhan, dan departemen sudah tersedia sebagai filter di setiap grup. (Dipilih pengguna dengan menerima rekomendasi.)

## Keputusan builder (tandai untuk dicek)

- Repo memuat `config/` dan folder `setup/`: daftar `composer require`, skrip seed term taxonomy, skrip impor 35 konten, dan akun demo untuk tiga role. Alasan: term taxonomy adalah konten, bukan config, jadi `config:export` tidak membawanya. Pemulihan bersih = `composer require` + `drush config:import` + skrip seed + skrip impor.
- Kolom `data.js` lengkap untuk semua 35 entri (Departemen dan Topik terisi). Field wajib tidak perlu dilonggarkan. Field Audience boleh kosong untuk 2 entri yang memang tanpa audience di data.

## Diserahkan ke builder

- Modul pendukung facet dan pencarian (Facets biasanya butuh Search API dengan backend database).
- Cara pembatasan Jenis per grup.
- Mekanisme impor.
- Pemetaan tipe schema.org per content type.
- Dukungan sinonim pencarian (`SYNONYMS` di `data.js`). Nilai tambah, bukan syarat.
- Bentuk tampilan halaman daftar dan halaman konten.

## Cara verifikasi

- Tiga kriteria nilai: cek alias URL, cek JSON-LD dan meta description di sumber halaman, coba menyimpan konten dengan field wajib kosong dan Jenis salah grup.
- Role: masuk sebagai tiap role dan jalankan transisi workflow di tabel di atas. Cek anonim tidak melihat Draft.
- Impor: jumlah node 35, jumlah per grup cocok dengan `data.js`.
- Instalasi bersih: modul dari `setup/`, lalu `drush config:import`, lalu skrip seed dan impor, memulihkan semua pengaturan dan konten.
- Auditor: buka form edit, field isi terkunci, hanya status moderasi yang bisa diubah.
