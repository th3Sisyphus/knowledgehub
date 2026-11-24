# Model Konten Knowledge Hub

Dokumen ini menjelaskan content type, field, vocabulary, relasi, metadata, dan role di Drupal. Isinya dibaca dari konfigurasi yang berjalan (`config/`), bukan dari rancangan awal. Sumber kebenaran tetap `config/` dan skrip di `setup/`.

## 1. Content type

Lima content type, satu per grup di IA Milestone 3. Field Jenis memisahkan jenis di dalam grup.

| Content type | Nama mesin | Jenis (vocabulary) | Jumlah node |
|--------------|------------|--------------------|-------------|
| Prosedur | `prosedur` | SOP, Panduan Kerja, Checklist (`jenis_prosedur`) | 8 |
| Referensi | `referensi` | Knowledge Article, Kebijakan, Glossary, Studi Kasus (`jenis_referensi`) | 7 |
| Pembelajaran | `pembelajaran` | Tutorial, Video Tutorial (`jenis_pembelajaran`) | 7 |
| Bantuan | `bantuan` | FAQ, Troubleshooting (`jenis_bantuan`) | 6 |
| Alat Kerja | `alat_kerja` | Template (`jenis_alat_kerja`) | 5 |

Alasan memilih lima tipe, bukan 13 atau satu: field tiap grup berbeda (Difficulty hanya di Pembelajaran, Nomor dokumen hanya di Referensi), sehingga satu tipe generik akan menyimpan banyak field kosong. Tiga belas tipe menggandakan form dan tampilan yang hampir sama. Jenis baru cukup menambah term di vocabulary grupnya.

Dua entri Kontak di prototipe (Bagian Administrasi, Helpdesk IT) bukan konten. Prototipe memakainya sebagai widget kontak. Keduanya tidak diimpor.

## 2. Field

Field dibagi menurut tujuan (lihat bagian 5). Kolom Tipe memakai nama tipe Drupal.

| Field | Nama mesin | Tipe | Wajib | Banyak nilai | Ada di |
|-------|------------|------|-------|--------------|--------|
| Judul | `title` | teks | ya | tidak | semua |
| Jenis | `field_jenis` | referensi term | ya | tidak | semua |
| Departemen | `field_departemen` | referensi term | ya | tidak | semua |
| Topik | `field_topik` | referensi term | ya | tidak | semua |
| Ringkasan | `field_ringkasan` | teks, maks 160 karakter | ya | tidak | semua |
| Isi | `body` | teks panjang | tidak | tidak | semua |
| Tanggal | `field_tanggal` | tanggal | tidak | tidak | semua |
| ID asal (prototipe) | `field_slug_asal` | teks | tidak | tidak | semua |
| Audience | `field_audience` | referensi term | tidak | ya | Prosedur, Referensi, Pembelajaran |
| Difficulty | `field_difficulty` | referensi term | tidak | tidak | Pembelajaran |
| Versi | `field_versi` | teks, maks 20 | tidak | tidak | Prosedur, Referensi, Alat Kerja |
| Nomor dokumen | `field_nomor` | teks, maks 64 | tidak | tidak | Referensi |
| Wajib dipatuhi | `field_wajib` | boolean | tidak | tidak | Prosedur, Referensi |
| Callout | `field_callout` | teks panjang | tidak | tidak | Referensi, Bantuan |
| Kode sumber (inventaris) | `field_sumber` | teks, maks 32 | tidak | tidak | Prosedur, Referensi, Pembelajaran |
| Durasi (detik) | `field_durasi` | bilangan bulat, minimum 1 | tidak | tidak | Pembelajaran |

Aturan validasi:
- Judul, Jenis, Departemen, Topik, dan Ringkasan wajib. Drupal menolak simpan bila ada yang kosong.
- Ringkasan maksimal 160 karakter, satu kalimat.
- Jenis, Departemen, Topik, Audience, dan Difficulty berupa pilihan terkontrol. Form tidak membuat term baru (`auto_create` mati).
- Jenis hanya menerima term dari vocabulary milik grup content type itu. Memilih SOP di content type Bantuan ditolak validasi.
- Audience opsional karena kebijakan dan referensi tertentu berlaku untuk semua audience. Semua 33 node yang diimpor memiliki Departemen dan Topik.
- `field_sumber` menyimpan kode inventaris Milestone 2 (contoh C03), bukan URL.

## 3. Vocabulary terkontrol

Hanya Super Admin dan Admin yang boleh menambah atau mengubah term.

| Vocabulary | Nama mesin | Term | Aturan pakai |
|------------|------------|------|--------------|
| Departemen | `departemen` | HR, Finance, IT, Operations | Satu nilai per konten. Departemen pemilik isi. |
| Audience | `audience` | Pegawai Baru, Pegawai Lama, Supervisor, SME | Boleh lebih dari satu. Siapa yang perlu membaca. |
| Topik | `topik` | Cuti, Keselamatan Kerja, Kinerja, Komunikasi, Pelaporan, Penggunaan Sistem, Perjalanan Dinas, Rekrutmen | Satu nilai per konten. Dasar konten terkait. |
| Difficulty | `difficulty` | Dasar, Menengah, Lanjutan | Hanya konten Pembelajaran. |
| Jenis Prosedur | `jenis_prosedur` | SOP, Panduan Kerja, Checklist | Hanya content type Prosedur. |
| Jenis Referensi | `jenis_referensi` | Knowledge Article, Kebijakan, Glossary, Studi Kasus | Hanya content type Referensi. |
| Jenis Pembelajaran | `jenis_pembelajaran` | Tutorial, Video Tutorial | Hanya content type Pembelajaran. |
| Jenis Bantuan | `jenis_bantuan` | FAQ, Troubleshooting | Hanya content type Bantuan. |
| Jenis Alat Kerja | `jenis_alat_kerja` | Template | Hanya content type Alat Kerja. |

Sinonim pencarian di prototipe (`SYNONYMS` di `data.js`, contoh "perdin" ke "perjalanan dinas") belum dipindahkan ke Drupal. Itu fitur tambahan, bukan syarat.

## 4. Relasi

| Dari | Ke | Cara | Dipakai untuk |
|------|----|------|---------------|
| Konten | Jenis, Departemen, Topik, Audience, Difficulty | referensi term | facet, filter, label di daftar |
| Konten | Konten lain | Topik yang sama | blok Konten terkait di halaman konten (maks 5, hanya yang terbit) |

Facet per grup mengikuti IA Milestone 3.

| Grup | Facet |
|------|-------|
| Prosedur, Referensi | Departemen, Audience, Topik, Jenis |
| Pembelajaran | Audience, Difficulty, Topik, Jenis |
| Bantuan | Departemen, Topik, Jenis |
| Alat Kerja | Departemen |

## 5. Metadata

| Jenis metadata | Isi | Field atau mekanisme |
|----------------|-----|----------------------|
| Deskriptif | apa isi konten | Judul, Ringkasan (jadi `meta description`), Jenis, Topik, Departemen, Audience, Difficulty |
| Struktural | bagaimana konten terhubung dan ditampilkan | Content type, Jenis per grup, alias URL, menu utama, konten terkait |
| Administratif | siapa, kapan, status | Tanggal, Versi, Nomor dokumen, Wajib dipatuhi, status moderasi, revisi, kode sumber, ID asal |

Metadata mesin:
- Meta description memakai `[node:field_ringkasan]` di semua content type.
- JSON-LD schema.org lewat Schema.org Metatag.

| Content type | `@type` | Alasan |
|--------------|---------|--------|
| Prosedur | WebPage | HowTo butuh data langkah terstruktur yang belum ada di konten |
| Referensi | Article | judul, ringkasan, tanggal terbit dan ubah |
| Pembelajaran | Course | nama dan deskripsi |
| Bantuan | WebPage | FAQPage atau QAPage butuh pasangan tanya-jawab terstruktur yang belum ada |
| Alat Kerja | WebPage | dokumen siap isi |

## 6. Alias URL (Pathauto)

| Objek | Pola | Contoh |
|-------|------|--------|
| Prosedur | `/prosedur/[judul]` | `/prosedur/sop-perjalanan-dinas` |
| Referensi | `/referensi/[judul]` | `/referensi/kebijakan-cuti-tahunan-pegawai-kontrak` |
| Pembelajaran | `/pembelajaran/[judul]` | `/pembelajaran/cascading-kinerja` |
| Bantuan | `/bantuan/[judul]` | |
| Alat Kerja | `/alat-kerja/[judul]` | |
| Term | `/[vocabulary]/[nama]` | `/topik/cuti` |

Dua judul sama tidak bentrok: Pathauto menambah akhiran angka.

## 7. Role, workflow, dan akses

| Role | Nama mesin | Hak |
|------|------------|-----|
| Super Admin | `super_admin` | semua akses |
| Admin | `kh_admin` | buat dan edit konten milik sendiri, kelola term, kirim ke review. Tidak bisa Publish dan tidak bisa mengubah konfigurasi |
| Auditor | `auditor` | lihat semua status, setujui, kembalikan, arsipkan, pulihkan. Isi konten terkunci di form |
| Pembaca | anonim | hanya konten Published |

Workflow `kh_editorial` pada semua content type:

| Transisi | Dari | Ke | Pemegang |
|----------|------|----|----------|
| Simpan Draft | Draft | Draft | Admin |
| Kirim ke Review | Draft | In Review | Admin |
| Revisi konten terbit | Published | Draft | Admin |
| Setujui (Publish) | In Review | Published | Auditor |
| Kembalikan ke Draft | In Review | Draft | Auditor |
| Arsipkan dari Review | In Review | Archived | Auditor |
| Arsipkan | Published | Archived | Auditor |
| Pulihkan ke Draft | Archived | Draft | Auditor |

Catatan perilaku:
- Auditor tidak punya transisi dari Draft, jadi Auditor tidak bisa membuka form edit Draft. Auditor tetap melihat Draft. Auditor bekerja pada In Review, Published, dan Archived.
- Auditor membutuhkan izin edit supaya bisa memakai transisi. Modul `kh_workflow` mengunci semua field isi di form Auditor. Hanya status moderasi dan catatan revisi yang terbuka.
- Admin hanya melihat dan mengedit konten miliknya sendiri yang berstatus Draft, ditambah semua konten Published.
- Anonim tidak melihat Draft, In Review, dan Archived.

Akun demo (lokal saja, sandi `demo`): `demo_superadmin`, `demo_admin`, `demo_auditor`. Akun `admin` / `admin` dibuat oleh installer.

## 8. Yang belum ada

- Sinonim pencarian.
- Halaman beranda khusus. Beranda memakai halaman Cari (semua konten dan pencarian).
- Konten Kontak dari prototipe.
- Facet untuk Departemen di Pembelajaran. Mengikuti IA Milestone 3, grup ini tidak memakainya.
