# PRODUCT REQUIREMENTS DOCUMENT (PRD)
# MODUL KPI — KAMPOENG RADJA

**Nama Sistem:** Sistem Informasi Terintegrasi Kampoeng Radja  
**Modul:** KPI (Key Performance Indicator)  
**Status Dokumen:** Working Baseline — Updated  
**Periode Dokumen:** September 2026  
**Catatan:** Dokumen ini merupakan baseline kebutuhan bisnis terbaru. Beberapa keputusan masih ditandai sebagai **OPEN DECISION** dan tidak boleh ditentukan sendiri oleh implementor/agent tanpa konfirmasi.

---

# 0. LEGENDA STATUS

## 0.1 Status Implementasi

| Penanda | Arti |
|---|---|
| ✅ **SUDAH** | Sudah tersedia/diimplementasikan pada sistem saat ini |
| 🟡 **SEBAGIAN** | Fondasi tersedia tetapi fungsi KPI belum selesai |
| ⏳ **BELUM** | Belum diimplementasikan |
| ⚠️ **OPEN DECISION** | Masih menunggu keputusan pimpinan/tim |

> **Penting:** Modul KPI secara keseluruhan **BELUM DIIMPLEMENTASIKAN**. Status ✅ hanya berlaku pada fondasi sistem yang sudah ada dan akan digunakan KPI, seperti data karyawan, atasan langsung, penempatan, role, dan foto tanda tangan.

## 0.2 Indikator Status Operasional KPI

| Indikator | Makna |
|---|---|
| ⚪ **Belum Diisi** | Record belum dikerjakan |
| 🔵 **Sedang Diproses** | Record sedang dalam periode pengerjaan |
| 🟡 **Menunggu TTD/Approval** | Pengisian selesai, menunggu pihak yang wajib menandatangani |
| 🟢 **Selesai** | Pengisian dan tanda tangan wajib lengkap |
| 🔴 **Tidak Mengisi / Terlewat** | Deadline lewat tanpa pengisian yang diwajibkan |
| 🔒 **Terkunci** | Record tidak dapat diedit lagi |
| ⏰ **Deadline Dekat** | Periode akan segera berakhir |
| ⚠️ **Perlu Tindakan** | Ada aksi yang harus dilakukan pengguna |
| 🤖 **Auto Submitted** | Sistem mengirim record otomatis karena deadline lewat |
| 🤖 **Auto Signed** | Sistem menandatangani otomatis karena deadline tanda tangan lewat |

---

# 1. LATAR BELAKANG

Proses KPI Kampoeng Radja sebelumnya berjalan menggunakan file spreadsheet dengan beberapa form terpisah, antara lain:

1. Daily Report
2. Kinerja Individu
3. Kinerja OPS / Indeks Prestasi Kerja Perorangan
4. Monthly Performance Appraisal (MPA)
5. Penilaian Absensi
6. Reward & Punishment
7. Nilai Akhir

Proses tersebut melibatkan beberapa pihak secara bergantian, yaitu karyawan, atasan langsung, atasan kedua, penilai bulanan, HRD/Direktur, serta pihak Super Admin.

Modul KPI pada Sistem Informasi Terintegrasi Kampoeng Radja harus memindahkan proses tersebut menjadi workflow terpusat yang:

- mengikuti periode secara tegas;
- mengikuti struktur atasan-bawahan perusahaan;
- mengetahui siapa yang bertanggung jawab mengisi;
- mengetahui siapa yang hanya memantau;
- mengetahui siapa yang wajib menandatangani;
- mengunci data berdasarkan approval dan deadline;
- menjaga seluruh bagian KPI tetap berada pada periode yang sama;
- menghasilkan Nilai Akhir secara otomatis.

**Status Implementasi:** ⏳ **BELUM**

---

# 2. TUJUAN

Modul KPI bertujuan untuk:

1. Memusatkan proses penilaian kinerja karyawan.
2. Menghilangkan ketergantungan pada banyak file Excel terpisah.
3. Mengatur deadline pengisian secara otomatis.
4. Memudahkan atasan memantau KPI seluruh bawahannya.
5. Mendukung approval/tanda tangan digital berbasis struktur organisasi.
6. Mendukung penilaian Monthly oleh penilai yang berubah setiap periode.
7. Menghubungkan aktivitas karyawan, target, penilaian umum, absensi, reward/punishment, dan Nilai Akhir.
8. Menyediakan akses administratif kepada Super Admin.
9. Menetapkan Direktur/HRD sebagai pihak yang secara operasional bertanggung jawab terhadap pengelolaan parameter dan penyelesaian administrasi KPI.
10. Memastikan nilai KPI siap sebelum proses penggajian.

---

# 3. FONDASI SISTEM YANG SUDAH TERSEDIA

| Fondasi | Status | Keterangan |
|---|---|---|
| Data Karyawan | ✅ SUDAH | Sudah tersedia |
| Jabatan | ✅ SUDAH | Wajib pada karyawan |
| Departemen | ✅ SUDAH | Nullable |
| Penempatan | ✅ SUDAH | Master data resmi |
| Atasan Langsung | ✅ SUDAH | Self-reference `karyawan` |
| Relasi Bawahan | ✅ SUDAH | Dapat diturunkan dari atasan langsung |
| Foto Tanda Tangan | ✅ SUDAH | Tersimpan pada profil karyawan |
| Role Sistem | ✅ SUDAH | `super_admin`, `admin`, `user` |
| Sinkronisasi Role Berdasarkan Jabatan | ✅ SUDAH | Sudah ada resolver |
| Menu KPI | ⏳ BELUM | Belum dibuat |
| Database KPI | ⏳ BELUM | Belum dibuat |
| Workflow KPI | ⏳ BELUM | Belum dibuat |
| Scheduler Deadline KPI | ⏳ BELUM | Belum dibuat |

---

# 4. ROLE SISTEM DAN KEWAJIBAN KPI

## 4.1 Mapping Role

| Jabatan | Role Sistem |
|---|---|
| Dirut | `super_admin` |
| Direktur | `super_admin` |
| Manajer / Manager | `super_admin` |
| SPV / Supervisor | `admin` |
| Marketing | `user` |
| Marcom / Markom | `user` |
| IT | `user` |
| Finance | `user` |
| Kasir | `user` |
| Operasional | `user` |
| General | `user` |
| Facility | `user` |

**Status:** ✅ **SUDAH**

## 4.2 Role Tidak Sama dengan Kewajiban KPI

### Dirut
- Role: `super_admin`
- Tidak berkewajiban mengisi KPI Individu.
- Menu **Individu harus di-hide**.
- Memiliki full access sebagai Super Admin.
- Tombol aksi administratif Super Admin tetap tersedia.
- Secara SOP bukan pelaksana normal pengisian administratif KPI.

**Status hide menu Individu:** ⏳ **BELUM**

### Direktur / HRD
- Role: `super_admin`
- Tidak berkewajiban mengisi KPI Individu.
- Menu **Individu harus di-hide**.
- Memiliki full access.
- Merupakan pihak yang **secara operasional bertanggung jawab** mengelola administrasi KPI.
- Menjadi pelaksana normal pengisian parameter KPI.
- Melanjutkan record Monthly setelah penilai selesai.
- Mengisi Penilaian Absensi.
- Mengisi Reward/Punishment.
- Menilai karyawan yang menjadi penilai bulanan.
- Dapat melengkapi kekurangan penilaian setelah periode MPA ditutup.
- Menyelesaikan/publish Monthly secara massal per periode.

**Status:** ⏳ **BELUM**

### Manajer
- Role: `super_admin`
- **Tetap wajib mengisi KPI Individu.**
- Menu Individu tetap tampil.
- Memiliki full access dan seluruh tombol aksi Super Admin.
- Karena memiliki bawahan, menu KPI-Karyawan tampil.
- Secara SOP bukan pelaksana normal administrasi KPI; tanggung jawab tersebut tetap pada Direktur/HRD.

**Status:** ⏳ **BELUM**

### SPV
- Role: `admin`
- Wajib mengisi KPI Individu.
- KPI-Karyawan tampil apabila memiliki bawahan.
- MPA muncul apabila ditetapkan sebagai penilai pada periode tertentu.

**Status:** ⏳ **BELUM**

### User
- Wajib mengisi KPI Individu.
- KPI-Karyawan hanya tampil jika mempunyai bawahan.
- MPA hanya aktif jika ditunjuk sebagai penilai periode.

**Status:** ⏳ **BELUM**

---

# 5. PRINSIP AKSES SUPER ADMIN

Semua Super Admin:

- Dirut
- Direktur
- Manajer

memiliki **full access secara sistem**.

Artinya tombol aksi administratif tetap dapat tampil untuk seluruh Super Admin.

Namun tanggung jawab operasional dibedakan melalui SOP:

```text
Capability Sistem
Dirut / Direktur / Manajer
        = Full Access

Pelaksana Normal Administrasi KPI
        = Direktur / HRD
```

Dengan demikian:

- Dirut tidak perlu di-hide tombol CRUD.
- Manajer tidak perlu di-hide tombol CRUD.
- Direktur/HRD tetap menjadi pihak yang seharusnya melakukan pengisian administratif.
- Sistem tidak menggunakan penyembunyian tombol untuk membedakan tanggung jawab bisnis Super Admin.

**Status:** ⏳ **BELUM**

---

# 6. STRUKTUR MENU KPI

```text
KPI
│
├── Individu
│   ├── Daily Report
│   ├── Kinerja Individu
│   ├── Kinerja OPS
│   ├── Monthly
│   └── Nilai Akhir
│
├── KPI-Karyawan
│
└── MPA
```

**Status:** ⏳ **BELUM**

---

# 7. VISIBILITAS MENU

| Jenis Pengguna | Individu | KPI-Karyawan | MPA |
|---|---:|---:|---:|
| Dirut | Hide | Full access | Full access |
| Direktur/HRD | Hide | Full access | Full access |
| Manajer | Tampil | Full access | Full access |
| SPV punya bawahan | Tampil | Tampil | Jika ditunjuk |
| SPV tanpa bawahan | Tampil | Hide | Jika ditunjuk |
| User punya bawahan | Tampil | Tampil | Jika ditunjuk |
| User tanpa bawahan | Tampil | Hide | Jika ditunjuk |

> MPA pada Super Admin selalu dapat diakses karena full access.  
> Pada Admin/User, MPA hanya aktif ketika pengguna ditunjuk sebagai penilai untuk suatu periode.

**Status:** ⏳ **BELUM**

---

# 8. PERIODISASI KPI

## 8.1 Prinsip Utama

Semua KPI bulanan wajib menggunakan **bulan performa**.

Contoh:

```text
Performa             : Agustus 2026
Pengisian            : September 2026
periode_bulan        : 8
periode_tahun        : 2026
```

Tidak boleh menyimpan sebagai periode September hanya karena form diisi pada September.

## 8.2 Siklus Bulanan

Contoh periode performa **Agustus 2026**:

```text
Agustus 2026
    ↓
31 Agustus 2026
Deadline penetapan penilai Agustus
    ↓
1–2 September
Kinerja Individu + Kinerja OPS
    ↓
1–5 September
MPA oleh penilai
    ↓
6–8 September
HRD melanjutkan Monthly
    ↓
s.d. 9 September
Tanda tangan
    ↓
9 September 23:59 WIB
Auto-sign
    ↓
10 September
Tanda tangan Nilai Akhir
```

Timezone:

**Asia/Jakarta (WIB)**

**Status periodisasi:** ⏳ **BELUM**

---

# 9. TIMELINE RESMI KPI

| Proses | Waktu |
|---|---|
| Daily Report | Setiap hari |
| Penetapan penilai periode | Maksimal hari terakhir bulan performa |
| Kinerja Individu | Tanggal 1–2 bulan berikutnya |
| Kinerja OPS | Tanggal 1–2 bulan berikutnya |
| MPA Penilai | Tanggal 1–5 |
| Monthly bagian HRD | Tanggal 6–8 |
| TTD Kinerja Individu | Maksimal tanggal 9 |
| TTD Kinerja OPS | Maksimal tanggal 9 |
| TTD Monthly | Setelah publish HRD sampai tanggal 9 |
| Auto-sign | Tanggal 9 pukul 23:59 WIB |
| TTD Nilai Akhir | Tanggal 10 |

**Status:** ⏳ **BELUM**

---

# 10. INDIVIDU — DAILY REPORT

## 10.1 Pengguna

Daily Report wajib diisi seluruh karyawan kecuali:

- Dirut
- Direktur

Manajer tetap wajib.

**Status:** ⏳ **BELUM**

## 10.2 Header Daily Report

Data identitas:

- Nama
- NIK
- Perusahaan
- Jabatan
- Departemen
- Penempatan
- Atasan Langsung
- Tanggal

Data identitas ditarik otomatis dari data Karyawan.

**Status fondasi:** ✅ **SUDAH**  
**Status integrasi KPI:** ⏳ **BELUM**

## 10.3 Tabel Daily Report

| Field | Pengisi |
|---|---|
| No | Sistem |
| Rincian Kegiatan | Karyawan |
| Keterangan | Karyawan |
| Bukti | Karyawan — upload foto |

**Status:** ⏳ **BELUM**

## 10.4 Batas Pengisian

Karyawan hanya boleh mengisi:

- hari ini;
- kemarin.

Contoh:

Tanggal saat ini: 3 September

```text
2 September → boleh isi/edit
3 September → boleh isi/edit
1 September → tidak boleh isi/edit
```

Pengecualian: record yang sudah approved tetap locked meskipun secara tanggal masih editable.

**Status:** ⏳ **BELUM**

## 10.5 Simpan dan Approval

Saat karyawan menyimpan:

```text
Draft/Isian
   ↓
Simpan
   ↓
Menunggu TTD Atasan Langsung
```

Simpan **tidak langsung mengunci** Daily.

Selama:
- belum approved; dan
- masih dalam periode edit,

karyawan masih boleh mengubah.

Setelah Atasan Langsung approve:

```text
Approved
   ↓
Locked
```

**Status:** ⏳ **BELUM**

## 10.6 Tanda Tangan Daily

Wajib:

- Atasan Langsung.

Sistem menyimpan minimal:

- `approved_by`
- `approved_at`
- status approval
- sumber manual/otomatis jika nanti diperlukan

**Status:** ⏳ **BELUM**

## 10.7 Indikator Daily pada KPI-Karyawan

Contoh:

```text
Jon
[ DR ⚠️ ] [ KI ] [ K-OPS ] [ M ] [ NA ]
```

Indikator dapat menunjukkan:

- ada Daily baru;
- ada Daily menunggu tanda tangan;
- seluruh Daily sudah ditandatangani;
- terdapat Daily tidak diisi.

**Status:** ⏳ **BELUM**

## 10.8 Tanda Tangani Semua

Pada:

```text
KPI-Karyawan
→ Bawahan Langsung
→ Daily Report
```

tersedia tombol:

**`Tanda Tangani Semua`**

Aturan:

1. Hanya digunakan terhadap Daily milik bawahan langsung.
2. Menandatangani seluruh Daily yang masih menunggu approval.
3. Record yang sudah approved tidak disentuh.
4. Wajib ada konfirmasi.
5. Setelah berhasil, indikator pending diperbarui.
6. Setiap record tetap menyimpan waktu dan identitas approver.

**Status:** ⏳ **BELUM**

## 10.9 Daily Tidak Diisi

Jika deadline lewat:

- record/hari ditandai `Tidak Mengisi`;
- tiga kali tidak mengisi → SP1.

⚠️ **OPEN DECISION:**
- tiga kali dihitung per bulan; atau
- akumulasi lintas bulan.

**Status:** ⏳ **BELUM**

---

# 11. INDIVIDU — KINERJA INDIVIDU

## 11.1 Pengguna

Diisi:

- seluruh karyawan wajib KPI Individu;
- kecuali Dirut dan Direktur.

Periode:

- tanggal 1–2 bulan berikutnya.

**Status:** ⏳ **BELUM**

## 11.2 Struktur

Header mengikuti data Karyawan.

Tabel:

| Kontribusi untuk Perusahaan | Evaluasi Diri |
|---|---:|
| Capaian Departemen | 1–100 |
| Perawatan Aset Kerja Sesuai Bidang | 1–100 |
| Kebersihan & Kerapihan Lingkungan Kerja | 1–100 |

## 11.3 Pembagian Pengisian

### Direktur / HRD
Mengelola atau menentukan:

- komponen Kontribusi untuk Perusahaan;
- indikator/kriteria apabila diperlukan;
- parameter administrasi terkait Kinerja Individu.

### Karyawan
Mengisi:

- angka Evaluasi Diri.

Input Evaluasi Diri:

**1–100**

**Status:** ⏳ **BELUM**

## 11.4 Bobot

| Komponen | Bobot |
|---|---:|
| Capaian Departemen | 70% |
| Perawatan Aset | 5% |
| Kebersihan & Kerapihan | 5% |
| **Total Maksimum Normal** | **80** |

Rumus:

```text
Nilai KI =
(Capaian Departemen × 0,70)
+ (Perawatan Aset × 0,05)
+ (Kebersihan & Kerapihan × 0,05)
```

Contoh:

```text
100 × 0,70 = 70
80 × 0,05  = 4
90 × 0,05  = 4,5

Total KI = 78,5
```

**Status perhitungan:** ⏳ **BELUM**

## 11.5 Deadline dan Auto Submit

Setelah tanggal 2:

- akses input karyawan ditutup;
- record otomatis submitted;
- jika belum diisi, record tetap terkirim dalam keadaan kosong;
- sistem memberi penanda **Tidak Mengisi**.

⚠️ Perlakuan numerik final untuk record kosong tidak boleh diasumsikan tanpa rule eksplisit.

**Status:** ⏳ **BELUM**

## 11.6 Tanda Tangan

Wajib:

- Atasan Langsung.

Setelah approved:

- record locked.

**Status:** ⏳ **BELUM**

---

# 12. INDIVIDU — KINERJA OPS / INDEKS PRESTASI KERJA PERORANGAN

## 12.1 Tujuan

Mengukur capaian pekerjaan/target operasional karyawan per bulan berdasarkan target yang telah ditentukan HRD/Direktur.

Periode input karyawan:

**tanggal 1–2 bulan berikutnya**

**Status:** ⏳ **BELUM**

## 12.2 Struktur Field

| Field | Sumber/Pengisi | Editable Karyawan |
|---|---|---:|
| No | Sistem | Tidak |
| KPI Item | HRD/Direktur | Tidak |
| Maintenance | HRD/Direktur | Tidak |
| Target Unit | HRD/Direktur | Tidak |
| Tanda (+/-) | HRD/Direktur | Tidak |
| Beban Target (%) | Sistem | Tidak |
| Sumber Data | Sistem dari Jabatan | Tidak |
| Frekuensi | HRD/Direktur | Tidak |
| Bulan | Sistem dari Periode KPI | Tidak |
| Target Bulanan | Berdasarkan konfigurasi target | Tidak |
| Hasil | Karyawan | Ya |
| Aktivitas Pencapaian — teks | Karyawan | Ya |
| Aktivitas Pencapaian — foto | Karyawan | Ya |

**Status:** ⏳ **BELUM**

## 12.3 Field yang Diisi HRD/Direktur

HRD mengisi:

1. KPI Item
2. Maintenance
3. Target Unit
4. Tanda (+/-)
5. Frekuensi

Semua Super Admin memiliki tombol aksi, tetapi Direktur/HRD merupakan pelaksana normal sesuai SOP.

**Status:** ⏳ **BELUM**

## 12.4 Field Otomatis

### Sumber Data

Diambil dari:

```text
karyawan.jabatan
```

Contoh:

```text
Merdiansyah
Jabatan = IT
Sumber Data = IT
```

### Bulan

Diambil dari periode KPI.

Contoh:

```text
periode_bulan = 8
→ AGUSTUS
```

### Beban Target

Dihitung otomatis dari proporsi `Target Unit`.

Rumus:

```text
Beban Target Item =
(Target Unit Item / Total Target Unit Seluruh Item)
× 10
```

Total Beban Target normal:

```text
10%
```

#### Contoh A

Target Unit:

```text
2, 2, 2, 2, 2
```

Total = 10

Masing-masing:

```text
2 / 10 × 10 = 2%
```

Hasil:

```text
2%, 2%, 2%, 2%, 2%
Total = 10%
```

#### Contoh B

Target Unit:

```text
5, 1, 1, 1
```

Total = 8

Hasil:

```text
5 / 8 × 10 = 6,25%
1 / 8 × 10 = 1,25%
1 / 8 × 10 = 1,25%
1 / 8 × 10 = 1,25%

Total = 10%
```

Perhitungan otomatis menjadi default.

Manual hanya menjadi fallback apabila suatu saat ada kebutuhan bisnis yang tidak dapat direpresentasikan formula.

**Status:** ⏳ **BELUM**

## 12.5 Pengisian oleh Karyawan

Karyawan hanya mengisi:

- Hasil
- Aktivitas Pencapaian berupa teks
- Bukti Aktivitas Pencapaian berupa foto

Target dan parameter tidak dapat diubah karyawan.

**Status:** ⏳ **BELUM**

## 12.6 Formula Nilai Kinerja OPS

Formula baseline berdasarkan pola worksheet:

```text
Nilai Item =
(Hasil / Target)
× Beban Target
```

```text
Nilai Kinerja OPS =
SUM(Nilai Item)
```

Contoh:

```text
Target = 2
Hasil = 2
Beban = 2

Nilai Item = 2
```

Jika lima item menghasilkan:

```text
2 + 2 + 2 + 0 + 2
```

maka:

```text
Nilai Kinerja OPS = 8
```

### Capaian di Atas Target

Nilai **tidak perlu di-clamp** ke maksimum 10 apabila hasil aktual menurut formula melebihi target.

Kelebihan capaian dapat tetap disimpan dan dapat berhubungan dengan insentif tambahan di luar penilaian normal.

⚠️ **OPEN DECISION:** fungsi matematis kolom `Tanda (+/-)` belum dijelaskan secara final dan tidak boleh diasumsikan implementor.

**Status formula:** 🟡 **SEBAGIAN TERDEFINISI**

## 12.7 Deadline

Lewat tanggal 2:

- auto-submit;
- record kosong tetap dikirim;
- diberi indikator Tidak Mengisi.

**Status:** ⏳ **BELUM**

## 12.8 Tanda Tangan

Wajib:

1. Karyawan bersangkutan
2. Atasan Langsung

Nilai belum dianggap siap diteruskan ke Nilai Akhir sebelum tanda tangan lengkap atau terkena auto-sign.

**Status:** ⏳ **BELUM**

---

# 13. KPI-KARYAWAN

## 13.1 Tujuan

KPI-Karyawan digunakan untuk:

- melihat KPI bawahan;
- melihat status pengisian;
- melihat status tanda tangan;
- melakukan approval sesuai kewenangan;
- memantau bawahan langsung dan tidak langsung;
- memberikan akses administratif seluruh karyawan kepada Super Admin.

**Status:** ⏳ **BELUM**

## 13.2 Hirarki

Dihitung dari:

```text
karyawan.atasan_langsung_id
```

Contoh:

```text
Naftalio
    ↓
Surwono
    ↓
Jon
```

Untuk Naftalio:

```text
Surwono = Bawahan Langsung
Jon      = Bawahan Kedua
```

Untuk Surwono:

```text
Jon = Bawahan Langsung
```

**Fondasi Atasan Langsung:** ✅ **SUDAH**  
**Traversal KPI:** ⏳ **BELUM**

## 13.3 Kategori

Untuk pengguna yang mempunyai bawahan:

```text
KPI-Karyawan
├── Bawahan Langsung
├── Bawahan Kedua
├── Bawahan Ketiga
├── Bawahan Keempat
└── dst.
```

### Khusus Super Admin

Tambahan kategori:

```text
Seluruh Karyawan
```

Tujuan:

- membuka KPI seluruh karyawan;
- memudahkan HRD melakukan administrasi KPI;
- tidak dibatasi hubungan bawahan pribadi Super Admin.

Semua Super Admin dapat membuka kategori tersebut.

**Status:** ⏳ **BELUM**

## 13.4 Kolom Daftar

Minimal:

- Nama
- Jabatan
- Departemen
- Penempatan
- Level bawahan
- Status KPI
- CTA

## 13.5 CTA

Setiap karyawan memiliki:

```text
[ DR ] [ KI ] [ K-OPS ] [ M ] [ NA ]
```

Keterangan:

- `DR` = Daily Report
- `KI` = Kinerja Individu
- `K-OPS` = Kinerja OPS
- `M` = Monthly
- `NA` = Nilai Akhir

**Status:** ⏳ **BELUM**

## 13.6 Monitoring vs Approval

### Monitoring
Atasan dapat melihat seluruh bawahan dalam rantai hirarki.

### Approval
Approval tetap mengikuti jenis KPI:

| KPI | Pihak Approval |
|---|---|
| Daily | Atasan Langsung |
| Kinerja Individu | Atasan Langsung |
| Kinerja OPS | Karyawan + Atasan Langsung |
| Monthly | HRD + Karyawan + Atasan Langsung + Atasan Kedua |
| Nilai Akhir | Atasan Langsung |

Atasan tingkat lebih jauh tetap dapat melihat tetapi tidak otomatis menjadi approver.

**Status:** ⏳ **BELUM**

---

# 14. MPA — MONTHLY PERFORMANCE APPRAISAL

## 14.1 Konsep

MPA dan Monthly merupakan **satu kesatuan data**.

Perbedaannya:

```text
MPA
= workspace untuk memberi penilaian

Monthly pada Individu
= tampilan hasil penilaian milik karyawan
```

Tidak dibuat record penilaian terpisah untuk masing-masing aktor.

**Status:** ⏳ **BELUM**

---

# 15. PENETAPAN PENILAI MPA

## 15.1 Lokasi

Penetapan penilai dilakukan langsung pada:

```text
KPI → MPA
```

melalui mini fitur di bagian atas halaman.

Tidak perlu menu konfigurasi penilai yang terpisah.

**Status:** ⏳ **BELUM**

## 15.2 Aturan

1. Satu periode/bulan hanya mempunyai **1 penilai utama**.
2. Penilai menilai seluruh karyawan yang menjadi objek MPA.
3. Penilai dapat berganti setiap periode.
4. Super Admin dapat menetapkan penilai.
5. Penilai dapat disiapkan untuk beberapa periode ke depan.
6. Setiap periode tetap mempunyai deadline assignment masing-masing.
7. Penilai untuk suatu periode harus sudah ditentukan maksimal pada hari terakhir bulan performa.
8. Setelah masuk periode MPA tanggal 1, assignment periode tersebut dikunci.
9. Penetapan lebih awal tidak membuat akses penilaian langsung aktif.

**Status:** ⏳ **BELUM**

## 15.3 Contoh

```text
Periode: Agustus 2026
Penilai: Jon
Deadline penetapan: 31 Agustus 2026

1–5 September:
Jon menilai performa Agustus
```

Tidak boleh terjadi:

```text
Penilai ditunjuk untuk Agustus
tetapi record tersimpan sebagai September
```

---

# 16. AKSES MPA PENILAI

## 16.1 Daftar Objek Penilaian

Daftar berisi seluruh karyawan kecuali:

- Dirut
- Direktur
- penilai sendiri

Penilai boleh menilai karyawan dengan level jabatan lebih tinggi.

Contoh:

```text
Jon (Teknisi)
→ dapat menilai Naftalio (Manajer)
```

**Status:** ⏳ **BELUM**

## 16.2 Kolom MPA

Minimal:

- Nama
- Jabatan
- Departemen
- Penempatan
- Status
- Aksi

Aksi:

```text
Beri Nilai
```

Setelah selesai:

```text
Sudah Dinilai
```

dan tombol dinonaktifkan.

**Status:** ⏳ **BELUM**

---

# 17. PENILAIAN OLEH PENILAI MPA

Penilai hanya melihat bagian yang menjadi tanggung jawabnya.

## 17.1 Kinerja Operasional

Satu indikator:

**Kinerja Operasional**

Range:

| Nilai | Kriteria |
|---:|---|
| 1–15 | Di bawah rata-rata |
| 16–30 | Mencapai target / standar |
| 31–45 | Luar biasa |

Penilai mengisi:

- Angka Penilaian
- Keterangan/Bukti pendukung

## 17.2 Penilaian Umum

Dimensi:

A. Sikap Kerja  
B. Team Work  
C. Inisiatif  
D. Kepemimpinan / Potensi Kepemimpinan

Setiap dimensi menggunakan:

```text
1–15
16–30
31–45
```

## 17.3 Level Karyawan

### Tidak memiliki bawahan / Level 1

Dinilai:

1. Kinerja Operasional
2. Sikap Kerja
3. Team Work
4. Inisiatif

Rumus:

```text
(
  (
    Kinerja Operasional
    + Sikap Kerja
    + Team Work
    + Inisiatif
  ) / 4
) / 45 × 5
```

### Memiliki bawahan / Level 2 ke atas

Dinilai:

1. Kinerja Operasional
2. Sikap Kerja
3. Team Work
4. Inisiatif
5. Kepemimpinan

Rumus:

```text
(
  (
    Kinerja Operasional
    + Sikap Kerja
    + Team Work
    + Inisiatif
    + Kepemimpinan
  ) / 5
) / 45 × 5
```

Nilai normal maksimal Penilaian Umum:

**5 poin**

**Status:** ⏳ **BELUM**

## 17.4 Catatan Performance dan Coaching

Penilai juga mengisi:

- Penjelasan Berkaitan dengan Performance
- Rencana Perbaikan / Coaching / Counseling

Bagian ini **bukan** diisi HRD dalam alur normal.

**Status:** ⏳ **BELUM**

---

# 18. DEADLINE MPA

Periode penilai:

**tanggal 1–5**

Setelah deadline:

- akses penilai ditutup;
- tidak ada toleransi;
- penilai tidak dapat membuka kembali input;
- tidak ada reopen otomatis.

Jika terdapat kekurangan:

```text
Penilai
→ datang/berkoordinasi dengan HRD
→ HRD melengkapi melalui akun HRD
```

**Status:** ⏳ **BELUM**

---

# 19. MPA MILIK PENILAI

Penilai tidak boleh menilai dirinya sendiri.

Namun penilai tetap merupakan objek KPI.

Maka:

> Monthly/MPA milik karyawan yang sedang menjadi penilai **diisi/dinilai oleh Direktur/HRD**.

Contoh:

```text
Jon = Penilai Agustus

Jon tidak menilai Jon
↓
HRD menilai Jon
```

Ini merupakan rule wajib.

**Status:** ⏳ **BELUM**

---

# 20. AKSES SUPER ADMIN DI MPA

Semua Super Admin memiliki full access:

- Dirut
- Direktur
- Manajer

Seluruh tombol aksi dapat tersedia.

Namun:

- Direktur/HRD adalah pihak yang berkewajiban menjalankan pengisian administratif;
- Dirut/Manajer bukan pelaksana normal, walaupun capability UI tersedia.

**Status:** ⏳ **BELUM**

---

# 21. LANJUTAN MONTHLY OLEH HRD

Setelah penilai menyelesaikan MPA:

```text
Penilai
→ Penilaian Umum
→ Performance
→ Coaching

          ↓

HRD
→ Absensi
→ Reward/Punishment
```

HRD melanjutkan **record yang sama**.

**Status:** ⏳ **BELUM**

---

# 22. PENILAIAN ABSENSI

Baseline:

**Manual oleh Direktur/HRD.**

| Kriteria | Kode | Potongan |
|---|---|---:|
| Urusan Pribadi | P1 | 0,5 |
| Datang Lambat | DL | 0,3 |
| Pulang Cepat | PC | 0,3 |
| Lupa Catat | LC | 0,3 |
| Mangkir | M | 3 |

Rumus:

```text
Total per Kriteria =
Potongan × Jumlah Hari
```

```text
Nilai Absensi =
(10 - SUM Total Potongan) × 0,5
```

Nilai normal maksimum:

**5**

Tidak diterapkan clamp minimum/maximum tambahan selama belum ada keputusan pimpinan.

**Status:** ⏳ **BELUM**

---

# 23. REWARD & PUNISHMENT

Diisi HRD/Direktur.

| Kriteria | Angka |
|---|---:|
| Major Award | +7 |
| Minor Award | +3 |
| Minor Demerit | -4 |
| Major Demerit | -8 |

Rumus:

```text
Total Baris =
Angka × Jumlah
```

```text
Nilai R/P =
SUM seluruh Total Baris
```

Bagian ini opsional.

**Status:** ⏳ **BELUM**

---

# 24. PENYELESAIAN MONTHLY OLEH HRD

HRD tidak publish karyawan satu per satu.

Workflow:

```text
Penilai selesai seluruh MPA
        ↓
HRD buka seluruh Monthly
        ↓
HRD isi Absensi seluruh karyawan
        ↓
HRD isi Reward/Punishment seluruh karyawan
        ↓
Pastikan satu periode selesai
        ↓
Klik Simpan / Selesaikan Monthly
        ↓
Seluruh Monthly dipublish sekaligus
```

Baru setelah aksi tersebut:

- Monthly muncul/update pada KPI Individu masing-masing;
- status Monthly menjadi menunggu tanda tangan;
- TTD HRD otomatis tercatat.

**Status:** ⏳ **BELUM**

---

# 25. TANDA TANGAN MONTHLY

Monthly membutuhkan:

1. HRD / Direktur
2. Atasan Kedua
3. Atasan Langsung
4. Karyawan

### HRD
Tercatat otomatis ketika HRD menyelesaikan Monthly.

### Atasan 1 dan Atasan 2
Tidak diwajibkan berurutan pada baseline saat ini.

### Karyawan
Menandatangani Monthly miliknya.

**Status:** ⏳ **BELUM**

---

# 26. DEFINISI ATASAN KEDUA

Baseline:

```text
Atasan Kedua
=
Atasan Langsung dari Atasan Langsung
```

Contoh:

```text
Jon
↓
Surwono
↓
Naftalio

Surwono = Atasan Langsung Jon
Naftalio = Atasan Kedua Jon
```

⚠️ **OPEN DECISION:** perlakuan jika suatu karyawan tidak mempunyai Atasan Kedua yang valid.

---

# 27. INDIKATOR APPROVAL MONTHLY

Contoh pada KPI-Karyawan:

```text
Jon
[ DR ] [ KI ] [ K-OPS ] [ M ⚠️ ] [ NA ]
```

Untuk Jon:

- Surwono melihat Jon pada Bawahan Langsung → indikator TTD Monthly.
- Naftalio melihat Jon pada Bawahan Kedua → indikator TTD Monthly.

Status detail dapat menunjukkan:

```text
HRD              ✅
Karyawan         ⏳
Atasan Langsung  ✅
Atasan Kedua     ⏳
```

**Status:** ⏳ **BELUM**

---

# 28. AUTO-SIGN

Deadline:

**Tanggal 9 pukul 23:59 WIB**

Berlaku untuk tanda tangan yang masih pending pada:

- Kinerja Individu
- Kinerja OPS
- Monthly

Tidak berlaku pada:

- Daily Report
- Nilai Akhir

Aksi:

```text
Pending Signature
        ↓
9 23:59
        ↓
Auto Signed
        ↓
Nilai dapat diteruskan
```

Sistem harus menyimpan bahwa tanda tangan berasal dari mekanisme otomatis.

**Status:** ⏳ **BELUM**

---

# 29. NILAI AKHIR

## 29.1 Komponen

| Komponen | Bobot/Peran |
|---|---:|
| Kinerja Individu | 80% |
| Kinerja OPS | 10% normal |
| Penilaian Umum | 5% normal |
| Disiplin/Absensi | 5% normal |
| Reward/Punishment | Tambah/kurang |

Rumus:

```text
Nilai Akhir =
Kinerja Individu
+ Kinerja OPS
+ Penilaian Umum
+ Disiplin/Absensi
+ Reward/Punishment
```

**Status:** ⏳ **BELUM**

## 29.2 Nilai Dapat Melebihi 100

Sistem **tidak melakukan clamp maksimum 100**.

Jika capaian atau Reward membuat nilai:

```text
107
```

maka nilai tetap:

```text
107
```

Kelebihan nilai dapat berkaitan dengan insentif tambahan di luar komponen KPI normal.

## 29.3 Kategori

| Nilai | Kategori |
|---:|---|
| `>= 90` | Sangat Baik |
| `80 – <90` | Baik |
| `70 – <80` | Cukup |
| `60 – <70` | Kurang |
| `<60` | Sangat Kurang |

Dengan demikian nilai >100 tetap masuk kategori **Sangat Baik**.

**Status:** ⏳ **BELUM**

---

# 30. TANDA TANGAN NILAI AKHIR

Wajib:

- Atasan Langsung.

Waktu:

**Tanggal 10**

Flow:

```text
Semua komponen siap
↓
Nilai Akhir terbentuk
↓
Tanggal 10
↓
Atasan Langsung TTD
↓
Final
```

**Status:** ⏳ **BELUM**

---

# 31. MATRIKS TANDA TANGAN

| Jenis KPI | Karyawan | Atasan Langsung | Atasan Kedua | HRD |
|---|:---:|:---:|:---:|:---:|
| Daily Report | - | ✅ | - | - |
| Kinerja Individu | - | ✅ | - | - |
| Kinerja OPS | ✅ | ✅ | - | - |
| Monthly | ✅ | ✅ | ✅ | ✅ |
| Nilai Akhir | - | ✅ | - | - |

**Status:** ⏳ **BELUM**

---

# 32. CRUD ADMINISTRATIF KPI

Semua Super Admin memiliki tombol CRUD.

Pelaksana normal:

**Direktur/HRD**

CRUD digunakan untuk parameter seperti:

- KPI Item
- Maintenance
- Target Unit
- Tanda
- Frekuensi
- Kriteria
- Indikator
- kontribusi perusahaan
- target/capaian
- parameter KPI lainnya

Prinsip:

```text
Full Capability = semua Super Admin
Operational Responsibility = Direktur / HRD
```

**Status:** ⏳ **BELUM**

---

# 33. SHARED RECORD

Semua proses harus berbagi record yang terhubung.

Contoh Monthly:

```text
Periode Agustus 2026
Karyawan Jon
        │
        ├── Penilai mengisi Penilaian Umum
        │
        ├── Penilai mengisi Performance
        │
        ├── Penilai mengisi Coaching
        │
        ├── HRD mengisi Absensi
        │
        ├── HRD mengisi Reward/Punishment
        │
        ├── HRD Publish
        │
        ├── Jon TTD
        │
        ├── Surwono TTD
        │
        └── Naftalio TTD
```

Bukan:

```text
Record Penilai
Record HRD
Record Atasan
Record Karyawan
```

yang terpisah.

Kunci utama konseptual:

```text
karyawan_id
periode_bulan
periode_tahun
```

**Status:** ⏳ **BELUM**

---

# 34. STATUS RECORD

Status yang disarankan:

```text
draft
submitted
waiting_approval
approved
auto_submitted
auto_signed
not_filled
locked
completed
```

Untuk MPA dapat ditambah:

```text
unassigned
scheduled
open
completed
closed
```

Nama final dapat menyesuaikan implementasi, tetapi makna status tidak boleh hilang.

**Status:** ⏳ **BELUM**

---

# 35. INDIKATOR UI KPI-KARYAWAN

Contoh:

```text
Jon — Teknisi

[ DR 🟡 ] [ KI 🟢 ] [ K-OPS ⚠️ ] [ M 🟡 ] [ NA ⚪ ]
```

Detail:

### DR 🟡
Ada Daily menunggu TTD.

### KI 🟢
Sudah selesai.

### K-OPS ⚠️
Ada tanda tangan belum lengkap.

### M 🟡
Monthly sudah publish tetapi belum semua TTD.

### NA ⚪
Nilai Akhir belum tersedia.

**Status:** ⏳ **BELUM**

---

# 36. NOTIFIKASI / PENANDA TINDAKAN

Sistem perlu memberikan indikator ketika:

- Daily bawahan perlu ditandatangani;
- Kinerja Individu bawahan perlu ditandatangani;
- Kinerja OPS perlu ditandatangani;
- Monthly bawahan langsung perlu TTD;
- Monthly bawahan kedua perlu TTD;
- MPA masih memiliki karyawan belum dinilai;
- deadline mendekat;
- record auto-submitted;
- record auto-signed;
- HRD perlu menyelesaikan Monthly;
- Nilai Akhir siap ditandatangani.

**Status:** ⏳ **BELUM**

---

# 37. RULE DEADLINE

Deadline bersifat **hard deadline**.

Artinya sistem harus:

- menutup input;
- mengganti status;
- menjalankan auto-submit;
- menjalankan auto-sign;
- mencegah edit setelah lock.

Deadline tidak hanya berupa teks pemberitahuan.

**Status:** ⏳ **BELUM**

---

# 38. ACCEPTANCE CRITERIA — MENU & ACCESS

- [ ] Dirut tidak memiliki menu Individu.
- [ ] Direktur tidak memiliki menu Individu.
- [ ] Manajer memiliki menu Individu.
- [ ] Manajer tetap wajib KPI pribadi.
- [ ] Pengguna tanpa bawahan tidak melihat KPI-Karyawan.
- [ ] Pengguna dengan bawahan melihat KPI-Karyawan.
- [ ] Semua Super Admin dapat membuka seluruh KPI.
- [ ] Semua Super Admin melihat tombol aksi administratif.
- [ ] Direktur/HRD dicatat sebagai pelaksana normal administrasi.
- [ ] Super Admin mempunyai kategori Seluruh Karyawan.

---

# 39. ACCEPTANCE CRITERIA — DAILY

- [ ] Daily hanya dapat diisi hari ini dan kemarin.
- [ ] Daily memiliki upload Bukti.
- [ ] Simpan tidak langsung lock.
- [ ] Approval Atasan Langsung menyebabkan lock.
- [ ] Indikator pending muncul pada KPI-Karyawan.
- [ ] Tanda Tangani Semua tersedia untuk bawahan langsung.
- [ ] Bulk sign tidak mengubah Daily yang sudah approved.
- [ ] Tidak mengisi diberi indikator.
- [ ] Tiga kali tidak mengisi dapat ditindaklanjuti SP1 setelah rule final dikunci.

---

# 40. ACCEPTANCE CRITERIA — KINERJA INDIVIDU

- [ ] Hanya aktif tanggal 1–2.
- [ ] Evaluasi diri 1–100.
- [ ] Formula bobot menghasilkan maksimum normal 80.
- [ ] Lewat deadline auto-submit.
- [ ] Record kosong ditandai Tidak Mengisi.
- [ ] TTD Atasan Langsung wajib.
- [ ] Setelah approval record locked.

---

# 41. ACCEPTANCE CRITERIA — KINERJA OPS

- [ ] HRD dapat mengelola KPI Item.
- [ ] HRD dapat mengelola Maintenance.
- [ ] HRD dapat mengelola Target Unit.
- [ ] HRD dapat mengelola Tanda.
- [ ] HRD dapat mengelola Frekuensi.
- [ ] Beban Target dihitung otomatis.
- [ ] Total Beban Target = 10% pada target normal.
- [ ] Sumber Data otomatis dari Jabatan.
- [ ] Bulan otomatis dari Periode.
- [ ] Karyawan hanya mengisi Hasil.
- [ ] Karyawan mengisi Aktivitas Pencapaian teks.
- [ ] Karyawan upload foto pencapaian.
- [ ] Lewat tanggal 2 auto-submit.
- [ ] TTD Karyawan wajib.
- [ ] TTD Atasan Langsung wajib.
- [ ] Nilai di atas target tidak dipotong hanya karena melebihi bobot normal.

---

# 42. ACCEPTANCE CRITERIA — KPI-KARYAWAN

- [ ] Hirarki dibangun dari Atasan Langsung.
- [ ] Bawahan Langsung tampil.
- [ ] Bawahan Kedua tampil.
- [ ] Tingkat selanjutnya tampil dinamis.
- [ ] Ada CTA DR/KI/K-OPS/M/NA.
- [ ] Super Admin dapat melihat Seluruh Karyawan.
- [ ] Monitoring bawahan tidak sama dengan hak approval.
- [ ] Indikator tindakan muncul sesuai kewenangan.

---

# 43. ACCEPTANCE CRITERIA — MPA

- [ ] Penilai ditetapkan pada halaman MPA.
- [ ] Satu periode hanya satu penilai utama.
- [ ] Penilai dapat disiapkan untuk beberapa periode.
- [ ] Assignment periode dikunci saat periode MPA dimulai.
- [ ] MPA hanya editable penilai tanggal 1–5.
- [ ] Penilai melihat semua objek kecuali Dirut/Direktur/diri sendiri.
- [ ] Penilai boleh menilai level jabatan lebih tinggi.
- [ ] Tombol Beri Nilai mati setelah selesai.
- [ ] Penilai mengisi Performance.
- [ ] Penilai mengisi Coaching.
- [ ] Deadline lewat menutup akses.
- [ ] Tidak ada toleransi.
- [ ] HRD dapat melengkapi kekurangan.
- [ ] HRD menilai si penilai.

---

# 44. ACCEPTANCE CRITERIA — MONTHLY HRD

- [ ] HRD melihat hasil MPA penilai.
- [ ] HRD mengisi Absensi manual.
- [ ] HRD mengisi Reward/Punishment.
- [ ] HRD tidak wajib mengisi Performance/Coaching karena bagian tersebut milik penilai.
- [ ] HRD melengkapi seluruh karyawan sebelum publish.
- [ ] Publish dilakukan sekaligus per periode.
- [ ] TTD HRD otomatis saat publish.
- [ ] Monthly masuk KPI Individu masing-masing setelah publish.
- [ ] Monthly menunggu TTD karyawan, Atasan 1, dan Atasan 2.

---

# 45. ACCEPTANCE CRITERIA — DEADLINE & FINAL

- [ ] KI auto-submit setelah tanggal 2.
- [ ] K-OPS auto-submit setelah tanggal 2.
- [ ] MPA ditutup setelah tanggal 5.
- [ ] HRD phase tanggal 6–8.
- [ ] TTD selesai maksimal tanggal 9.
- [ ] Auto-sign berjalan tanggal 9 23:59 WIB.
- [ ] Daily tidak terkena auto-sign bulanan.
- [ ] Nilai Akhir tersedia setelah komponen memenuhi rule.
- [ ] Nilai Akhir ditandatangani Atasan Langsung tanggal 10.
- [ ] Nilai >100 tetap disimpan.
- [ ] Kategori `>=90` tetap Sangat Baik.

---

# 46. STATUS IMPLEMENTASI TERKINI

| Area | Status |
|---|---|
| Data Karyawan | ✅ SUDAH |
| Jabatan | ✅ SUDAH |
| Departemen | ✅ SUDAH |
| Penempatan | ✅ SUDAH |
| Atasan Langsung | ✅ SUDAH |
| Foto Tanda Tangan | ✅ SUDAH |
| Role Mapping | ✅ SUDAH |
| Relasi dasar Atasan/Bawahan | ✅ SUDAH |
| Menu KPI | ⏳ BELUM |
| Individu | ⏳ BELUM |
| Daily Report | ⏳ BELUM |
| Kinerja Individu | ⏳ BELUM |
| Kinerja OPS | ⏳ BELUM |
| KPI-Karyawan | ⏳ BELUM |
| MPA | ⏳ BELUM |
| Penetapan Penilai | ⏳ BELUM |
| Monthly | ⏳ BELUM |
| Absensi Monthly | ⏳ BELUM |
| Reward/Punishment | ⏳ BELUM |
| Approval KPI | ⏳ BELUM |
| Bulk Sign Daily | ⏳ BELUM |
| Auto Submit | ⏳ BELUM |
| Auto Sign | ⏳ BELUM |
| Nilai Akhir | ⏳ BELUM |
| CRUD Parameter KPI | ⏳ BELUM |
| Indikator Status | ⏳ BELUM |
| Scheduler KPI | ⏳ BELUM |

---

# 47. OPEN DECISIONS

Hal berikut **belum boleh diputuskan agent secara mandiri**:

1. Tiga kali Tidak Mengisi Daily:
   - per bulan; atau
   - akumulatif.
2. Fungsi matematis `Tanda (+/-)` pada Kinerja OPS.
3. Perlakuan nilai numerik Kinerja Individu/Kinerja OPS yang auto-submit dalam keadaan kosong.
4. Perlakuan tanda tangan Monthly jika Atasan Kedua tidak tersedia.
5. Apakah urutan TTD Monthly nantinya akan diwajibkan Atasan 1 → Atasan 2 atau tetap bebas.
6. Format export KPI.
7. Format cetak KPI.
8. Historisasi parameter KPI ketika master/indikator berubah setelah periode lama selesai.
9. Detail mekanisme SP1 setelah tiga kali Daily tidak diisi.

---

# 48. PRINSIP IMPLEMENTASI WAJIB

## 48.1 Period-First
Setiap record bulanan harus mengetahui periode performanya.

## 48.2 Shared Record
Aktor berbeda melanjutkan record yang sama.

## 48.3 Hierarchy-Aware
Monitoring dan approval menggunakan struktur Atasan Langsung.

## 48.4 Full Super Admin Capability
Semua Super Admin tetap mempunyai full capability dan tombol aksi.

## 48.5 HRD Operational Ownership
Direktur/HRD merupakan pelaksana normal pengelolaan administratif KPI.

## 48.6 Role ≠ KPI Obligation
Manajer tetap mengisi KPI walaupun Super Admin.  
Dirut dan Direktur tidak mengisi KPI Individu.

## 48.7 Hard Deadline
Deadline harus dieksekusi oleh sistem.

## 48.8 Traceable Signature
Setiap tanda tangan harus menyimpan identitas, waktu, dan sumber tindakan manual/otomatis.

## 48.9 Visible Status
Status dan tindakan yang belum selesai harus mudah terlihat.

## 48.10 No Silent Assumptions
Rule yang masih OPEN tidak boleh diisi sendiri oleh programmer/agent.

---

# 49. RINGKASAN ALUR END-TO-END

```text
SELAMA BULAN BERJALAN
Karyawan isi Daily Report
        ↓
Atasan Langsung approve Daily

AKHIR BULAN
Super Admin pastikan penilai periode sudah ditetapkan
        ↓

TANGGAL 1–2
Karyawan isi Kinerja Individu
Karyawan isi Hasil + Bukti Kinerja OPS
        ↓

TANGGAL 1–5
Penilai melakukan MPA seluruh karyawan
kecuali Dirut/Direktur/diri sendiri
        ↓
HRD menilai si penilai
        ↓

TANGGAL 6–8
HRD melanjutkan record Monthly
→ Absensi
→ Reward/Punishment
        ↓
HRD menyelesaikan seluruh karyawan
        ↓
Publish Monthly satu periode sekaligus
        ↓
TTD HRD otomatis
        ↓

SAMPAI TANGGAL 9
TTD Kinerja Individu
TTD Kinerja OPS
TTD Monthly
        ↓

9 PUKUL 23:59 WIB
Auto-sign sisa KI/K-OPS/Monthly
        ↓

SISTEM HITUNG NILAI AKHIR
        ↓

TANGGAL 10
Atasan Langsung TTD Nilai Akhir
        ↓

KPI PERIODE SELESAI
```

---

**END OF PRD — WORKING BASELINE UPDATED**
