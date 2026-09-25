# UI_SPEC — Kelola Karyawan

**Status:** READY FOR IMPLEMENTATION
**Last Updated:** 2026-09-20 — konfigurasi Role pada master Jabatan

Visual references:
- `references/data_karyawan.png`
- `references/departemen_jabatan.png`

Desain Tambah/Edit/Detail belum memiliki screenshot khusus. Agent harus menurunkannya dari design language dua reference di atas tanpa membuat style baru.

---

# 1. Shared Layout

Gunakan `InternalDashboardLayout`/layout internal existing.

Harus konsisten:
- sidebar;
- top navigation;
- primary blue;
- typography;
- card radius;
- border;
- spacing;
- button;
- icon style;
- responsive behavior.

Active navigation:
- Data Karyawan pada list/detail/tambah/edit.
- Master Organisasi (Jabatan, Departemen, dan Penempatan) pada halaman master.

---

# 2. Data Karyawan — List

Visual utama mengikuti `data_karyawan.png`.

## Semua Role

List/read-only view menampilkan 12 atribut umum:

- Nama
- Jenis Kelamin
- Agama
- Tanggal Lahir
- Tempat Lahir
- Pendidikan
- Jabatan
- Departemen
- Status Kerja
- Status Keaktifan
- Tanggal Masuk
- Tanggal Keluar

Jika seluruh kolom tidak muat, tabel menggunakan horizontal scroll yang disengaja dan tetap dapat dioperasikan pada desktop, tablet, dan mobile.

## Super Admin

Tambahan UI:
- heading + deskripsi;
- tombol `Tambah Karyawan`;
- search/filter sesuai PRD;
- tabel;
- pagination;
- action Detail/Edit/Delete-or-Deactivate sesuai rule.
- NIK boleh dipertahankan sebagai kolom tambahan; field sensitif lain tersedia pada Detail/Edit/Create, bukan wajib di tabel list.

## Admin
- read-only;
- company-wide;
- tidak ada tombol mutation;
- seluruh 12 atribut umum terlihat;
- NIK dan field sensitif tidak ada pada payload/tabel.

## User
- read-only dan company-wide;
- dapat menggunakan list multi-row/search/filter;
- seluruh 12 atribut umum terlihat pada setiap record;
- tidak menerima field sensitif.

---

# 3. Search / Filter / Pagination

## Super Admin
Search:
- Nama
- NIK

Filter:
- Jabatan
- Departemen
- Status Keaktifan
- Status Kerja

## Admin
Search:
- Nama

Filter:
- Jabatan
- Departemen
- Status Keaktifan
- Status Kerja

## User
Search:
- Nama

Filter:
- Jabatan
- Departemen
- Status Keaktifan
- Status Kerja

Admin/User tidak dapat mencari berdasarkan NIK karena NIK merupakan field sensitif.

## Pagination
- server-side;
- 15 rows/page;
- preserve query/filter saat pindah page;
- default sort Nama A–Z.

---

# 4. Tambah Karyawan

Agent membuat halaman berdasarkan design language references.

Gunakan halaman penuh, bukan modal kecil, karena field banyak.

Kelompok form:

## Data Pribadi
- Nama
- NIK
- Jenis Kelamin
- Tempat Lahir
- Tanggal Lahir
- Agama
- Status Perkawinan
- Pendidikan
- Alamat
- No. HP

## Data Pekerjaan
- Jabatan
- Departemen
- Status Kerja
- Status Keaktifan
- Tanggal Masuk
- Tanggal Keluar

## Dokumen
- Foto KTP

Behavior:
- submit disabled saat processing;
- backend validation;
- field error inline;
- input tidak hilang saat invalid;
- dropdown master dari backend;
- departemen boleh kosong;
- tanggal keluar boleh kosong;
- setelah sukses → Detail Karyawan.

Jangan tambahkan username/PIN/role pada form.

---

# 5. Edit Karyawan

Visual diturunkan dari Tambah Karyawan.

Perbedaan:
- prefilled;
- Foto KTP existing ditampilkan hanya kepada Super Admin;
- upload baru opsional;
- Save Changes;
- Cancel kembali ke Detail;
- setelah sukses → Detail Karyawan.

---

# 6. Detail Karyawan

Agent membuat halaman berdasarkan reference.

Gunakan card/section:
1. Data Pribadi
2. Data Pekerjaan
3. Kontak
4. Dokumen
5. Akun Sistem

Super Admin:
- semua data;
- preview Foto KTP;
- tombol Edit;
- action Nonaktifkan/Karyawan Keluar;
- conditional Delete bila eligible.
- melihat section Akun Sistem;
- membuat akun jika belum tersedia;
- melihat username, role, status akun, dan status wajib ganti PIN;
- mengaktifkan/nonaktifkan akun sesuai status Karyawan.

Admin:
- 12 field umum dengan company-wide row scope;
- tanpa mutation.

User:
- 12 field umum dengan company-wide row scope;
- tanpa mutation.

Admin/User tidak menerima account-management payload atau action.

## 6.1 Akun Sistem — Belum Memiliki Akun

Tampilkan kepada Super Admin:

- status `Belum memiliki akun`;
- role hasil mapping Jabatan bila tersedia;
- pesan `Role untuk jabatan ini belum ditentukan` bila Jabatan belum memiliki mapping;
- tombol `Buat Akun` hanya bila role dapat ditentukan.

Create Account menggunakan modal/compact form pada Detail Karyawan dengan:

- identitas Nama dan Jabatan read-only;
- Role read-only dari backend;
- Username;
- PIN Awal 6 digit;
- Konfirmasi PIN;
- tombol Batal dan Buat Akun.

Browser tidak mengirim `role_id`. Error duplikasi username, akun kedua, PIN, dan Jabatan tanpa mapping ditampilkan inline/di area form tanpa menghilangkan input.

## 6.2 Akun Sistem — Sudah Memiliki Akun

Tampilkan kepada Super Admin:

- Username;
- Role;
- status Aktif/Nonaktif;
- status `Wajib ganti PIN` atau `PIN sudah diganti`.

Action minimum:

- Nonaktifkan Akun untuk akun aktif;
- Aktifkan Akun untuk akun nonaktif hanya bila Karyawan aktif.

Jika Karyawan nonaktif, tombol Aktivasi disabled dan UI menjelaskan bahwa master Karyawan harus aktif. Tidak ada action lihat PIN, lihat hash, atau delete akun.

Role akun existing tetap berasal dari relasi akun. Karyawan tanpa akun menampilkan Role dari konfigurasi Jabatan sebagai calon role dan tidak dibuatkan akun otomatis. Perubahan Jabatan atau Role Jabatan tidak menyinkronkan role akun existing secara otomatis.

---

# 6.3 Ganti PIN Pertama

Halaman auth sederhana yang konsisten dengan Login:

- heading `Ganti PIN`;
- informasi bahwa PIN sementara harus diganti;
- PIN Baru;
- Konfirmasi PIN;
- submit `Simpan PIN`;
- logout tetap tersedia.

Behavior:

1. login dengan akun `must_change_pin = true` diarahkan ke halaman ini;
2. akses URL internal lain dialihkan oleh middleware backend;
3. PIN tepat 6 digit dan konfirmasi sama;
4. tombol disabled selama request;
5. validasi inline tanpa menghilangkan kedua input kecuali setelah sukses;
6. setelah sukses, PIN disimpan hash, `must_change_pin = false`, lalu redirect `/dashboard`.

---

# 7. Foto KTP

- jangan tampilkan di list;
- Detail: preview hanya Super Admin;
- Edit: existing file + replace;
- jika null → placeholder `Belum ada dokumen`;
- bukan avatar;
- jangan expose URL/file kepada Admin/User.

---

# 8. Delete / Deactivate UX

## Hard Delete
Jika eligible:
- confirmation dialog;
- jelaskan permanen;
- backend cek dependency lagi;
- jangan mengandalkan eligibility frontend.

Jika tidak eligible:
- jangan tawarkan hard delete atau tampilkan disabled state yang jelas;
- arahkan ke Nonaktifkan.

## Nonaktifkan
- confirmation;
- jelaskan dampak terhadap Absensi/login;
- setelah sukses status badge berubah;
- histori tetap ada.

## Karyawan Keluar
- minta Tanggal Keluar;
- validasi;
- status menjadi nonaktif;
- User existing ikut inactive.

---

# 9. Master Organisasi

Visual Jabatan dan Departemen tetap mengikuti `departemen_jabatan.png`; Penempatan ditambahkan dengan pola card/list yang sama tanpa redesign halaman.

Desktop:
- tiga card dengan pola visual yang sama:
  - Data Jabatan
  - Data Departemen
  - Data Penempatan

Data Jabatan menampilkan kolom Nama Jabatan, Role, dan Aksi. Role kosong ditampilkan sebagai badge `Belum Ditentukan`. Modal Tambah/Edit Jabatan memiliki field Nama Jabatan dan select Role dari master `role`; Role boleh dikosongkan.

Action:
- Tambah;
- Edit;
- Delete.

Tambah/Edit boleh menggunakan modal compact yang konsisten dengan reference.

Delete:
- confirmation;
- jika masih dipakai → backend menolak + tampilkan message.

Tablet/mobile:
- cards stack;
- action tetap accessible;
- tabel/card tidak overflow.

---

# 10. Loading / Empty / Error

Loading:
- skeleton/processing state ringan;
- jangan layout shift besar.

Empty:
- bedakan database kosong vs filter kosong;
- tombol reset filter jika relevan.

Unauthorized:
- backend 403.

Validation:
- inline.

Delete rejected:
- tampilkan alasan bisnis yang jelas.

---

# 11. Responsive

Desktop adalah reference utama.

Tablet:
- filters wrap;
- table horizontal scroll bila perlu;
- dua master cards stack jika ruang tidak cukup.

Mobile:
- form single-column;
- action stack/wrap;
- tabel dapat scroll horizontal;
- jangan mengubah permission/data scope.

---

# 12. Export Data Karyawan

Pada header Data Karyawan Super Admin, tampilkan tombol `Export Data` tanpa mengganggu `Tambah Karyawan`.

Klik tombol membuka modal compact:

```text
Export Data Karyawan

Status Keaktifan *
[ Aktif ▼ ]

[Batal] [Export Excel]
```

Pilihan hanya `Aktif` dan `Nonaktif`. Modal menjelaskan bahwa search/filter halaman tidak memengaruhi isi export. Admin/User tidak menerima tombol atau modal export.

Workbook `.xlsx` memakai satu sheet dengan header bold, warna header ringan, border tipis, freeze header, filter kolom, lebar kolom yang memadai, dan wrapping untuk Alamat. Jika hasil status kosong, tampilkan flash error tanpa mengunduh workbook kosong.

Desktop: tombol berada satu grup dengan Tambah Karyawan. Mobile: kedua tombol boleh wrap/stack dan modal tetap berada di dalam viewport.

---

# 13. Visual QA

Agent wajib membandingkan halaman aktual dengan:
- `data_karyawan.png`
- `departemen_jabatan.png`

Untuk Tambah/Edit/Detail:
- gunakan style yang sama dengan reference;
- jangan mengarang tema baru;
- dokumentasikan perbedaan visual yang sengaja dibuat.

---

# 14. Post-Implementation

Wajib:
- authorization tests;
- field-leakage tests Admin/User;
- CRUD tests;
- delete constraint tests;
- regression Dashboard Home;
- regression Absensi;
- production build;
- visual QA;
- update `LOG.md`.

---

# 15. Route Behavior Akun

- create/manage account hanya melalui route Employee yang dilindungi `auth + active + super_admin`;
- halaman dan submit Ganti PIN memerlukan `auth + active` tetapi dikecualikan dari forced-PIN redirect;
- logout selalu tersedia bagi authenticated user;
- route internal lain tidak boleh dapat dibypass selama `must_change_pin = true`.
- create account membaca `jabatan.role_id`; perubahan Jabatan Karyawan tidak mengubah role akun existing.
