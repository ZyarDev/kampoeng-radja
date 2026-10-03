# Global Access Control — Kampoeng Radja

Status: **final as-built baseline; Dashboard dan sebagian detail KPI tetap TBD**

## Tujuan

Dokumen ini menjelaskan lapisan access control yang harus digunakan seluruh sistem internal. Dokumen ini tidak memberikan permission baru kepada role mana pun.

## Lapisan Authorization

1. **Module/sidebar access** — apakah user boleh melihat dan menuju suatu modul.
2. **Page access** — apakah user boleh membuka route/page tertentu.
3. **Feature/action access** — apakah user boleh create, update, delete, approve, atau aksi khusus lain.
4. **Data scope** — record mana yang boleh dilihat/diubah, misalnya seluruh data, departemen sendiri, bawahan, atau diri sendiri.

Menyembunyikan menu atau tombol di frontend bukan authorization. Route/controller/Form Request/policy atau mekanisme backend lain wajib menolak aksi yang tidak diizinkan.

## Dimensi Permission

Permission dapat mempertimbangkan:

- role (`super_admin`, `admin`, `user`);
- jabatan;
- departemen;
- relasi kepemimpinan atau ownership data;
- status akun/karyawan;
- kondisi khusus modul, misalnya hanya tanggal berjalan.

Kombinasi aktual tidak boleh ditebak. Jika belum diputuskan, tulis `TBD — menunggu keputusan tim`.

## Baseline Keamanan Aktual

- Guest hanya boleh mengakses route publik dan login.
- Akun internal harus aktif (`users.is_active = true`).
- Middleware `admin` saat ini mengizinkan role aktif `admin` dan `super_admin` ke area `/admin`.
- Route view Data Absensi dilindungi `auth + active`; capability backend memberikan akses view company-wide kepada `super_admin`, `admin`, dan `user`.
- Mutation dan export Excel Absensi tetap dibatasi backend kepada `super_admin`; hide/show frontend hanya digunakan untuk UX.
- CMS menggunakan matrix final: `super_admin`, Departemen `MARCOM`, atau `admin` + Penempatan `MARCOM` memperoleh full CRUD; kondisi lain ditolak backend.
- Role tidak disimpan manual di session; nilai dibaca dari relasi database.
- Untuk akun Karyawan baru, Jabatan adalah sumber authoritative bagi mapping `users.role_id`. Perubahan Jabatan employee wajib menyinkronkan role account existing tanpa mengubah username, PIN, atau active state.

Daftar di atas adalah **implementation truth**, bukan keputusan permission final untuk modul yang masih TBD.

## Aturan Dokumen Modul

Setiap modul Dashboard aktif yang mempunyai akses khusus sebaiknya memiliki `PERMISSIONS.md` berisi:

- page access;
- visible components;
- allowed actions;
- data scope;
- special conditions;
- implementation status;
- keputusan yang masih TBD.

Jika requirement dan implementation berbeda, pertahankan requirement dan catat mismatch di `docs/DOCUMENTATION_AUDIT.md` serta `LOG.md`.

## Larangan

- Jangan menyimpulkan Admin memiliki semua data hanya karena dapat membuka `/admin`.
- Jangan menyimpulkan User tidak akan pernah mendapat akses hanya karena saat ini mendapat 403.
- Jangan memberi permission berdasarkan nama jabatan/departemen tanpa keputusan tim.
- Jangan mengandalkan frontend hide/show sebagai satu-satunya proteksi.
- Jangan mengubah role atau schema untuk menyelesaikan ambiguity dokumentasi.

## Referensi

- Matrix: `docs/GLOBAL/ACCESS_CONTROL_MATRIX.md`
- Progress aktual: `LOG.md`
- Absensi: `docs/DASHBOARD/ATTENDANCE/PERMISSIONS.md`
