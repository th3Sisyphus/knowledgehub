# Knowledge Hub Drupal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pindahkan prototipe Knowledge Hub ke Drupal 11 dengan 5 content type, vocabulary terkontrol, 3 role, workflow moderasi, facet, alias URL, dan JSON-LD.

**Architecture:** Instalasi Drupal di Docker (sudah ada). Struktur dibuat dengan skrip PHP idempoten (`drush php:script`) lalu diekspor ke `config/`. Taxonomy dan konten diisi skrip seed/impor. Satu modul custom kecil mengunci form edit untuk Auditor. Pencarian dan facet memakai Search API (backend database) dan Facets.

**Tech Stack:** Drupal 11, MariaDB 11, drush, Pathauto, Token, Schema.org Metatag, Search API + search_api_db, Facets, Content Moderation (inti), Node.js di host (konversi `data.js` ke JSON).

**Spec:** `docs/superpowers/specs/2026-11-16-drupal-knowledge-hub-design.md`

## Global Constraints

- Drupal 11 (`drupal:11-apache`), MariaDB 11. Mesin pengguna M3 Air (arm64).
- Pembaca anonim tanpa login. Konten anonim hanya Published (Archived tidak tampil).
- Tiga role: Super Admin, Admin, Auditor. Mesin: `super_admin`, `kh_admin`, `auditor`.
- Workflow `kh_editorial`: Draft, In Review, Published, Archived.
- Lima content type: `prosedur`, `referensi`, `pembelajaran`, `bantuan`, `alat_kerja`.
- Jenis: lima vocabulary `jenis_prosedur`, `jenis_referensi`, `jenis_pembelajaran`, `jenis_bantuan`, `jenis_alat_kerja`. Tiap content type hanya mereferensikan vocabulary jenis miliknya.
- Vocabulary lain: `departemen`, `audience`, `topik`, `difficulty`. Term hanya bisa dibuat Super Admin dan Admin, tidak dari form konten (tanpa auto-create).
- Repo memuat `config/`, `setup/`, `modules/`, `docs/`. Kode Drupal tetap di volume Docker.
- Tanpa tema custom (Olivero). Tanpa akun pembaca. Tanpa progres belajar.
- Jangan commit atau push sebelum pengguna meminta. Langkah "Commit" di bawah hanya dijalankan bila pengguna sudah mengizinkan commit. Jangan tambah trailer `Co-Authored-By` (aturan pengguna).
- Teks UI, label, dan dokumen berbahasa Indonesia. Nama mesin dan kode bahasa Inggris atau Indonesia sesuai tabel di atas.

## Review Focus

1. Anonim membuka node Draft, In Review, atau Archived lewat URL langsung: harus 403 (bukan konten).
2. Admin menyimpan node dengan Departemen, Topik, atau Ringkasan kosong: form menolak.
3. Dua judul sama dalam satu content type: alias tidak bentrok (Pathauto menambah akhiran) dan keduanya tetap terbuka.
4. Pencarian kata tanpa hasil atau facet tanpa hasil: halaman tampil dengan pesan "tidak ada hasil", bukan error.
5. Dua entri tanpa audience di `data.js` (`aud: []`): impor berhasil, halaman tampil tanpa error, JSON-LD tetap valid.

---

## File Structure

- `setup/modules.sh`: `composer require` dan `drush en` semua modul. 
- `setup/model.php`: buat vocabulary, content type, field, form display, view display, workflow. Idempoten.
- `setup/seed_terms.php`: isi term dari daftar tetap di file. Idempoten.
- `setup/roles.php`: buat role, izin, transisi, akun demo. Idempoten.
- `setup/export_catalog.mjs`: baca `../Mock_Web_Knowledge_Hub/data.js`, tulis `setup/catalog.json`.
- `setup/import_content.php`: baca `catalog.json`, buat 35 node. Idempoten (kunci: `field_slug_asal`).
- `setup/discovery.php`: Search API index, Views per grup, facet, menu utama, blok konten terkait, pola Pathauto, Metatag.
- `setup/verify.sh`: semua pemeriksaan, tumbuh per task.
- `modules/kh_workflow/`: modul custom pengunci form Auditor.
- `docker-compose.yml`: tambah mount `./modules:/opt/drupal/web/modules/custom` dan `./setup:/opt/drupal/setup`.
- `docs/content-model.md`: dokumentasi model untuk Milestone 4.
- `config/`: hasil `drush config:export`.

Semua skrip PHP dijalankan dengan:
`docker compose exec drupal vendor/bin/drush php:script /opt/drupal/setup/<file>.php`

---

### Task 1: Drupal terpasang dan modul aktif

**Files:**
- Modify: `docker-compose.yml` (volume `./modules`, `./setup`)
- Create: `setup/modules.sh`, `setup/verify.sh`, `modules/.gitkeep`
- Modify: `README.md` (langkah pemasangan)

**Interfaces:**
- Produces: Drupal berjalan di `http://localhost:8080`, admin `admin`/`admin` (lokal saja), `config_sync_directory = /opt/drupal/config`, drush tersedia sebagai `vendor/bin/drush`. `setup/verify.sh` dengan fungsi `check <nama> <perintah>` yang mencetak PASS/FAIL dan keluar non-nol bila ada FAIL.

- [ ] **Step 1: Tulis `setup/verify.sh` dengan pemeriksaan pertama (gagal dulu)**

Pemeriksaan: `curl -s -o /dev/null -w '%{http_code}' localhost:8080` sama dengan 200; `drush pm:list --status=enabled` memuat `pathauto`, `schema_metatag`, `facets`, `search_api_db`, `content_moderation`, `token`.

- [ ] **Step 2: Jalankan `bash setup/verify.sh`. Expected: FAIL (Drupal belum terpasang).**

- [ ] **Step 3: Tambah dua mount ke `docker-compose.yml`, jalankan `docker compose up -d`.**

- [ ] **Step 4: Pasang Drupal lewat drush, bukan installer web.** Perintah `drush site:install standard --db-url=mysql://drupal:drupal@db/drupal --site-name="Knowledge Hub" --account-name=admin --account-pass=admin -y`. Tulis perintah ini ke `setup/modules.sh` bersama `composer require drush/drush drupal/pathauto drupal/token drupal/schema_metatag drupal/facets drupal/search_api` dan `drush en ... -y` (termasuk `content_moderation`, `workflows`, `search_api_db`, `schema_article`, `schema_web_page`, `schema_faq_page` bila ada, `kh_workflow` ditambah di Task 5). Tambah `$settings['config_sync_directory'] = '/opt/drupal/config';` ke `settings.php` bila belum ada.

- [ ] **Step 5: Jalankan `bash setup/verify.sh`. Expected: semua PASS.**

- [ ] **Step 6: Perbarui README (satu paragraf: `bash setup/modules.sh` menggantikan installer web). Commit (bila diizinkan).**

---

### Task 2: Vocabulary dan term

**Files:**
- Create: `setup/seed_terms.php`
- Modify: `setup/verify.sh`

**Interfaces:**
- Produces: vocabulary `departemen`, `audience`, `topik`, `difficulty`, `jenis_prosedur`, `jenis_referensi`, `jenis_pembelajaran`, `jenis_bantuan`, `jenis_alat_kerja` beserta term sesuai spec. Nama term persis seperti di spec (contoh `Perjalanan Dinas`, `Pegawai Baru`).

- [ ] **Step 1: Tambah pemeriksaan di `verify.sh`:** jumlah term per vocabulary (departemen 4, audience 4, topik 8, difficulty 3, jenis_prosedur 3, jenis_referensi 4, jenis_pembelajaran 2, jenis_bantuan 2, jenis_alat_kerja 1). Gunakan `drush php:eval` dengan `entity_type.manager`. Jalankan, expected FAIL.

- [ ] **Step 2: Implementasi `setup/seed_terms.php`.** Satu array asosiatif vocabulary ke daftar nama. Buat vocabulary bila belum ada, buat term bila nama belum ada di vocabulary itu. Skrip harus aman dijalankan dua kali.

- [ ] **Step 3: Jalankan skrip dua kali, lalu `verify.sh`. Expected: PASS dan jumlah term tidak bertambah di kali kedua.**

- [ ] **Step 4: `drush config:export -y`. Commit (bila diizinkan).**

---

### Task 3: Content type dan field

**Files:**
- Create: `setup/model.php`
- Modify: `setup/verify.sh`

**Interfaces:**
- Consumes: vocabulary dari Task 2.
- Produces: lima content type dengan field (nama mesin): `field_jenis` (wajib, ref ke vocabulary jenis milik tipe itu, tanpa auto-create), `field_departemen` (wajib, ref `departemen`), `field_topik` (wajib, ref `topik`), `field_ringkasan` (wajib, teks polos, maks 160 karakter), `body`, `field_tanggal` (date), `field_slug_asal` (string, id asal di `data.js`, dipakai impor). Khusus: `field_audience` (multi, ref `audience`) di `prosedur`, `referensi`, `pembelajaran`; `field_difficulty` (ref `difficulty`) di `pembelajaran`; `field_versi`, `field_nomor`, `field_wajib` (boolean), `field_callout`, `field_sumber` (link), `field_durasi` (string) hanya di tipe yang memakainya di `data.js` (cek tiap kolom per grup di `data.js` dan catat pemetaan di `docs/content-model.md`).
- Moderasi diaktifkan pada kelima tipe oleh Task 5, bukan di task ini.

- [ ] **Step 1: Tambah pemeriksaan di `verify.sh`:** (a) lima content type ada; (b) `field_jenis` di `bantuan` hanya mereferensikan `jenis_bantuan` (baca `field.field.node.bantuan.field_jenis` dengan `drush config:get`, nilai `settings.handler_settings.target_bundles` sama dengan `{jenis_bantuan: jenis_bantuan}`); (c) `field_ringkasan`, `field_jenis`, `field_departemen`, `field_topik` berstatus `required: true` di semua tipe; (d) `field_difficulty` hanya ada di `pembelajaran`. Expected FAIL.

- [ ] **Step 2: Implementasi `setup/model.php`.** Fungsi pembantu lokal `ensure_field(string $bundle, string $name, string $type, array $storage, array $field, string $widget)` agar tidak berulang. Atur form display (widget select untuk referensi, teks panjang untuk `body`) dan view display (label di atas, urutan wajar). Idempoten.

- [ ] **Step 3: Jalankan, lalu `verify.sh`. Expected: PASS.**

- [ ] **Step 4: Uji manual cepat lewat `drush php:eval`:** buat node `bantuan` tanpa `field_ringkasan`, validasi lewat `$node->validate()`; assert ada pelanggaran. Tambahkan sebagai pemeriksaan di `verify.sh` (menutup Review Focus 2).

- [ ] **Step 5: `drush config:export -y`. Commit (bila diizinkan).**

---

### Task 4: Impor 35 konten

**Files:**
- Create: `setup/export_catalog.mjs`, `setup/import_content.php`, `setup/catalog.json` (hasil)
- Modify: `setup/verify.sh`

**Interfaces:**
- Consumes: content type dan vocabulary Task 2 dan 3. Struktur entri `CATALOG` di `data.js`: `id, group, type, title, dept, aud[], topic, diff?, date, desc, body[]?, version?, number?, wajib?, callout?, source?, duration?, status?`.
- Produces: 35 node. Pemetaan: `group` ke content type, `type` ke term Jenis, `dept`/`topic`/`diff`/`aud` ke term, `desc` ke `field_ringkasan`, `body[]` ke `body` (satu `<p>` per item; builder boleh memakai `<ol>` untuk SOP bila terbaca lebih baik), `id` ke `field_slug_asal`. `status: 'Draft'` menjadi Draft, selain itu Published (status moderasi diterapkan setelah Task 5; sebelum itu `status` node 0/1).

- [ ] **Step 1: Tambah pemeriksaan di `verify.sh`:** jumlah node total 35; jumlah per content type sama dengan hitungan per `group` di `data.js` (prosedur 8, referensi 7, pembelajaran 7, bantuan 6, alat_kerja 5 — hitung ulang dari `catalog.json`, jangan percaya angka ini); node `sop-perjalanan-dinas` punya `field_departemen` HR. Expected FAIL.

- [ ] **Step 2: `setup/export_catalog.mjs`.** Muat `data.js` di dalam `vm` (script klasik dengan `const`), ambil `CATALOG`, tulis `setup/catalog.json`. Jalankan `node setup/export_catalog.mjs`. Periksa 35 entri.

- [ ] **Step 3: `setup/import_content.php`.** Cari node dengan `field_slug_asal` sama, perbarui bila ada, buat bila belum. Term yang tidak ditemukan menghentikan skrip dengan pesan jelas (jangan membuat term).

- [ ] **Step 4: Jalankan dua kali. `verify.sh`. Expected: PASS, jumlah node tetap 35.**

- [ ] **Step 5: Tambah pemeriksaan:** dua entri `aud: []` terimpor tanpa audience dan halaman node mereka mengembalikan 200 (Review Focus 5). Commit `setup/` (bila diizinkan).

---

### Task 5: Role, workflow, izin, modul pengunci Auditor

**Files:**
- Create: `setup/roles.php`, `modules/kh_workflow/kh_workflow.info.yml`, `modules/kh_workflow/kh_workflow.module`
- Modify: `setup/modules.sh` (aktifkan `kh_workflow`), `setup/verify.sh`

**Interfaces:**
- Produces: role `super_admin`, `kh_admin`, `auditor`. Workflow `kh_editorial` (state `draft`, `in_review`, `published`, `archived`) pada lima content type. Transisi dan pemegangnya sesuai spec: `draft->in_review` (kh_admin), `published->draft` (kh_admin; lalu kirim ulang ke review), `in_review->published`, `in_review->draft`, `in_review->archived`, `published->archived`, `archived->draft` (auditor). Super Admin: semua. Akun demo: `demo_superadmin`, `demo_admin`, `demo_auditor` (sandi lokal `demo`). `hook_form_node_form_alter` pada `kh_workflow` menonaktifkan semua elemen form kecuali `moderation_state` dan `revision_log` bila pengguna punya role `auditor` dan bukan `super_admin`.
- Akses node: Admin edit hanya node miliknya yang berstatus Draft, lihat semua Published. Auditor lihat semua status. Anonim hanya Published (izin `view any unpublished` tidak diberikan).

- [ ] **Step 1: Tambah pemeriksaan di `verify.sh`** (setelah login via `drush user:login`/cookie, atau lewat `drush php:eval` dengan `$node->access('view', $account)`): (a) anonim `access('view')` salah untuk satu node Draft dan satu node Archived, benar untuk Published; (b) `demo_admin` tidak punya izin `use editorial transition publish`; (c) `demo_auditor` punya `use editorial transition publish`; (d) `demo_auditor` tidak punya izin `create` pada semua tipe; (e) form edit node untuk auditor: ambil array form lewat `entity.form_builder`, assert `title` `#disabled` benar dan `moderation_state` tidak. Expected FAIL.

- [ ] **Step 2: Implementasi `setup/roles.php` dan modul `kh_workflow`.** Terapkan moderasi ke lima tipe. Pasang `status` node dari `data.js` (`sop-lembur` jadi Draft): jalankan ulang impor Task 4 setelah workflow aktif, atau set `moderation_state` di skrip impor.

- [ ] **Step 3: Jalankan, `verify.sh`. Expected: PASS.**

- [ ] **Step 4: Tambah pemeriksaan HTTP anonim:** `curl` alias node Draft (`sop-lembur`) mengembalikan 403; node Published mengembalikan 200 (Review Focus 1).

- [ ] **Step 5: `drush config:export -y`. Commit (bila diizinkan).**

---

### Task 6: Alias URL dan metadata mesin

**Files:**
- Create: `setup/discovery.php` (bagian Pathauto dan Metatag; bagian lain di Task 7)
- Modify: `setup/verify.sh`

**Interfaces:**
- Produces: pola Pathauto: `/[node:content-type]/[node:title]` per content type dan `/[term:vocabulary]/[term:name]` per term. Metatag default untuk node: `description` = `[node:field_ringkasan]`, JSON-LD schema.org per content type: `prosedur` HowTo atau WebPage (builder pilih yang tersedia di `schema_metatag`), `referensi` Article, `pembelajaran` Course atau LearningResource bila ada (fallback Article), `bantuan` FAQPage hanya bila konten memuat pasangan tanya-jawab (fallback WebPage), `alat_kerja` WebPage. Catat pilihan akhir di `docs/content-model.md`.

- [ ] **Step 1: Tambah pemeriksaan di `verify.sh`:** (a) alias `/prosedur/sop-perjalanan-dinas` mengembalikan 200; (b) HTML node memuat `<meta name="description"` dengan isi sama dengan `field_ringkasan`; (c) HTML memuat blok `<script type="application/ld+json">` yang bisa di-parse `python -c 'import json,sys'` dan punya kunci `@context` dan `@type`; (d) dua node judul sama (buat sementara lewat `drush php:eval`, hapus setelahnya) mendapat alias berbeda dan keduanya 200 (Review Focus 3). Expected FAIL.

- [ ] **Step 2: Implementasi bagian Pathauto dan Metatag.** Lalu jalankan `drush pathauto:aliases-generate all` (atau `drush pathauto:aliases-generate create all`) untuk node dan term yang sudah ada.

- [ ] **Step 3: `verify.sh`. Expected: PASS.**

- [ ] **Step 4: `drush config:export -y`. Commit (bila diizinkan).**

---

### Task 7: Pencarian, facet, navigasi, konten terkait

**Files:**
- Modify: `setup/discovery.php` (tambah bagian pencarian), `setup/verify.sh`

**Interfaces:**
- Consumes: node dan alias dari task sebelumnya.
- Produces: index Search API `kh_index` (backend database, semua node dari lima tipe, field fulltext: judul, body, ringkasan; field filter: departemen, audience, topik, difficulty, jenis, content type). Lima Views halaman (satu per grup) di path `/prosedur`, `/referensi`, `/pembelajaran`, `/bantuan`, `/alat-kerja` dengan facet blok sesuai spec: Prosedur dan Referensi (Departemen, Audience, Topik, Jenis), Pembelajaran (Audience, Difficulty, Topik, Jenis), Bantuan (Departemen, Topik, Jenis), Alat Kerja (Departemen). Halaman pencarian `/cari` dengan teks bebas. Menu utama berisi lima grup dan Pencarian (urutan: Prosedur, Referensi, Pembelajaran, Bantuan, Alat Kerja, Cari). Blok "Konten terkait" di halaman node: Views berfilter Topik node yang sedang dibuka, mengecualikan node itu sendiri, maksimal 5. Hanya Published masuk index.

- [ ] **Step 1: Tambah pemeriksaan di `verify.sh`:** (a) `/prosedur` mengembalikan 200 dan memuat judul `SOP Perjalanan Dinas`; (b) `/prosedur` tidak memuat `SOP Lembur` (Draft); (c) `/pembelajaran` memuat facet `Difficulty`, `/bantuan` tidak; (d) `/alat-kerja` hanya memuat facet Departemen; (e) `/cari?search_api_fulltext=cuti` memuat `SOP Pengajuan Cuti`; (f) `/cari?search_api_fulltext=zzzzqqq` mengembalikan 200 dan memuat pesan "tidak ada hasil" (Review Focus 4); (g) halaman `/prosedur/sop-perjalanan-dinas` memuat blok `Konten terkait` dengan minimal satu tautan; (h) menu utama memuat lima tautan grup. Expected FAIL.

- [ ] **Step 2: Implementasi bagian pencarian di `setup/discovery.php`.** Buat index, indeks ulang (`drush search-api:index`), Views, facet, blok, menu. Teks "tidak ada hasil" diatur lewat area kosong Views. Idempoten.

- [ ] **Step 3: `verify.sh`. Expected: PASS.**

- [ ] **Step 4: Buka halaman utama di browser pengguna.** Ubah blok beranda atau front page ke `/` dengan ringkasan lima grup (kartu atau daftar) bila `/` masih kosong. Opsional: sinonim `SYNONYMS` hanya bila murah (jika tidak, catat sebagai tidak dikerjakan di `docs/content-model.md`).

- [ ] **Step 5: `drush config:export -y`. Commit (bila diizinkan).**

---

### Task 8: Dokumentasi model dan pemulihan bersih

**Files:**
- Create: `docs/content-model.md`
- Modify: `README.md`, `setup/verify.sh`

**Interfaces:**
- Produces: `docs/content-model.md` dengan: tabel content type (nama, tujuan, grup), tabel field per tipe (nama mesin, tipe data, wajib, validasi), tabel vocabulary dan term beserta aturan penggunaan, relasi (Jenis, Departemen, Topik, konten terkait via Topik), jenis metadata (deskriptif, struktural, administratif) dengan pemetaan field, tabel role dan transisi. README memuat urutan pemulihan bersih.

- [ ] **Step 1: Tulis dokumen dari konfigurasi nyata** (`drush config:get` atau baca `config/`), bukan dari ingatan. Setiap tabel harus cocok dengan config.

- [ ] **Step 2: Uji pemulihan bersih.** `docker compose down -v`, `docker compose up -d`, `bash setup/modules.sh`, `drush config:import -y`, `seed_terms.php`, `import_content.php`, `bash setup/verify.sh`. Expected: semua PASS tanpa langkah manual. Bila `config:import` gagal karena UUID situs berbeda, atur UUID situs dari `config/system.site.yml` dengan `drush config:set system.site uuid <uuid>` di `modules.sh` sebelum impor.

- [ ] **Step 3: Perbarui README (urutan pemulihan, login demo, daftar tiga kriteria nilai dan cara memeriksanya). Commit (bila diizinkan).**

---

## Self-review

- Cakupan spec: role dan workflow (Task 5), content type dan field (3), vocabulary (2), navigasi, facet, terkait, pencarian (7), Pathauto dan JSON-LD (6), impor (4), dokumen model dan pemulihan (8), Auditor longgar (5), repo `setup/` dan `modules/` (File Structure). Sinonim pencarian ditandai opsional di spec dan di Task 7.
- Spec menyebut repo memuat `config/`, `setup/`, `docs/`. Plan menambah `modules/` (modul custom pengunci Auditor). Perbarui baris itu di spec saat eksekusi.
- Tipe konsisten: nama mesin role, workflow, field, dan vocabulary sama di Global Constraints dan task.
- Angka jumlah node per tipe di Task 4 adalah perkiraan. `verify.sh` harus menghitung dari `catalog.json`.
