# PERMISSIONS — Kelola Karyawan

**Status:** READY FOR IMPLEMENTATION
**Last Updated:** 2026-09-20 — konfigurasi Role pada master Jabatan

> **Baseline Arif 2026-08-29:** Master organisasi adalah Jabatan, Departemen, dan Penempatan. Jabatan wajib; Departemen/Penempatan nullable. Mapping role authoritative: Dirut/Direktur/Manajer → `super_admin`; SPV/Supervisor → `admin`; Marketing/Marcom/IT/Finance/Kasir/Operasional/General/Facility → `user`. `Staff` dan konsep `Posisi` dibatalkan. Atasan Langsung adalah self-reference Karyawan nullable. KPI tidak diimplementasikan.

Legenda:
- ✅ ALLOW
- ❌ DENY
- 🔒 SCOPE/CONDITION

---

# 1. Page Access

| Halaman | Super Admin | Admin | User |
|---|---|---|---|
| Data Karyawan | ✅ | ✅ | ✅ |
| Tambah Karyawan | ✅ | ❌ | ❌ |
| Edit Karyawan | ✅ | ❌ | ❌ |
| Detail Karyawan | ✅ | ✅ | ✅ |
| Master Organisasi (Jabatan, Departemen, Penempatan) | ✅ | ❌ | ❌ |
| Ganti PIN Pertama | 🔒 akun sendiri dengan `must_change_pin = true` | 🔒 akun sendiri dengan `must_change_pin = true` | 🔒 akun sendiri dengan `must_change_pin = true` |

---

# 2. Data Scope

| Role | Scope |
|---|---|
| Super Admin | Seluruh karyawan dari seluruh departemen (company-wide) |
| Admin | Seluruh karyawan dari seluruh departemen (company-wide) |
| User | Seluruh karyawan dari seluruh departemen (company-wide) |

Perbedaan ketiga role pada modul ini berasal dari field visibility, action permission, akses master Jabatan & Departemen, serta account management; bukan dari row scope.

---

# 3. Field Visibility

## A. Common Employee Fields

| Field | Super Admin | Admin | User |
|---|---|---|---|
| Nama | ✅ | ✅ | ✅ |
| Jenis Kelamin | ✅ | ✅ | ✅ |
| Agama | ✅ | ✅ | ✅ |
| Tanggal Lahir | ✅ | ✅ | ✅ |
| Tempat Lahir | ✅ | ✅ | ✅ |
| Pendidikan | ✅ | ✅ | ✅ |
| Jabatan | ✅ | ✅ | ✅ |
| Departemen | ✅ | ✅ | ✅ |
| Status Kerja | ✅ | ✅ | ✅ |
| Status Keaktifan | ✅ | ✅ | ✅ |
| Tanggal Masuk | ✅ | ✅ | ✅ |
| Tanggal Keluar | ✅ | ✅ | ✅ |

## B. Sensitive Employee Fields

| Field | Super Admin | Admin | User |
|---|---|---|---|
| NIK | ✅ | ❌ | ❌ |
| Alamat | ✅ | ❌ | ❌ |
| Status Perkawinan | ✅ | ❌ | ❌ |
| No. HP | ✅ | ❌ | ❌ |
| Foto KTP/path/URL | ✅ | ❌ | ❌ |

Backend wajib menghilangkan seluruh key sensitif dari Inertia props Admin/User; menyembunyikannya hanya melalui kondisi Vue tidak memenuhi permission ini. Super Admin tetap menerima data lengkap pada Detail, Tambah, dan Edit Karyawan.

---

# 4. Actions

| Action | Super Admin | Admin | User |
|---|---|---|---|
| View list | ✅ company-wide | ✅ company-wide | ✅ company-wide |
| Search/filter | ✅ | ✅ | ✅ |
| View detail | ✅ company-wide | ✅ company-wide | ✅ company-wide |
| Create | ✅ | ❌ | ❌ |
| Edit | ✅ | ❌ | ❌ |
| View Foto KTP | ✅ | ❌ | ❌ |
| Replace Foto KTP | ✅ | ❌ | ❌ |
| Change Status Kerja | ✅ | ❌ | ❌ |
| Change Status Keaktifan | ✅ | ❌ | ❌ |
| Process Karyawan Keluar | ✅ | ❌ | ❌ |
| Conditional Hard Delete | ✅ dengan rule | ❌ | ❌ |
| Create Account | ✅ | ❌ | ❌ |
| View Account Status | ✅ | ❌ | ❌ |
| Activate/Deactivate Account | ✅ dengan rule status Karyawan | ❌ | ❌ |
| Export Data Karyawan Excel | ✅ company-wide, pilih Aktif/Nonaktif | ❌ | ❌ |

---

## 4.1 Export Data Karyawan

- hanya Super Admin;
- endpoint dilindungi backend, bukan hanya disembunyikan di Vue;
- pilihan status wajib `aktif` atau `nonaktif` dan bersumber dari `karyawan.status_keaktifan`;
- export selalu company-wide serta tidak mengikuti filter list Data Karyawan;
- Foto KTP, path/URL Foto KTP, PIN, hash, dan field akun lain tidak diexport;
- Admin/User direct URL menerima HTTP 403.

---

# 5. Delete / Deactivate Rule

## Hard Delete
Super Admin boleh hard delete hanya bila Karyawan belum memiliki:
- User;
- Absensi;
- histori/relasi lain yang harus dipertahankan.

Jika sudah memiliki dependency, backend menolak.

## Deactivate
Super Admin boleh menonaktifkan Karyawan.

Jika mempunyai User:
- `users.is_active = false`.

Mengaktifkan kembali Karyawan tidak otomatis mengaktifkan User.

---

# 6. Master Organisasi

| Action | Super Admin | Admin | User |
|---|---|---|---|
| View | ✅ | ❌ | ❌ |
| Create | ✅ | ❌ | ❌ |
| Edit | ✅ | ❌ | ❌ |
| Delete | ✅ dengan constraint | ❌ | ❌ |

Delete ditolak jika masih direferensikan karyawan aktif maupun nonaktif.

---

# 7. User Account

Pengelolaan akun merupakan bagian modul Employee berdasarkan PRD terbaru, tetapi akun **tidak dibuat otomatis** ketika data Karyawan dibuat.

| Action | Super Admin | Admin | User |
|---|---|---|---|
| View account section | ✅ | ❌ | ❌ |
| Create account for Employee without account | ✅ | ❌ | ❌ |
| Choose arbitrary `role_id` | ❌ | ❌ | ❌ |
| Activate/deactivate account | ✅ dengan guard | ❌ | ❌ |
| View PIN/hash | ❌ | ❌ | ❌ |
| Delete account | ❌ | ❌ | ❌ |

Aturan authoritative:

1. satu Karyawan maksimal satu akun (`users.karyawan_id` UNIQUE);
2. role ditentukan backend dari Jabatan, bukan input browser;
3. Jabatan tanpa mapping role ditolak dengan pesan aman, tanpa default role;
4. PIN awal tepat 6 digit, dikonfirmasi, lalu disimpan sebagai hash;
5. akun baru memiliki `must_change_pin = true`;
6. akun Karyawan nonaktif tidak boleh diaktifkan;
7. aktivasi kembali master Karyawan tidak otomatis mengaktifkan akun;
8. ketika Karyawan dinonaktifkan atau diproses keluar, akun existing ikut dinonaktifkan.
9. akun baru memakai `jabatan.role_id` sebagai sumber `users.role_id`;
10. perubahan Role Jabatan tidak mengubah akun existing secara massal;
11. perubahan Jabatan Karyawan tidak menyinkronkan role akun existing secara otomatis.

## 7.1 Konfigurasi Role Jabatan

| Kategori Jabatan | Role |
|---|---|
| Dirut, Direktur, Manajer/Manager | `super_admin` |
| SPV/Supervisor | `admin` |
| Marketing, Marcom, IT, Finance, Kasir, Operasional, General, Facility | `user` |

Mapping di atas digunakan sebagai backfill awal untuk Jabatan existing. Setelah itu, Super Admin mengelola relasi `jabatan.role_id` melalui Master Organisasi. Jabatan lain tidak memperoleh fallback.

Role authorization akun tetap dibaca dari `users.role_id`. `jabatan.role_id` merupakan konfigurasi default untuk pembuatan akun baru dan tidak menggantikan role akun existing.

## 7.2 Forced First PIN Change

User terautentikasi dengan `must_change_pin = true` hanya boleh mengakses:

- halaman Ganti PIN Pertama;
- submit Ganti PIN Pertama;
- logout.

Route Dashboard, Employee, CMS, Absensi, dan route internal lain wajib diblokir backend sampai PIN baru valid tersimpan dan `must_change_pin` menjadi `false`.

---

# 8. Backend Security

Wajib:
1. authorization backend untuk mutation;
2. query scope diterapkan sebelum data dikirim;
3. field sensitif tidak diserialisasi kepada Admin/User;
4. request URL manual unauthorized → 403;
5. role dari database;
6. tidak percaya `role`, `departemen_id`, atau ownership dari client payload;
7. protected delivery untuk Foto KTP bila diterapkan.
8. forced PIN change ditegakkan middleware backend, bukan redirect frontend saja;
9. login memeriksa `users.is_active` dan `karyawan.status_keaktifan`;
10. status akun tidak boleh diaktifkan jika master Karyawan nonaktif.
