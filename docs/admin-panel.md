# Panduan Admin Panel

Panduan ini untuk akun lokal Knowledge Hub. Akun dan sandi di bawah hanya untuk pengembangan di komputer sendiri. Jangan dipakai di server publik.

## Masuk

Buka http://127.0.0.1:8080/user/login

Pakai `127.0.0.1`, bukan `localhost`. Di beberapa mesin `localhost` mengarah ke IPv6 dan port 8080 bisa dipegang program lain, sehingga halaman memberi 404. Docker hanya mendengar di `127.0.0.1`.

| Akun | Sandi | Role |
|------|-------|------|
| `demo_superadmin` | `demo` | Super Admin |
| `demo_admin` | `demo` | Admin |
| `demo_auditor` | `demo` | Auditor |
| `admin` | `admin` | akun pembuat situs, akses penuh |

Tampilan admin memakai tema Claro bawaan Drupal. Tampilan publik memakai tema `kh_theme`.

## Alamat penting

| Alamat | Fungsi | Super Admin | Admin | Auditor |
|--------|--------|:-----------:|:-----:|:-------:|
| `/admin/content` | daftar semua konten | ya | ya | ya |
| `/node/add` | tambah konten | ya | ya | tidak |
| `/admin/content/moderated` | antrian moderasi | ya | tidak | ya |
| `/admin/structure/taxonomy` | kelola term (Departemen, Topik, Jenis, dan lainnya) | ya | ya | tidak |
| `/admin/people` | kelola akun dan role | ya | tidak | tidak |
| `/admin/modules` | modul | ya | tidak | tidak |

Tabel ini diuji dengan login otomatis ke tiap alamat. Kode HTTP 403 muncul pada sel "tidak".

## Halaman profil

Setelah masuk, Drupal membuka halaman profil akun (`/user/ID`). Halamannya berisi inisial, role, tanggal bergabung, dan kartu pintasan. Kartu hanya muncul bila akun itu punya izinnya: Admin melihat Tambah konten, Kelola konten, dan Kelola term. Auditor melihat Kelola konten dan Antrian moderasi. Super Admin melihat semuanya, termasuk Kelola akun. Semua role melihat Sunting profil dan tombol Keluar.

Pintasan hanya tampil di profil milik sendiri. Pembaca lain yang membuka profil seseorang tidak melihat pintasan.

## Hak tiap role

**Super Admin.** Semua akses, termasuk modul, konfigurasi, role, dan workflow. Semua transisi workflow boleh.

**Admin.**
- Membuat konten di lima content type.
- Menyunting konten miliknya sendiri. Admin melihat semua konten Published, tetapi hanya menyunting miliknya.
- Menambah dan mengubah term taxonomy.
- Mengirim konten ke review, dan menarik konten terbit kembali ke Draft untuk direvisi.
- Tidak bisa Publish, tidak bisa mengubah konfigurasi, dan tidak bisa membuka `/admin/people` atau `/admin/modules`.

**Auditor.**
- Melihat semua konten di semua status, termasuk Draft.
- Menyetujui konten (In Review ke Published), mengembalikannya ke Draft, mengarsipkan, dan memulihkan konten arsip ke Draft.
- Form edit mengunci semua field isi. Hanya status moderasi dan catatan revisi yang bisa diubah.
- Tidak bisa membuat konten dan tidak bisa mengelola taxonomy.
- Tidak punya transisi dari Draft, jadi Auditor tidak bisa membuka form edit konten yang masih Draft.

## Workflow konten

Status: Draft, In Review, Published, Archived.

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

Pembaca tanpa login hanya melihat konten Published. Draft, In Review, dan Archived memberi 403.

## Menambah konten

1. Masuk sebagai `demo_admin`.
2. Buka `/node/add` dan pilih content type sesuai grup (Prosedur, Referensi, Pembelajaran, Bantuan, atau Alat Kerja).
3. Isi Judul, Jenis, Departemen, Topik, dan Ringkasan. Kelima field ini wajib. Ringkasan maksimal 160 karakter dan dipakai sebagai meta description.
4. Pilihan Jenis hanya menampilkan jenis milik content type itu. Contoh: di Bantuan hanya FAQ dan Troubleshooting.
5. Pilih status "In Review" lalu simpan.
6. Masuk sebagai `demo_auditor`, buka `/admin/content/moderated`, lalu setujui konten itu.

Alamat konten dibuat otomatis dari judul, contoh `/prosedur/sop-perjalanan-dinas`. Bila judul diganti, alamat lama tetap hidup.

## Yang belum dibuat

- Link ke admin di header situs publik. Footer punya "Masuk Admin" ke `/user/login`.
- Form "Tambah Konten" khusus seperti di prototipe HTML. Drupal memakai form bawaannya.
- Langkah 5 dan 6 di atas belum diuji lewat klik di browser. Yang sudah diuji: izin tiap role, akses tiap alamat, dan matriks transisi lewat skrip (`bash setup/verify.sh`).
