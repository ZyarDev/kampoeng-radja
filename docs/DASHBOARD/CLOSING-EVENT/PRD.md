# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## Modul Closing Event — Kampoeng Radja

**Status:** SUPERSEDED — final as-built matrix is in `docs/DASHBOARD/CLOSING-EVENT/PERMISSIONS.md`
**Tanggal finalisasi requirement:** 23 Agustus 2026

---

> **Documentation reconciliation note:** The historical Manager/Supervisor/Marketing matrix in this document is retained for history only. The final stakeholder matrix now uses Department/Placement rules and is documented in `PERMISSIONS.md` and `docs/AS_BUILT_PRD_RECONCILIATION.md`.

## 1. Ringkasan Modul

Modul **Closing Event** merupakan modul internal untuk mencatat dan mengelola kegiatan/event yang di-booking atau disewa oleh konsumen/klien Kampoeng Radja.

Modul mencakup pencatatan data konsumen, PIC, jenis event, tanggal pelaksanaan, jam kedatangan, satu atau lebih lokasi, konsumsi, jumlah pengunjung, panitia, additional, harga total, serta audit pengguna yang membuat dan terakhir mengubah data.

Hak akses modul Closing Event tidak ditentukan hanya oleh role global aplikasi, tetapi oleh kombinasi **role, jabatan, dan departemen** pengguna.

Role global pengguna tetap mengikuti aturan sistem akun yang sudah ada dan **tidak diubah oleh modul Closing Event**.

---

## 2. Tujuan Modul

- Menyediakan satu tempat terpusat untuk mencatat dan mengelola data Closing Event.
- Mencatat informasi konsumen dan kebutuhan pelaksanaan event secara terstruktur.
- Mendukung satu Closing Event menggunakan satu atau lebih lokasi.
- Menggunakan master PIC, Jenis Event, dan Lokasi agar data tidak diketik bebas.
- Menerapkan access control berdasarkan kombinasi role, jabatan, dan departemen.
- Memastikan pengguna yang tidak berwenang tidak dapat melihat menu, halaman, endpoint, maupun data Closing Event.
- Mencatat pengguna pembuat dan pengguna terakhir yang mengubah data melalui `created_by` dan `updated_by`.

---

## 3. Ruang Lingkup

### 3.1 Termasuk dalam scope

- Daftar Closing Event.
- Filter berdasarkan **bulan dan tahun pelaksanaan event**.
- Tambah Closing Event.
- Detail Closing Event.
- Edit Closing Event.
- Hapus Closing Event khusus Super Admin.
- CRUD Master PIC khusus Super Admin.
- CRUD Master Jenis Event khusus Super Admin.
- CRUD Master Lokasi khusus Super Admin.
- Pemilihan PIC dari master PIC.
- Pemilihan Jenis Event dari master Event.
- Pemilihan satu atau lebih Lokasi dari master Lokasi.
- Penyimpanan audit `created_by`, `updated_by`, `created_at`, dan `updated_at`.
- Authorization backend dan pembatasan UI berdasarkan hak akses.
- Event satu hari atau multi-hari tetap disimpan sebagai satu record Closing Event.
- Highlight otomatis pada event yang rentang pelaksanaannya mencakup hari berjalan.
- Status bisnis `aktif`/`dibatalkan`, pembatalan dengan alasan dan audit actor/waktu, serta reaktivasi event.

### 3.2 Di luar scope

- Approval berjenjang Closing Event.
- Pembayaran, invoice, DP, pelunasan, atau status pembayaran.
- Integrasi kalender eksternal.
- Notifikasi otomatis.
- Dashboard analitik khusus Closing Event.
- Laporan keuangan Closing Event.
- Upload kontrak atau bukti pembayaran.
- Lifecycle tambahan seperti draft, pending, berlangsung, atau selesai. `Sedang Berlangsung` tetap state turunan tanggal.
- Pembatasan edit berdasarkan umur/tanggal data.
- Audit log perubahan terpisah selain `created_by`, `updated_by`, dan timestamps.

---

## 4. Prinsip Access Control

Closing Event menggunakan access control bertingkat:

1. akses menu/modul;
2. akses halaman;
3. akses action di dalam halaman.

Frontend menyesuaikan menu, tombol, form, dan action yang terlihat, tetapi **authorization backend wajib tetap menjadi sumber keamanan utama**.

Pengguna yang tidak memiliki akses tidak boleh memperoleh data Closing Event dari endpoint aplikasi.

---

## 5. Aktor dan Hak Akses

| Kelompok | Jabatan | Departemen | Lihat | Input | Detail | Edit | Hapus | Export | Kelola Master |
|---|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Super Admin | Dirut, Direktur, Admin Sistem | Semua / tidak wajib departemen | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Manajer | Manajer / Manager | Semua departemen | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| SPV Marcom | Supervisor | Marcom | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| SPV Marketing | Supervisor | Marketing | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| Karyawan Marketing | Selain kelompok di atas | Marketing | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| Role User | Semua jabatan/departemen | Semua | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Karyawan lainnya | Selain yang disebut di atas | Selain ketentuan di atas | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

### 5.1 Ketentuan penting

- Seluruh Manajer memperoleh akses lihat, input, detail, dan edit tanpa dibatasi departemen.
- Supervisor hanya memperoleh akses apabila berada di departemen **Marcom** atau **Marketing**.
- Supervisor dari departemen selain Marcom atau Marketing tidak memperoleh akses Closing Event.
- Karyawan biasa departemen Marketing memperoleh akses lihat, detail, dan input, tetapi tidak dapat edit atau hapus.
- Karyawan biasa departemen Marcom tidak otomatis memperoleh akses.
- Pengguna lain yang tidak termasuk kelompok di atas tidak memperoleh akses sama sekali.
- Pengguna dengan role global `user` memperoleh akses company-wide untuk daftar dan detail Closing Event secara read-only, tanpa Create, Update, Delete, Export, atau pengelolaan Master.

### 5.2 Role global tidak berubah

Modul Closing Event **tidak membuat role baru dan tidak mengubah role pengguna**.

Sistem membaca role, jabatan, dan departemen yang sudah dimiliki pengguna dari sistem akun/karyawan, kemudian menentukan capability Closing Event.

Contoh:

- Manager Finance tetap menggunakan role global yang sudah dimilikinya, lalu mendapatkan akses Closing Event karena jabatannya Manajer.
- Supervisor Marketing mendapat akses karena jabatan `Supervisor` dan departemen `Marketing`.
- Supervisor Facility tidak mendapat akses karena departemennya bukan Marcom atau Marketing.
- Karyawan biasa Marketing mendapat akses lihat dan input karena departemennya `Marketing`.

---

## 6. Capability Closing Event

Sistem sebaiknya mengevaluasi capability berikut:

- `canViewClosingEvent`
- `canCreateClosingEvent`
- `canUpdateClosingEvent`
- `canDeleteClosingEvent`
- `canExportClosingEvent`
- `canManageClosingEventMaster`

### 6.1 Prioritas evaluasi

1. Super Admin → seluruh capability.
2. Manajer / Manager → View, Create, Detail, Update, Export.
3. Supervisor + Marcom → View, Create, Detail, Update, Export.
4. Supervisor + Marketing → View, Create, Detail, Update, Export.
5. Karyawan lainnya + Marketing → View, Create, Detail, Export.
6. Role global User → View dan Detail saja.
7. Selain itu → tidak memiliki capability Closing Event.

---

## 7. Struktur Menu dan Navigasi

### 7.1 Pengguna yang memiliki akses umum

Closing Event menjadi menu utama pada dashboard internal.

Ketika dibuka, pengguna diarahkan ke halaman daftar Closing Event.

### 7.2 Super Admin

Super Admin memiliki struktur:

```text
Closing Event
├── Data Closing Event
└── Master Closing Event
    ├── PIC
    ├── Jenis Event
    └── Lokasi
```

### 7.3 Manajer, SPV Marcom, SPV Marketing, dan Karyawan Marketing

Hanya memiliki akses ke:

```text
Closing Event
└── Data Closing Event
```

Mereka tidak melihat menu pengelolaan master.

---

## 8. Halaman Daftar Closing Event

Halaman daftar menjadi titik masuk utama modul.

### 8.1 Kolom tabel

| Kolom | Sumber Data / Keterangan |
|---|---|
| Tanggal | Range compact dari `closing_event.tanggal` sampai `tanggal_selesai`; `tanggal` adalah tanggal mulai |
| PIC | `pic.nama_pic` |
| Konsumen | `closing_event.konsumen` |
| Jenis Event | `event.jenis_event` |
| Lokasi | Gabungan satu atau lebih lokasi dari `closing_event_lokasi` |
| Jam Kedatangan | `closing_event.jam_kedatangan` |
| Jumlah Pengunjung | `closing_event.jumlah_pengunjung` |
| Konsumsi | Ya/Tidak dari `closing_event.konsumsi` |
| Additional | `closing_event.additional`, ditampilkan ringkas/truncate |
| Panitia | `closing_event.panitia`, ditampilkan ringkas/truncate |
| Aksi | Menyesuaikan hak akses |

Event dibatalkan diberi badge kecil **Dibatalkan** di bawah tanggal. Event aktif tidak memerlukan badge pada list kecuali sedang berlangsung.

`kontak` dan `harga_total` tidak ditampilkan pada daftar utama. Keduanya tetap disimpan, ditampilkan pada Detail, dan disertakan dalam Export Excel.

### 8.2 Tombol aksi

- **Tambah Closing Event**: Super Admin, seluruh Manajer, SPV Marcom, SPV Marketing, dan seluruh karyawan departemen Marketing.
- **Detail**: seluruh pengguna yang memiliki akses modul.
- **Edit**: Super Admin, seluruh Manajer, SPV Marcom, dan SPV Marketing.
- **Hapus**: hanya Super Admin.

Karyawan Marketing biasa hanya dapat melihat, membuka detail, dan menambahkan data baru.

### 8.3 Highlight event sedang berlangsung

- Effective end date menggunakan `tanggal_selesai ?? tanggal`.
- Closing Event ditandai **Sedang berlangsung** hanya ketika `status_event = aktif`, `tanggal <= hari ini`, dan effective end date `>= hari ini` secara inclusive, memakai zona aplikasi `Asia/Jakarta`.
- Jika terdapat beberapa event yang rentangnya mencakup hari berjalan, seluruh row tersebut memperoleh highlight.
- Karena schema tidak memiliki jam selesai, highlight berlaku sepanjang tanggal kalender. Status `dibatalkan` selalu menonaktifkan highlight.
- Highlight hanya merupakan bantuan visual; tidak mengubah authorization, sorting, filter, status database, atau hak edit event.

---

## 9. Filter, Sorting, dan Pagination

### 9.1 Filter

Filter utama Closing Event menggunakan **irisan rentang pelaksanaan event**. Event tampil jika:

```text
tanggal <= akhir bulan terpilih
AND
(tanggal_selesai ?? tanggal) >= awal bulan terpilih
```

Filter **tidak menggunakan**:

```text
created_at
```

atau tanggal kapan data Closing Event dimasukkan ke sistem.

Filter menggunakan:

- Bulan pelaksanaan.
- Tahun pelaksanaan.
- Status Event: Semua Status (default), Aktif, atau Dibatalkan.

### 9.2 Default tampilan

Saat halaman daftar pertama kali dibuka:

- tampilkan Closing Event pada **bulan berjalan**;
- tahun menggunakan tahun berjalan;
- data diambil berdasarkan overlap rentang tanggal dengan bulan berjalan.

### 9.3 Sorting dalam bulan

Urutan default:

```text
tanggal ASC
id ASC
```

Event dengan tanggal pelaksanaan terdekat pada bulan tersebut tampil lebih dahulu.

### 9.4 Pagination

Gunakan server-side pagination:

```text
15 data per halaman
```

Filter bulan/tahun/status harus tetap dipertahankan ketika pengguna berpindah halaman.

### 9.5 Export Excel bulanan

- Format file `.xlsx` dengan nama `closing-event-{bulan}-{tahun}.xlsx`.
- Periode berdasarkan overlap `tanggal`/`tanggal_selesai`, bukan timestamps sistem.
- Export tersedia untuk Super Admin, seluruh Manajer, SPV Marcom, SPV Marketing, dan seluruh karyawan Marketing.
- Export bersifat company-wide dan memuat seluruh event aktif maupun dibatalkan pada bulan/tahun terpilih sebagai histori operasional.
- Urutan row menggunakan `tanggal ASC, id ASC`.
- Satu Closing Event menjadi satu row, termasuk event multi-hari; multiple lokasi digabung dalam satu cell.
- Kolom: NO, TANGGAL MULAI, TANGGAL SELESAI, PIC, KONSUMEN, KONTAK, JENIS EVENT, LOKASI, JAM KEDATANGAN, JUMLAH PENGUNJUNG, KONSUMSI, ADDITIONAL, PANITIA, HARGA TOTAL, STATUS EVENT, ALASAN PEMBATALAN, DIBATALKAN OLEH, DIBATALKAN PADA.
- Harga Total tetap berupa nilai numeric dengan formatting Rupiah.
- Harga Total tidak dibagi atau diduplikasi berdasarkan jumlah hari. Untuk agregasi nilai di masa depan, harga hanya dihitung satu kali pada `tanggal` (Tanggal Mulai).
- Nilai nullable ditampilkan sebagai `-`.
- Periode kosong tidak menghasilkan workbook kosong dan harus menampilkan feedback yang jelas.

---

## 10. Halaman Detail Closing Event

Halaman Detail dibuat untuk menampilkan informasi Closing Event secara lengkap tanpa memenuhi tabel daftar dengan seluruh atribut.

### 10.1 Informasi Event

- Status Event (`Aktif` atau `Dibatalkan`).

- Tanggal Mulai.
- Tanggal Selesai; tampil `—` untuk event satu hari.
- Jam Kedatangan.
- Jenis Event.
- Lokasi.

### 10.2 Informasi Konsumen

- Konsumen.
- Kontak.
- Jumlah Pengunjung.

### 10.3 Informasi Operasional

- PIC.
- Konsumsi.
- Panitia.
- Additional.

### 10.4 Informasi Nilai

- Harga Total.

### 10.5 Informasi Audit

- Dibuat oleh.
- Waktu dibuat.
- Terakhir diubah oleh.
- Waktu terakhir diubah.

### 10.6 Action pada Detail

Action mengikuti permission pengguna:

- Super Admin → Edit, Hapus.
- Manajer → Edit.
- SPV Marcom → Edit.
- SPV Marketing → Edit.
- Karyawan Marketing biasa → tidak memiliki Edit/Hapus.

---

## 11. Form Tambah Closing Event

Form tambah hanya dapat dibuka oleh pengguna yang memiliki capability Create.

PIC, Jenis Event, dan Lokasi tidak diketik bebas.

| Field | Komponen Input | Kewajiban | Keterangan |
|---|---|---|---|
| PIC | Select | Wajib | Mengambil dari tabel `pic` |
| Tanggal Mulai | Date | Wajib | Disimpan pada kolom existing `tanggal` |
| Tanggal Selesai | Date | Opsional | Nullable dan harus sama dengan atau setelah Tanggal Mulai; kosong berarti event satu hari; nilai yang sama dengan Tanggal Mulai dinormalisasi menjadi `NULL` |
| Konsumen | Text | Wajib | Nama klien/pihak yang menyewa |
| Kontak | Text | Wajib | Nomor kontak konsumen |
| Jam Kedatangan | Time | Wajib | Waktu kedatangan konsumen |
| Jenis Event | Select | Wajib | Mengambil dari tabel `event` |
| Lokasi | Multi-select / checklist | Wajib | Minimal satu lokasi; dapat lebih dari satu |
| Additional | Textarea | Opsional | Catatan/kebutuhan tambahan |
| Konsumsi | Boolean / Ya-Tidak | Wajib | Apakah konsumsi disediakan Kampoeng Radja |
| Jumlah Pengunjung | Number | Wajib | Minimal 1 |
| Harga Total | Currency / Number | Wajib | Disimpan sebagai `DECIMAL(15,2)` |
| Panitia | Textarea | Opsional | Teks bebas |

---

## 12. Proses Penyimpanan Closing Event

1. Backend memeriksa authorization Create.
2. Request divalidasi.
3. Sistem membuat satu record pada `closing_event`.
4. `created_by` diisi dengan `users.id` pengguna yang sedang login.
5. `updated_by` pada pembuatan awal diisi `NULL`.
6. Setiap lokasi yang dipilih dibuat sebagai relasi pada `closing_event_lokasi`.
7. Proses penyimpanan data utama dan relasi lokasi dilakukan dalam transaction.
8. Pengguna diarahkan ke daftar atau detail dengan notifikasi berhasil.

Jika tiga lokasi dipilih, sistem membuat tiga relasi lokasi dengan `closing_event_id` yang sama.

---

## 13. Form Edit Closing Event

Edit hanya tersedia untuk:

- Super Admin.
- Seluruh Manajer.
- SPV Markom.
- SPV Marketing.

### 13.1 Ketentuan edit

- Form menggunakan field yang sama dengan form tambah.
- Data lama dimuat terlebih dahulu.
- Seluruh lokasi lama harus ikut terpilih.
- Pengguna dapat menambah atau menghapus lokasi.
- Relasi lokasi disinkronkan saat update.
- `updated_by` diisi dengan `users.id` pengguna yang melakukan perubahan.
- `updated_at` diperbarui otomatis.
- Form Edit menyediakan Status Event. Perubahan `aktif → dibatalkan` mewajibkan alasan; backend mengisi `cancelled_by` dan `cancelled_at`.
- Event dibatalkan tetap editable dan dapat diaktifkan kembali. Reaktivasi tidak menghapus metadata pembatalan terakhir.

### 13.2 Edit data lama

Pengguna yang memiliki hak Edit dapat mengedit Closing Event lama **tanpa batas waktu**.

Tanggal event yang sudah lewat tidak membuat Closing Event otomatis read-only.

Hak edit ditentukan oleh permission pengguna, bukan umur data.

Karyawan Marketing biasa tetap tidak dapat edit, termasuk event yang dibuat sendiri.

---

## 14. Hapus Closing Event

- Hanya Super Admin.
- UI menampilkan dialog konfirmasi.
- Backend memverifikasi Super Admin.
- Closing Event dihapus.
- Relasi `closing_event_lokasi` ikut dibersihkan melalui foreign key cascade.
- Pengguna selain Super Admin menerima `403 Forbidden` apabila mencoba endpoint hapus.

Tidak ada role lain yang mendapat hak hapus.

---

## 15. Master Closing Event

Master hanya dapat dikelola oleh Super Admin.

### 15.1 Master PIC

Fitur:

- Lihat.
- Tambah.
- Edit.
- Hapus.

Struktur:

| Field | Ketentuan |
|---|---|
| `nama_pic` | Wajib, unik, maksimal 100 karakter |

### 15.2 Master Jenis Event

Fitur:

- Lihat.
- Tambah.
- Edit.
- Hapus.

Struktur:

| Field | Ketentuan |
|---|---|
| `jenis_event` | Wajib, unik, maksimal 150 karakter |

### 15.3 Master Lokasi

Fitur:

- Lihat.
- Tambah.
- Edit.
- Hapus.

Struktur:

| Field | Ketentuan |
|---|---|
| `nama_lokasi` | Wajib, unik, maksimal 150 karakter |

### 15.4 Delete master

PIC, Jenis Event, atau Lokasi yang masih digunakan oleh Closing Event tidak boleh dihapus jika tindakan tersebut merusak integritas referensial.

Tidak boleh cascade-delete Closing Event hanya karena sebuah master dihapus.

---

## 16. Struktur Database

### 16.1 Tabel `closing_event`

| Key | Atribut | Tipe / Ketentuan |
|---|---|---|
| PK | `id` | INT |
| FK | `pic_id` | INT → `pic.id` |
| FK | `event_id` | INT → `event.id` |
| FK | `created_by` | INT → `users.id` |
| FK | `updated_by` | INT NULL → `users.id` |
|  | `tanggal` | DATE |
|  | `tanggal_selesai` | DATE NULL; harus sama dengan atau setelah `tanggal` |
|  | `status_event` | ENUM (`aktif`, `dibatalkan`), default `aktif` |
|  | `konsumen` | VARCHAR(150) |
|  | `kontak` | VARCHAR(20) |
|  | `jam_kedatangan` | TIME |
|  | `additional` | TEXT NULL |
|  | `konsumsi` | BOOLEAN |
|  | `jumlah_pengunjung` | INT |
|  | `harga_total` | DECIMAL(15,2) |
|  | `panitia` | TEXT NULL |
|  | `alasan_pembatalan` | TEXT NULL |
|  | `cancelled_at` | TIMESTAMP NULL |
| FK | `cancelled_by` | INT NULL → `users.id`; null on user delete |
|  | `created_at` | TIMESTAMP |
|  | `updated_at` | TIMESTAMP |

### 16.2 Tabel `pic`

| Key | Atribut | Tipe |
|---|---|---|
| PK | `id` | INT |
| UNIQUE | `nama_pic` | VARCHAR(100) |

### 16.3 Tabel `event`

| Key | Atribut | Tipe |
|---|---|---|
| PK | `id` | INT |
| UNIQUE | `jenis_event` | VARCHAR(150) |

### 16.4 Tabel `lokasi`

| Key | Atribut | Tipe |
|---|---|---|
| PK | `id` | INT |
| UNIQUE | `nama_lokasi` | VARCHAR(150) |

### 16.5 Tabel `closing_event_lokasi`

| Key | Atribut | Tipe |
|---|---|---|
| PK, FK | `closing_event_id` | INT → `closing_event.id` |
| PK, FK | `lokasi_id` | INT → `lokasi.id` |

Composite primary key:

```sql
PRIMARY KEY (closing_event_id, lokasi_id)
```

Tujuan:

- mencegah lokasi yang sama dimasukkan dua kali pada Closing Event yang sama;
- mendukung satu Closing Event menggunakan lebih dari satu lokasi.

---

## 17. Relasi Database

```text
PIC 1 ─────── N Closing Event

Event 1 ───── N Closing Event

Closing Event N ───── N Lokasi
              melalui
       closing_event_lokasi

User 1 ─────── N Closing Event
        created_by / updated_by / cancelled_by
```

### 17.1 Aturan foreign key

- Penghapusan `closing_event` boleh cascade ke `closing_event_lokasi`.
- Penghapusan PIC/Event/Lokasi yang masih digunakan harus ditolak.
- `created_by`, `updated_by`, dan `cancelled_by` tidak boleh menggunakan cascade yang dapat menghapus Closing Event ketika akun User dihapus.

---

## 18. Master Data PIC Awal

| No | Nama PIC |
|---:|---|
| 1 | MARKOM |
| 2 | IPADA |
| 3 | PUTRI |
| 4 | ELIZA |
| 5 | AJENG |
| 6 | AMEL |
| 7 | DINDA |

Data ini digunakan sebagai initial master data dan setelah itu dapat dikelola Super Admin melalui CRUD Master Closing Event.

---

## 19. Master Data Jenis Event Awal

1. REKREASI
2. FAMILY GATHERING - PLATINUM
3. FAMILY GATHERING - GOLD
4. FAMILY GATHERING - SILVER
5. WISDU AGROWISATA
6. WISDU BATIK JUMPUTAN
7. WISDU MEWARNAI
8. LIBURAN SEKOLAH
9. MAKRAB
10. CAMPING
11. OLAHRAGA REKREASI
12. OUTING CLASS
13. SEKOLAH MINGGU
14. LDK
15. IBADAH KRISTIANI
16. KOMUNITAS
17. ARISAN
18. RAMADHAN CAMP
19. ENGLISH CAMP
20. ULANG TAHUN
21. PERPISAHAN SEKOLAH
22. EVENT MARKOM
23. FUN SWIMMING
24. SAFARI SANTRI
25. PTA
26. MANASIK HAJI
27. STAYCATION
28. SAHARA

Data ini digunakan sebagai initial master data dan setelah itu dapat dikelola Super Admin.

---

## 20. Master Data Lokasi Awal

1. ISTANA BALON
2. AREA CAMPING
3. LAPANGAN OUTBOUND
4. TENDA PANGGUNG
5. TRAP 4
6. LAPANGAN JWP
7. MMLT 1
8. GAMES POS 1
9. MM LT 2
10. GAZEBO OA
11. GAZEBO RESTO BESAR

Data ini digunakan sebagai initial master data dan setelah itu dapat dikelola Super Admin.

---

## 21. Aturan Bisnis

1. `konsumen` adalah nama klien/pihak yang menyewa atau memesan event.
2. `konsumsi = true` berarti konsumsi disediakan oleh Kampoeng Radja.
3. `konsumsi = false` berarti konsumsi tidak disediakan oleh Kampoeng Radja.
4. Satu Closing Event wajib memiliki satu PIC.
5. Satu Closing Event wajib memiliki satu Jenis Event.
6. Satu Closing Event wajib memiliki minimal satu Lokasi dan dapat memiliki lebih dari satu.
7. Panitia disimpan sebagai `TEXT NULL` karena dapat mencakup karyawan tetap, tenaga sementara, peserta magang, atau pihak lain.
8. `harga_total` menggunakan `DECIMAL(15,2)` dan bukan `FLOAT`.
9. `additional` opsional dan disimpan sebagai `TEXT NULL`.
10. `created_by` selalu merujuk ke `users.id`.
11. `updated_by` `NULL` saat create dan diisi `users.id` pada edit.
12. Pengguna yang tidak memiliki akses Closing Event tidak boleh memperoleh data Closing Event.
13. Karyawan Marketing biasa tidak dapat mengedit event walaupun merupakan pembuat record.
14. Event lama tetap dapat diedit oleh kelompok yang memiliki hak Edit.
15. Modul Closing Event tidak mengubah role global pengguna.
16. Status bisnis hanya `aktif` dan `dibatalkan`; event baru selalu `aktif`.
17. Pembatalan tidak menghapus record, membutuhkan alasan, dan mencatat actor serta waktu dari backend.
18. Event dibatalkan tetap dapat diedit serta diaktifkan kembali; metadata pembatalan terakhir dipertahankan.
19. Export memuat seluruh histori aktif/dibatalkan, sedangkan seluruh agregat Dashboard hanya menghitung event aktif.

---

## 22. Validasi Minimum

| Field | Validasi Minimum |
|---|---|
| `pic_id` | required, exists pada tabel `pic` |
| `event_id` | required, exists pada tabel `event` |
| `tanggal` | required, date |
| `tanggal_selesai` | nullable, date, after_or_equal `tanggal` |
| `konsumen` | required, string, max 150 |
| `kontak` | required, string, max 20 |
| `jam_kedatangan` | required, format waktu valid |
| `lokasi` | required, array, minimal satu lokasi |
| `lokasi.*` | distinct, exists pada tabel `lokasi` |
| `additional` | nullable, string |
| `konsumsi` | required, boolean |
| `jumlah_pengunjung` | required, integer, minimal 1 |
| `harga_total` | required, numeric/decimal, minimal 0 |
| `panitia` | nullable, string |
| `status_event` | jika dikirim pada update harus in `aktif,dibatalkan`; form Edit selalu mengirim status, request update lama tanpa field ini mempertahankan status existing |
| `alasan_pembatalan` | required ketika berubah dari aktif menjadi dibatalkan; nullable string selain itu |

Kontak konsumen tidak menggunakan regex khusus pada fase ini.

---

## 23. User Flow

### 23.1 Melihat daftar

1. Pengguna login.
2. Sistem membaca role, jabatan, dan departemen.
3. Sistem mengevaluasi capability Closing Event.
4. Jika pengguna mempunyai akses, menu Closing Event ditampilkan.
5. Pengguna membuka Data Closing Event.
6. Sistem secara default menampilkan event yang rentang pelaksanaannya beririsan dengan bulan berjalan.
7. Pengguna dapat mengganti bulan dan tahun.
8. Jika tidak memenuhi rule, akses ditolak.

### 23.2 Menambah Closing Event

1. Pengguna dengan capability Create membuka Data Closing Event.
2. Pengguna menekan **Tambah Closing Event**.
3. Sistem menampilkan form.
4. Pengguna memilih PIC, Jenis Event, dan minimal satu Lokasi.
5. Pengguna melengkapi data lainnya.
6. Backend memvalidasi authorization dan request.
7. Sistem menyimpan `closing_event` dan relasi lokasi dalam transaction.
8. `created_by` diisi pengguna login.
9. Pengguna diarahkan ke daftar/detail dengan notifikasi berhasil.

### 23.3 Melihat Detail

1. Pengguna yang memiliki akses membuka action **Detail**.
2. Backend memvalidasi akses modul.
3. Sistem menampilkan seluruh informasi event dan audit.
4. Action Edit/Hapus menyesuaikan capability pengguna.

### 23.4 Mengedit Closing Event

1. Pengguna dengan capability Update menekan **Edit**.
2. Sistem memuat data lama beserta lokasi.
3. Pengguna melakukan perubahan.
4. Backend memvalidasi authorization dan data.
5. Sistem memperbarui data dan sinkronisasi lokasi.
6. `updated_by` diisi pengguna yang melakukan perubahan.
7. Pengguna diarahkan kembali dengan notifikasi berhasil.

### 23.5 Menghapus Closing Event

1. Super Admin menekan **Hapus**.
2. Sistem menampilkan dialog konfirmasi.
3. Super Admin menyetujui.
4. Backend memverifikasi authorization Delete.
5. Closing Event dan relasi lokasinya dihapus.
6. Sistem menampilkan notifikasi berhasil.

### 23.6 Mengelola Master

1. Super Admin membuka **Master Closing Event**.
2. Super Admin memilih PIC, Jenis Event, atau Lokasi.
3. Super Admin dapat tambah, edit, atau hapus master.
4. Delete master yang masih digunakan ditolak.

### 23.7 Export Excel

1. Pengguna dengan capability Export memilih Bulan dan Tahun pada Data Closing Event.
2. Pengguna menekan **Export Excel**.
3. Backend memvalidasi capability serta periode.
4. Sistem mengambil seluruh Closing Event company-wide yang rentang pelaksanaannya beririsan dengan periode pilihan.
5. Jika data tersedia, sistem mengunduh workbook `.xlsx`; jika tidak, sistem menampilkan pesan periode kosong.

---

## 24. Authorization dan Keamanan

### Super Admin

- Lihat daftar.
- Detail.
- Tambah.
- Edit.
- Hapus.
- Export Excel.
- CRUD Master PIC.
- CRUD Master Jenis Event.
- CRUD Master Lokasi.

### Manajer

- Lihat daftar.
- Detail.
- Tambah.
- Edit.
- Export Excel.
- Tidak dapat hapus.
- Tidak dapat kelola master.

### Supervisor Marcom

Syarat:

```text
jabatan = Supervisor
AND departemen = Marcom
```

Hak:

- Lihat daftar.
- Detail.
- Tambah.
- Edit.
- Export Excel.

### Supervisor Marketing

Syarat:

```text
jabatan = Supervisor
AND departemen = Marketing
```

Hak:

- Lihat daftar.
- Detail.
- Tambah.
- Edit.
- Export Excel.

### Karyawan Marketing biasa

Syarat:

```text
departemen = Marketing
AND bukan Manajer
AND bukan Supervisor yang memperoleh rule lebih tinggi
```

Hak:

- Lihat daftar.
- Detail.
- Tambah.
- Export Excel.
- Tidak dapat edit.
- Tidak dapat hapus.

### Pengguna lainnya

- Tidak melihat menu Closing Event.
- Tidak dapat membuka route Closing Event.
- Tidak menerima data Closing Event dari endpoint.

---

## 25. Acceptance Criteria

- [ ] Pengguna tidak berhak tidak melihat menu Closing Event.
- [ ] Direct URL oleh pengguna tidak berhak ditolak backend.
- [ ] Super Admin dapat lihat, detail, tambah, edit, hapus.
- [ ] Super Admin dapat CRUD Master PIC, Jenis Event, dan Lokasi.
- [ ] Seluruh Manajer dapat lihat, detail, tambah, edit tetapi tidak hapus.
- [ ] SPV Marcom dapat lihat, detail, tambah, edit tetapi tidak hapus.
- [ ] SPV Marketing dapat lihat, detail, tambah, edit tetapi tidak hapus.
- [ ] Supervisor departemen lain tidak dapat mengakses Closing Event.
- [ ] Karyawan biasa Marketing dapat lihat, detail, tambah tetapi tidak edit/hapus.
- [ ] Semua kelompok yang memiliki akses Closing Event dapat Export Excel bulanan company-wide.
- [ ] Pengguna di luar kelompok akses mendapat 403 ketika membuka endpoint export secara langsung.
- [ ] Kontak dan Harga Total tidak tampil pada list, tetapi tetap tampil pada Detail dan Export Excel.
- [ ] Karyawan biasa Marcom tidak otomatis mendapat akses.
- [ ] Role global pengguna tidak diubah oleh Closing Event.
- [ ] Satu Closing Event dapat memiliki lebih dari satu lokasi.
- [ ] Minimal satu lokasi wajib dipilih.
- [ ] Lokasi yang sama tidak dapat diduplikasi.
- [ ] PIC berasal dari master PIC.
- [ ] Jenis Event berasal dari master Event.
- [ ] Lokasi berasal dari master Lokasi.
- [ ] Panitia boleh kosong.
- [ ] Additional boleh kosong.
- [ ] Harga menggunakan `DECIMAL(15,2)`.
- [ ] Konsumsi tersimpan sebagai boolean.
- [ ] `created_by` merekam creator.
- [ ] `updated_by` NULL saat create dan merekam editor terakhir setelah update.
- [ ] Delete Closing Event membersihkan relasi lokasi.
- [ ] Master yang masih digunakan tidak dapat dihapus secara destruktif.
- [ ] Default daftar menggunakan bulan berjalan berdasarkan tanggal pelaksanaan event.
- [ ] Filter bulan/tahun menggunakan overlap `tanggal` sampai `tanggal_selesai ?? tanggal`, bukan `created_at`.
- [ ] Sorting dalam bulan menggunakan tanggal pelaksanaan ascending.
- [ ] Pagination menggunakan 15 data per halaman.
- [ ] Event satu hari tetap satu record dengan `tanggal_selesai = NULL`.
- [ ] Event multi-hari tetap satu record, satu row list, satu row export, dan satu Harga Total.
- [ ] Seluruh event aktif yang rentangnya mencakup hari ini memiliki highlight dan label Sedang berlangsung.
- [ ] Event di luar rentang hari ini tidak memperoleh highlight berlangsung.
- [ ] Pengguna dengan hak Edit dapat mengedit event lama tanpa batas waktu.
- [ ] Event dibatalkan tidak memperoleh highlight berlangsung, tetap editable, dapat diaktifkan kembali, dan tetap ikut Export Excel.
- [ ] Dashboard, chart nilai, total event, dan total pengunjung hanya menghitung `status_event = aktif`.

---

## 26. Contoh Skenario Data

| Field | Contoh |
|---|---|
| PIC | MARKOM |
| Tanggal Mulai | 25 Agustus 2026 |
| Tanggal Selesai | NULL (event satu hari) |
| Konsumen | PT Contoh Jambi |
| Kontak | 081234567890 |
| Jam Kedatangan | 08:00 |
| Jenis Event | FAMILY GATHERING - GOLD |
| Lokasi | LAPANGAN OUTBOUND, TRAP 4, GAZEBO OA |
| Additional | Permintaan tambahan sound system dan meja registrasi |
| Konsumsi | Ya |
| Jumlah Pengunjung | 250 |
| Harga Total | 15000000.00 |
| Panitia | Tim Marketing, crew operasional, peserta magang |

Pada database:

- data utama disimpan sebagai satu record `closing_event`;
- tiga lokasi disimpan sebagai tiga relasi pada `closing_event_lokasi`;
- `created_by` berisi `users.id` creator;
- `updated_by` tetap `NULL` sampai terjadi edit.

---

## 27. Keputusan Final

| Area | Keputusan |
|---|---|
| Access control | Kombinasi role, jabatan, dan departemen |
| Super Admin | Full CRUD Closing Event + CRUD seluruh master |
| Manajer | View, Detail, Create, Edit |
| SPV Marcom | View, Detail, Create, Edit |
| SPV Marketing | View, Detail, Create, Edit |
| Karyawan Marketing | View, Detail, Create |
| Export Excel | Super Admin, Manajer, SPV Marcom, SPV Marketing, dan seluruh karyawan Marketing; company-wide berdasarkan overlap periode pelaksanaan |
| Kolom daftar | Tanggal, PIC, Konsumen, Jenis Event, Lokasi, Jam Kedatangan, Jumlah Pengunjung, Konsumsi, Additional, Panitia, Aksi |
| Kontak dan Harga Total | Tidak tampil di daftar; tetap ada pada Detail dan Export |
| Master PIC/Event/Lokasi | CRUD hanya Super Admin |
| Filter | Bulan dan tahun berdasarkan overlap `tanggal` sampai `tanggal_selesai ?? tanggal` |
| Default daftar | Bulan berjalan |
| Sorting | Tanggal pelaksanaan ASC dalam bulan |
| Pagination | 15 data per halaman |
| Highlight berlangsung | Inclusive range: `tanggal <= hari ini <= tanggal_selesai ?? tanggal` |
| Panitia | Opsional / `TEXT NULL` |
| Kontak | `required|string|max:20` |
| Edit data lama | Diperbolehkan bagi role/jabatan yang memiliki hak Edit |
| Detail Closing Event | Dibuat |
| Status Closing Event | Tidak digunakan |
| Role global akun | Tidak diubah oleh modul Closing Event |
| Additional | Opsional / `TEXT NULL` |
| Harga Total | `DECIMAL(15,2)` |
| Multi lokasi | Menggunakan `closing_event_lokasi` |
| PIC | Master independen, bukan FK langsung ke karyawan |
| Event multi-hari | Tetap satu record; `tanggal` = mulai, `tanggal_selesai` nullable |
| Harga Total | Satu nilai per Closing Event; tidak dibagi atau diduplikasi per hari |

---

**Akhir PRD Closing Event — FINAL**
