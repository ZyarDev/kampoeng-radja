PRODUCT REQUIREMENTS DOCUMENT (PRD)

Modul Kelola Karyawan - Kampoeng Radja

Versi requirement terbaru berdasarkan keputusan yang telah disepakati

## Baseline Organisasi Arif — 29 Agustus 2026

Bagian ini menggantikan requirement organisasi/role lama yang masih muncul pada paragraf historis di bawah:

- Role: Dirut, Direktur, dan Manajer → `super_admin`; SPV/Supervisor → `admin`; Marketing, Marcom, IT, Finance, Kasir, Operasional, General, dan Facility → `user`.
- Jabatan `Staff` dibatalkan dan tidak dibuat otomatis.
- Konsep `Posisi`, `posisi_id`, dan Master Posisi dibatalkan.
- Master organisasi resmi: Jabatan, Departemen, dan Penempatan.
- Jabatan wajib; Departemen dan Penempatan nullable.
- Karyawan dapat memiliki Atasan Langsung nullable yang mereferensikan Karyawan lain dan tidak boleh mereferensikan diri sendiri.
- Karyawan dapat memiliki Foto Tanda Tangan berupa file path; bukan base64.
- Export Karyawan memuat Penempatan dan Atasan Langsung, tetapi tidak memuat Foto Tanda Tangan.
- KPI/Daily Report/evaluasi KPI tidak termasuk scope dan tidak diimplementasikan.

1. Ringkasan Modul

Modul Kelola Karyawan merupakan modul internal untuk pengelolaan master data karyawan, jabatan, departemen, dan akun pengguna. Akses penuh hanya dimiliki oleh Super Admin. Admin dan User hanya memiliki menu Data Karyawan dengan hak baca terbatas pada atribut yang telah ditentukan.

Item

Keputusan

Nama menu Super Admin

Kelola Karyawan

Submenu Super Admin

Data Karyawan; Jabatan & Departemen

Nama menu Admin/User

Data Karyawan

Akses Super Admin

Lihat, tambah, edit, hapus karyawan; kelola jabatan & departemen; buat/kelola akun

Akses Admin/User

Read-only data karyawan dengan atribut terbatas

Sumber jabatan/departemen pada form

Dropdown dari tabel jabatan dan departemen

Departemen

Boleh kosong untuk jabatan yang tidak terikat departemen

Tanggal keluar

Boleh kosong untuk karyawan yang masih aktif

2. Tujuan

Menyediakan satu sumber data karyawan yang terstruktur dan konsisten.

Memisahkan hak akses pengelolaan penuh Super Admin dari akses baca Admin dan User.

Memastikan jabatan dan departemen dipilih dari master data, bukan diketik bebas pada form karyawan.

Mendukung pembuatan akun pengguna yang terhubung satu-ke-satu dengan data karyawan.

Melindungi atribut pribadi karyawan agar tidak seluruhnya terlihat oleh Admin dan User.

3. Aktor dan Hak Akses

Fitur

Super Admin

Admin

User

Lihat data karyawan umum

Ya

Ya

Ya

Lihat seluruh atribut karyawan

Ya

Tidak

Tidak

Tambah karyawan

Ya

Tidak

Tidak

Edit karyawan

Ya

Tidak

Tidak

Hapus karyawan

Ya

Tidak

Tidak

Akses Jabatan & Departemen

Ya

Tidak

Tidak

Tambah/Edit/Hapus Jabatan

Ya

Tidak

Tidak

Tambah/Edit/Hapus Departemen

Ya

Tidak

Tidak

Buat/Kelola akun

Ya

Tidak

Tidak

4. Struktur Navigasi

Super Admin

Kelola Karyawan

Data Karyawan

Jabatan & Departemen

Admin dan User

Data Karyawan

Admin dan User tidak melihat menu Kelola Karyawan maupun submenu Jabatan & Departemen.

5. Field Visibility Data Karyawan

Field visibility berlaku pada list dan Detail Karyawan. Super Admin, Admin, dan User memiliki row scope company-wide pada menu Data Karyawan. Perbedaan akses hanya ditentukan oleh field visibility, izin mutation, akses master Jabatan & Departemen, serta account management.

5.1 Atribut Umum

Ditampilkan kepada Super Admin, Admin, dan User:

- nama
- jenis_kelamin
- agama
- tanggal_lahir
- tempat_lahir
- pendidikan
- jabatan
- departemen
- status_kerja
- status_keaktifan
- tanggal_masuk
- tanggal_keluar

5.2 Atribut Sensitif

Hanya boleh diterima dan dilihat oleh Super Admin:

- nik
- alamat
- status_perkawinan
- no_hp
- foto_ktp

Admin dan User tidak boleh menerima key, path, maupun URL atribut sensitif dalam payload frontend. Super Admin tetap mengelola data lengkap melalui Tambah, Detail, dan Edit Karyawan.

6. Fitur Tambah Karyawan

Hanya Super Admin yang dapat menambahkan karyawan baru.

Super Admin membuka Kelola Karyawan > Data Karyawan.

Super Admin memilih aksi Tambah Karyawan.

Sistem menampilkan form input data karyawan lengkap.

Field Jabatan menampilkan pilihan dari tabel jabatan.

Field Departemen menampilkan pilihan dari tabel departemen dan boleh dikosongkan.

Super Admin mengisi seluruh data wajib dan menyimpan form.

Sistem melakukan validasi dan menyimpan data karyawan jika seluruh ketentuan terpenuhi.

Setelah data karyawan berhasil dibuat, sistem menyediakan opsi untuk membuat akun karyawan sekarang atau nanti.

7. Form Tambah/Edit Karyawan

Field

Tipe Input

Aturan

nama

Text

Wajib

nik

Text

Wajib, unik, maksimal 20 karakter

tanggal_lahir

Date

Wajib

tempat_lahir

Text

Wajib

jenis_kelamin

Select/Radio

L atau P

alamat

Textarea

Wajib sesuai kebijakan data perusahaan

agama

Select

Dari nilai ENUM

status_perkawinan

Select

Dari nilai ENUM

pendidikan

Select

Dari nilai ENUM

jabatan_id

Select

Sumber: tabel jabatan; wajib

departemen_id

Select

Sumber: tabel departemen; boleh kosong

status_keaktifan

Select

aktif / nonaktif

status_kerja

Select

Dari nilai ENUM

tanggal_masuk

Date

Wajib

tanggal_keluar

Date

Boleh kosong

no_hp

Text

VARCHAR; tidak diperlakukan sebagai angka matematika

foto_ktp

File Upload

Gambar disimpan di storage; database menyimpan path

8. Fitur Edit Karyawan

Hanya Super Admin yang dapat mengedit data karyawan.

Form edit menggunakan struktur field yang sama dengan form tambah.

Jabatan dan departemen tetap menggunakan master data sebagai sumber pilihan.

Departemen tetap boleh kosong.

Tanggal keluar tetap boleh kosong.

Perubahan status_keaktifan menjadi nonaktif perlu dihubungkan dengan status akun agar karyawan yang tidak aktif tidak dapat login.

9. Fitur Hapus Karyawan

Hanya Super Admin yang dapat menghapus data karyawan.

Sistem wajib meminta konfirmasi sebelum penghapusan.

Sistem harus memeriksa relasi data sebelum menghapus agar tidak merusak integritas referensial.

Jika karyawan sudah memiliki data yang direferensikan modul lain (misalnya akun atau absensi), kebijakan penghapusan final perlu ditetapkan sebelum implementasi produksi.

10. Submenu Jabatan & Departemen - Super Admin

Halaman ini menampilkan dua tabel master pada satu submenu.

10.1 Tabel Jabatan

Kolom

Keterangan

nama_jabatan

Nama jabatan; unik

aksi

Edit dan Hapus

Tersedia fitur Tambah Jabatan.

Tersedia aksi Edit Jabatan.

Tersedia aksi Hapus Jabatan.

Jabatan yang masih digunakan oleh karyawan tidak boleh dihapus tanpa penanganan relasi terlebih dahulu.

10.2 Tabel Departemen

Kolom

Keterangan

nama_departemen

Nama departemen; unik

aksi

Edit dan Hapus

Tersedia fitur Tambah Departemen.

Tersedia aksi Edit Departemen.

Tersedia aksi Hapus Departemen.

Departemen yang masih digunakan oleh karyawan tidak boleh dihapus tanpa penanganan relasi terlebih dahulu.

11. Tampilan Data Karyawan - Admin dan User

Admin dan User hanya memiliki akses baca, tetapi keduanya dapat melihat seluruh karyawan dari seluruh departemen.

List dan Detail menampilkan atribut umum pada bagian 5.1 beserta Penempatan dan Atasan Langsung. Atribut sensitif pada bagian 5.2 tidak boleh diserialisasi ke Inertia props Admin/User. Admin dan User tidak memperoleh aksi tambah, edit, hapus, account management, Foto KTP, Foto Tanda Tangan, atau akses Master Organisasi.

12. Pembuatan dan Pengelolaan Akun Karyawan

Pembuatan akun dilakukan setelah data karyawan tersedia. Satu karyawan hanya boleh memiliki satu akun karena users.karyawan_id bersifat UNIQUE.

Super Admin memilih karyawan yang belum memiliki akun.

Super Admin memilih aksi Buat Akun.

Sistem mengikat akun ke karyawan_id yang dipilih.

Super Admin menentukan username yang unik.

Role akun baru dibaca dari relasi `karyawan → jabatan → role_id`. Baseline lama tetap digunakan hanya sebagai backfill awal untuk Jabatan existing.

Super Admin membuat PIN awal sementara 6 digit dan melakukan konfirmasi PIN.

Sistem menyimpan PIN dalam bentuk hash, bukan nilai asli.

Akun baru diberi must_change_pin = true.

Pada login pertama, pengguna wajib mengganti PIN sebelum dapat melanjutkan ke Dashboard.

Setelah berhasil mengganti PIN, must_change_pin diubah menjadi false.

Field Users

Field Users

Ketentuan

karyawan_id

FK dan UNIQUE

role_id

FK ke role

username

UNIQUE

pin

VARCHAR(255), berisi hash

is_active

Status akses login akun

must_change_pin

BOOLEAN; true pada akun baru/PIN sementara

created_at

Timestamp

updated_at

Timestamp

13. Aturan Role Akun Berdasarkan Jabatan

Setiap Jabatan memiliki `role_id` nullable yang dapat diatur Super Admin melalui Master Organisasi → Data Jabatan. Pilihan Role berasal dari master `role`; tidak ada fallback otomatis untuk Jabatan yang belum dikonfigurasi.

Saat akun baru dibuat, backend menyalin `jabatan.role_id` ke `users.role_id`. Jabatan tanpa Role menolak pembuatan akun dengan pesan konfigurasi yang jelas.

Perubahan Role pada Jabatan hanya memengaruhi akun yang dibuat setelah perubahan. Perubahan tersebut tidak melakukan bulk update pada akun existing. Perubahan Jabatan Karyawan juga tidak menyinkronkan `users.role_id` akun existing secara otomatis.

Baseline lama tetap digunakan sebagai backfill awal: Dirut/Direktur/Manajer → `super_admin`; SPV/Supervisor → `admin`; Marketing/Marcom/IT/Finance/Kasir/Operasional/General/Facility → `user`. Jabatan lain tetap `Belum Ditentukan` sampai dikonfigurasi Super Admin.

14. Tabel Database yang Digunakan

Tabel

Peran dalam Modul

karyawan

Menyimpan master data karyawan

jabatan

Master pilihan jabatan

departemen

Master pilihan departemen

users

Akun login yang terhubung ke karyawan

role

Tingkat akses akun

15. Aturan Bisnis Utama

NIK wajib unik.

Satu karyawan hanya boleh memiliki satu akun.

departemen_id boleh NULL.

tanggal_keluar boleh NULL.

foto_ktp menyimpan path file, bukan binary gambar.

Hanya Super Admin yang dapat melakukan operasi tulis pada data karyawan, jabatan, dan departemen.

Admin dan User hanya mendapatkan read-only view dengan atribut terbatas.

Jabatan dan departemen tidak diinput bebas pada form karyawan; keduanya harus berasal dari master data.

PIN awal harus di-hash dan tidak boleh disimpan dalam plaintext.

Pengguna wajib mengganti PIN sementara saat login pertama.

Akun karyawan nonaktif harus dapat dinonaktifkan agar tidak dapat melakukan login.

16. Validasi Minimum

nik tidak boleh duplikat.

username tidak boleh duplikat.

karyawan_id pada users tidak boleh duplikat.

jabatan_id harus mengarah ke jabatan yang tersedia.

departemen_id, jika diisi, harus mengarah ke departemen yang tersedia.

tanggal_keluar tidak boleh lebih awal dari tanggal_masuk.

PIN awal harus tepat 6 digit sebelum di-hash.

Konfirmasi PIN harus sama dengan PIN yang dimasukkan.

File foto KTP harus divalidasi sebagai file gambar dengan batas ukuran yang akan ditentukan pada implementasi.

17. Export Data Karyawan ke Excel

Super Admin dapat mengekspor Data Karyawan company-wide ke workbook Excel `.xlsx` berdasarkan pilihan `status_keaktifan` yang wajib dipilih: `aktif` atau `nonaktif`.

Status export hanya bersumber dari `karyawan.status_keaktifan`. Export tidak mengikuti search, filter Departemen, Jabatan, Status Kerja, atau filter list Data Karyawan yang sedang aktif.

Nama file:

- `data-karyawan-aktif.xlsx`;
- `data-karyawan-nonaktif.xlsx`.

Kolom final:

- No;
- Nama;
- NIK;
- Jenis Kelamin;
- Agama;
- Tempat Lahir;
- Tanggal Lahir;
- Alamat;
- Status Perkawinan;
- Pendidikan;
- Jabatan;
- Departemen;
- Status Kerja;
- Status Keaktifan;
- Tanggal Masuk;
- Tanggal Keluar;
- No. HP.

Data diurutkan `nama ASC`, lalu `id ASC`. Relasi Jabatan dan Departemen ditampilkan sebagai nama, bukan foreign key. Departemen dan Tanggal Keluar yang kosong ditampilkan `-`.

Foto KTP, path/URL Foto KTP, PIN, hash PIN, dan field autentikasi lain tidak boleh masuk export. Admin dan User tidak memiliki tombol maupun akses endpoint export; direct URL ditolak HTTP 403. Jika hasil pilihan status kosong, sistem menampilkan pesan dan tidak mengirim workbook kosong.

18. Acceptance Criteria

Super Admin dapat melihat seluruh atribut karyawan.

Super Admin dapat menambah, mengedit, dan menghapus karyawan sesuai aturan relasi.

Pilihan jabatan dan departemen pada form berasal dari tabel master.

Super Admin dapat menambah, mengedit, dan menghapus master jabatan dan departemen dengan proteksi referensi.

Admin dan User hanya melihat atribut karyawan yang telah ditentukan dan tidak melihat data pribadi yang dikecualikan.

Admin dan User tidak melihat aksi tambah, edit, hapus, atau menu Jabatan & Departemen.

Super Admin dapat membuat akun untuk karyawan yang belum memiliki akun.

Sistem mencegah satu karyawan memiliki lebih dari satu akun.

PIN akun baru disimpan sebagai hash dan pengguna diwajibkan mengganti PIN sementara pada login pertama.

Karyawan dengan departemen kosong dan tanggal keluar kosong dapat disimpan dengan valid.

Super Admin dapat export seluruh Karyawan Aktif atau Nonaktif ke Excel company-wide.

Admin/User tidak dapat export Data Karyawan.

Foto KTP dan data autentikasi tidak terdapat pada workbook Employee.

19. Out of Scope / Belum Dikunci

Riwayat jabatan belum digunakan pada fase ini.

Soft delete vs hard delete untuk karyawan belum diputuskan final.

Batas ukuran dan format file foto KTP akan ditetapkan pada implementasi.

Detail audit log perubahan data karyawan belum dirancang sebagai tabel terpisah.

KPI, Closing Event, Attendance, dan CMS berada di PRD/modul terpisah.
