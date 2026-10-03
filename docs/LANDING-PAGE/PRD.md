# PRD.md
## Product Requirement Document — Fase 1 Landing Page Kampoeng Radja

**Produk:** Landing Page / Company Profile Kampoeng Radja  
**Fase:** 1 — Prioritas aktif  
**Status:** Aktif  
**Cakupan:** Website publik + pengelolaan konten publik minimum

> **Kontrak visual:**  
> PRD ini mengunci **apa yang harus dibangun dan bagaimana fungsi bisnisnya bekerja**.
>
> Untuk seluruh keputusan visual, gunakan **Figma approved** sebagai source of truth utama.
>
> Jangan menyimpulkan:
>
> - layout;
> - ukuran;
> - spacing;
> - typography;
> - warna;
> - radius;
> - shadow;
> - crop;
> - responsive composition;
>
> hanya dari uraian PRD jika Figma sudah menentukannya.

---

# 1. Tujuan Produk

Landing Page Kampoeng Radja dibangun untuk:

1. menghadirkan company profile publik resmi Kampoeng Radja;
2. memperkuat citra brand sebagai taman wisata keluarga yang fun, playful, dan profesional;
3. memberikan informasi mengenai Kampoeng Radja kepada calon pengunjung;
4. menampilkan informasi wahana;
5. menampilkan dokumentasi event;
6. menampilkan konten publik seperti berita/promo/partner sesuai scope;
7. menyediakan akses menuju autentikasi sistem internal perusahaan.

Landing Page dapat diakses oleh pengunjung tanpa login.

---

# 2. Target Pengguna

## 2.1 Guest / Pengunjung Publik

Guest dapat:

- mengakses seluruh halaman publik;
- membaca informasi perusahaan;
- melihat wahana;
- menggunakan filter Wahana;
- melihat dokumentasi event;
- menggunakan interaksi publik lain yang termasuk scope.

Guest tidak dapat:

- mengelola konten;
- mengubah kategori/label;
- mengakses fitur internal perusahaan tanpa autentikasi.

---

## 2.2 Admin / Super Admin

CMS authorization final menggunakan matrix MARCOM-based: Super Admin, seluruh employee Departemen MARCOM, dan Admin dengan Penempatan MARCOM dapat mengelola konten publik. User/role lain tanpa kondisi tersebut tidak memperoleh CMS access. Detail implementation ada pada `app/Support/CmsAccess.php`.

Detail hak akses final mengikuti `USER_FLOW.md` dan implementasi authorization yang disetujui.

---

# 3. Halaman Publik Fase 1

Fase 1 menetapkan empat halaman publik:

1. **Beranda**
2. **Tentang Kami**
3. **Wahana & Tempat Makan**
4. **Galeri Event**

Keempat halaman dapat diakses tanpa autentikasi.

Route final mengikuti konvensi project dan implementasi yang disepakati.

---

# 4. Navigasi Publik

Navigasi utama harus menyediakan akses ke empat halaman publik:

- Beranda;
- Tentang Kami;
- Wahana;
- Galeri Event.

Akses login untuk karyawan **tidak menjadi bagian navigasi utama publik**.

Akses login ditempatkan pada area yang ditentukan oleh Figma approved dan requirement UI.

Pada desain Fase 1 saat ini, login direncanakan melalui footer.

Jika visual Figma approved terbaru menunjukkan struktur berbeda tetapi tidak mengubah fungsi bisnis:

> ikuti Figma.

---

# 5. Beranda

Beranda merupakan entry point utama website publik.

Section bisnis yang termasuk scope:

1. Hero
2. Informasi / Insight
3. Media & Berita
4. Promo & Event
5. Wahana Unggulan / USP
6. Sponsorship / Mitra
7. Lokasi
8. Footer

Urutan visual final mengikuti frame Figma approved.

---

## 5.1 Hero

Tujuan:

- memperkenalkan Kampoeng Radja secara visual;
- menampilkan pesan utama/brand;
- menyediakan CTA jika desain/requirement menggunakannya.

Jenis media hero mengikuti Figma approved.

PRD tidak mengunci media harus selalu video atau image jika desain approved berubah.

Jika Figma menggunakan video, behavior teknis mengikuti `UI_SPEC.md`.

---

## 5.2 Informasi / Insight

Section ini memberikan informasi/panduan yang relevan bagi pengunjung.

Konten dapat berupa:

- panduan;
- informasi kunjungan;
- informasi praktis;
- hal lain yang disetujui perusahaan.

Agent tidak boleh mengarang fakta seperti jam operasional atau aturan kunjungan.

Konten final mengikuti `CONTENT.md`.

---

## 5.3 Media & Berita

Section Media & Berita menampilkan konten publik berupa berita/media perusahaan.

Data minimum per item dapat mencakup:

- judul;
- tanggal;
- ringkasan;
- thumbnail/image;
- link/detail jika fitur detail termasuk scope.

Layout mengikuti Figma.

Tidak ada requirement penggunaan `vue-grid-layout`.

---

## 5.4 Promo & Event

Section ini menampilkan promo atau event yang sedang relevan.

Data dapat meliputi:

- judul;
- periode;
- deskripsi;
- image;
- CTA jika diperlukan.

Promo tidak boleh dibuat fiktif.

---

## 5.5 Wahana Unggulan

Section ini menampilkan wahana atau pengalaman yang ingin ditonjolkan.

Data final dan jumlah item mengikuti content/PRD/Figma.

Jangan menganggap seluruh wahana harus muncul di section ini.

---

## 5.6 Sponsorship / Mitra

Section ini menampilkan partner/sponsor jika data resmi tersedia.

Requirement fungsi:

- partner dapat ditampilkan secara visual;
- behavior interaktif mengikuti Figma/UI spec.

PRD tidak mengunci:

- auto-scroll;
- pause-on-hover;
- drag;

kecuali behavior tersebut memang tercantum pada Figma/UI spec yang approved.

---

## 5.7 Lokasi

Section Lokasi menampilkan lokasi Kampoeng Radja.

Requirement:

- Google Maps menggunakan iframe embed;
- alamat dapat ditampilkan jika data resmi tersedia;
- CTA directions/petunjuk arah dapat tersedia jika desain menggunakannya.

Lokasi/map final harus menggunakan data yang sudah diverifikasi.

---

## 5.8 Footer

Footer menjadi shared public component.

Konten dapat meliputi:

- logo;
- kontak;
- alamat;
- social links;
- copyright;
- akses login karyawan.

Konten dan struktur visual final mengikuti Figma dan `CONTENT.md`.

---

# 6. Tentang Kami

Halaman Tentang Kami menyediakan informasi identitas dan profil perusahaan.

Scope bisnis:

1. Hero
2. Profil / pengantar jika terdapat pada desain
3. Sejarah / Kisah
4. Visi & Misi
5. Struktur Organisasi
6. Footer

Urutan visual final mengikuti Figma approved.

---

## 6.1 Hero

Menampilkan identitas Kampoeng Radja.

Copy dan media mengikuti Figma/CONTENT.

Slogan yang pernah tercatat:

```text
Kesenangan Tiada Akhir di Kampoeng Radja
```

tidak otomatis dianggap konten produksi sampai dikonfirmasi sesuai `CONTENT.md`.

---

## 6.2 Sejarah / Kisah

Menampilkan perjalanan/sejarah Kampoeng Radja.

Requirement:

- mendukung narasi kronologis;
- dapat disertai foto/milestone;
- tidak mengarang fakta sejarah.

Bentuk timeline/gallery mengikuti Figma.

---

## 6.3 Visi & Misi

Menampilkan visi dan misi perusahaan yang sudah disetujui.

Konten harus berasal dari sumber resmi.

---

## 6.4 Struktur Organisasi

Menampilkan struktur organisasi yang disetujui untuk publikasi.

Data dapat meliputi:

- jabatan;
- nama;
- foto;

jika perusahaan mengizinkan.

Struktur visual mengikuti Figma.

PRD tidak mengunci implementasi sebagai tree recursive atau gambar statis.

---

# 7. Wahana

Halaman Wahana menyediakan daftar visual wahana dan sistem filter.

---

## 7.1 Model Kategori dan Label

Filter menggunakan konsep:

```text
Category
   ↓
Label
```

Satu item/foto wahana dapat memiliki lebih dari satu label.

Relasi data final mengikuti architecture/database implementation.

---

## 7.2 Daftar Awal Kategori / Label

Daftar requirement saat ini:

### Kategori

```text
Wahana
Tempat Makan
```

### Label Wahana

```text
Anak-anak
Dewasa
Air
Darat
Adrenaline
Santai
```

### Tempat Makan

Untuk saat ini:

> kategori `Tempat Makan` disiapkan tanpa label.

Status daftar di atas:

`[REQUIREMENT AKTIF — REVIEW JIKA DATA BISNIS BERUBAH]`

Perubahan daftar tidak dilakukan hanya berdasarkan desain visual.

---

# 8. Manajemen Kategori dan Label

Admin/Super Admin dapat:

- menambah kategori;
- mengubah kategori;
- menghapus kategori;
- menambah label;
- mengubah label;
- menghapus label;
- menghubungkan label ke item/foto wahana.

Data kategori/label bersifat dinamis, bukan hardcoded permanen di frontend.

---

# 9. Assignment Label Wahana

Setiap item/foto wahana dapat memiliki satu atau lebih label.

Contoh bisnis:

```text
Kolam renang anak
→ Air
→ Anak-anak
```

```text
Flying fox
→ Darat
→ Adrenaline
```

Contoh hanya menjelaskan behavior sistem.

Data produksi aktual harus berasal dari perusahaan.

---

# 10. Guest Filter Behavior

Guest dapat memilih satu atau lebih label.

Requirement utama:

### Single Filter

Jika memilih:

```text
Air
```

tampilkan item yang memiliki label `Air`.

### Multiple Filter

Jika memilih:

```text
Air + Anak-anak
```

tampilkan hanya item yang memiliki **seluruh label yang dipilih**.

Artinya:

> filter menggunakan logika **AND**, bukan OR.

---

# 11. Filter Interaction

Requirement behavior:

- label dapat dipilih;
- label aktif dapat ditoggle off;
- tombol `Cari` menerapkan filter jika UI final menggunakan pola apply;
- tombol `Reset` menghapus filter aktif;
- state kosong tidak boleh menyebabkan error.

Visual state filter mengikuti Figma.

Jika Figma/UI spec nantinya menetapkan apply otomatis tanpa tombol `Cari`, perubahan behavior harus diperbarui di PRD terlebih dahulu karena itu merupakan perubahan fungsi.

---

# 12. Filter Lintas Kategori

Sistem harus memungkinkan pemilihan label dari kategori berbeda jika data di masa depan mendukungnya.

Filter tetap menerapkan AND terhadap seluruh label aktif.

---

# 13. Wahana Guest View

Guest hanya dapat:

- melihat kategori;
- melihat label;
- memilih filter;
- melihat hasil.

Guest tidak dapat:

- membuat kategori;
- mengedit label;
- menghapus label;
- mengubah assignment data.

---

# 14. Wahana Detail / Lightbox

Jika Figma/UI final menggunakan preview/detail item:

fitur dapat menampilkan:

- image;
- nama;
- deskripsi;
- informasi lain yang memang tersedia.

Detail field tidak boleh dibuat tanpa requirement/content.

---

# 15. Galeri Event

Halaman Galeri Event menampilkan dokumentasi event Kampoeng Radja.

Setiap event dapat memiliki:

- judul;
- tanggal;
- deskripsi;
- kumpulan foto.

Data aktual mengikuti konten resmi.

---

# 16. Sorting Galeri Event

Guest dapat mengurutkan event berdasarkan tanggal:

```text
Terbaru
Terlama
```

Behavior:

### Terbaru

Tanggal event paling baru tampil terlebih dahulu.

### Terlama

Tanggal event paling lama tampil terlebih dahulu.

Default sorting harus ditentukan pada `UI_SPEC.md`.

Jika belum ditentukan, gunakan:

`[PERLU KLARIFIKASI]`

dan jangan membuat klaim bahwa pilihan default merupakan requirement bisnis.

---

# 17. Galeri Event — Interaction

Jika desain menggunakan lightbox/detail:

- interaction harus berfungsi;
- keyboard behavior mengikuti `UI_SPEC.md`;
- visual mengikuti Figma.

PRD tidak mewajibkan lightbox jika desain/behavior final tidak menggunakannya.

---

# 18. Panel Admin Fase 1

Fase 1 mencakup panel admin **minimum** untuk pengelolaan konten publik. Media & Berita merupakan section Beranda, bukan halaman publik terpisah pada scope final.

Scope dapat mencakup:

- Wahana;
- kategori;
- label;
- assignment label;
- Galeri Event;
- Media & Berita;
- Promo;
- Mitra.

Detail UI panel admin tidak harus mengikuti desain Landing Page publik kecuali tersedia desain Figma khusus admin.

---

# 19. Panel Admin — Prinsip Scope

Panel admin hanya dibuat sejauh diperlukan untuk mengelola konten publik Fase 1.

Jangan membangun:

- dashboard KPI;
- evaluasi karyawan;
- closing event marketing;
- permission system kompleks;
- modul internal lain;

pada fase ini.

---

# 20. Role Fase 1

Role global:

- Super Admin;
- Admin;
- User.

Untuk pengelolaan konten publik:

- Super Admin memiliki akses sesuai fondasi global;
- Admin dapat mengelola konten sesuai scope;
- User tidak otomatis memiliki akses content management.

Detail authorization mengikuti `USER_FLOW.md`.

---

# 21. Data Dinamis

Data yang memang dikelola melalui panel admin harus berasal dari backend/database.

Namun:

> tidak semua teks/decorative content Figma wajib menjadi data dinamis.

Lihat `ARCHITECTURE.md` untuk pemisahan data bisnis dan presentasional.

---

# 22. Konten dan Aset Sementara

Jika konten produksi belum tersedia:

1. gunakan `[FIGMA SEMENTARA]` jika tersedia di Figma;
2. jika tidak ada, gunakan `[PLACEHOLDER TERDOKUMENTASI]`.

Agent tidak boleh:

- mengarang fakta;
- menggunakan stock image untuk mengganti aset Figma;
- menganggap copy Figma otomatis sebagai konten produksi.

Lihat:

- `CONTENT.md`
- `ASSETS.md`

---

# 23. Responsive

Keputusan stakeholder Fase 1:

> Tim desain hanya menyediakan **desain desktop**.

Tidak akan dibuat frame Figma khusus:

- tablet;
- mobile.

Konsekuensi:

### Desktop

- mengikuti Figma approved;
- wajib visual QA;
- dapat memperoleh status `[FIGMA VERIFIED]`.

### Tablet / Mobile

- menggunakan `RESPONSIVE.md`;
- berstatus `[RESPONSIVE FALLBACK]`;
- tidak boleh diklaim pixel-accurate terhadap Figma.

Responsive fallback harus mempertahankan:

- hierarchy;
- urutan konten;
- identitas visual;
- usability;
- proporsi media sebisa mungkin.

---

# 24. Accessibility

Fase 1 harus menerapkan accessibility dasar.

Minimal:

- semantic HTML;
- alt text untuk image informatif;
- keyboard-friendly interactive control;
- focus management untuk modal jika digunakan;
- link/button semantics yang benar.

Perubahan accessibility yang terlihat signifikan harus dicatat jika menyimpang dari Figma.

---

# 25. Performance

Landing Page harus memperhatikan performa.

Termasuk:

- optimasi image;
- lazy loading jika tepat;
- optimasi video jika digunakan;
- menghindari dependency berat tanpa kebutuhan;
- tidak menggunakan URL Figma temporary saat runtime.

Performance optimization tidak boleh menghapus bagian visual approved tanpa persetujuan.

---

# 26. SEO Dasar

Landing Page publik harus mendukung SEO dasar.

Minimal:

- page title;
- meta description;
- semantic heading;
- alt image;
- URL yang jelas.

Open Graph, sitemap, canonical, dan konfigurasi lain dapat ditambahkan sesuai release/deployment scope.

---

# 27. Out of Scope — Fase 1

Tidak termasuk Fase 1:

- Sistem KPI Karyawan;
- Sistem Closing Event Marketing;
- master data Fase 2/3;
- workflow KPI;
- workflow Closing Event;
- permission granular fase berikutnya;
- mobile application;
- public REST API terpisah;
- redesign brand.

---

# 28. Detail Media & Berita

Figma memiliki reference node:

```text
1:650
```

Status visual:

`[APPROVED FOR DEVELOPMENT]`

Namun requirement halaman **Detail Media & Berita** belum ditetapkan pada PRD ini.

Status scope:

`[PERLU KEPUTUSAN]`

Agent tidak boleh membangun page detail hanya karena desain tersedia.

---

# 29. Requirement yang Masih Terbuka

## 29.1 Media & Berita

Perlu diputuskan:

- apakah hanya list/card;
- apakah memiliki halaman detail;
- apakah content dikelola manual melalui admin;
- apakah ada integrasi eksternal.

Default yang paling aman saat ini:

> content dikelola internal melalui panel admin sederhana, tanpa integrasi eksternal, sampai requirement lain disetujui.

Namun halaman detail tetap belum otomatis masuk scope.

---

## 29.2 Struktur Organisasi

Perlu diputuskan:

- static content;
- atau dinamis melalui admin.

Sebelum diputuskan:

> jangan membuat CMS khusus struktur organisasi hanya berdasarkan asumsi.

---

## 29.3 Multi-Bahasa

Status:

`[BELUM DIPUTUSKAN]`

Untuk Fase 1 saat ini:

> Bahasa Indonesia menjadi baseline implementasi.

Jangan membangun translation architecture kompleks sebelum kebutuhan bilingual disetujui.

---

# 30. Requirement yang Dihapus sebagai Asumsi Lama

Requirement berikut **tidak lagi dianggap otomatis**:

- `vue-grid-layout`;
- style “terinspirasi Vue”;
- carousel harus auto-scroll;
- carousel harus pause-on-hover;
- carousel harus draggable;
- semua hero harus full-screen;
- semua halaman menggunakan detail visual generik dari PRD.

Hal-hal tersebut hanya berlaku jika Figma/UI spec menentukannya.

---

# 31. Acceptance Criteria — Beranda

Beranda dianggap memenuhi requirement produk jika:

- [ ] seluruh section scope aktif tersedia;
- [ ] content dapat ditampilkan;
- [ ] lokasi berfungsi jika data resmi tersedia;
- [ ] partner/news/promo mengikuti data source yang disetujui;
- [ ] visual mengikuti Figma desktop;
- [ ] responsive fallback bekerja.

---

# 32. Acceptance Criteria — Tentang Kami

- [ ] informasi profil yang termasuk scope tersedia;
- [ ] sejarah dapat ditampilkan;
- [ ] visi/misi dapat ditampilkan;
- [ ] struktur organisasi dapat ditampilkan;
- [ ] tidak ada fakta fiktif;
- [ ] visual desktop mengikuti Figma;
- [ ] responsive fallback bekerja.

---

# 33. Acceptance Criteria — Wahana

- [ ] kategori/label dapat ditampilkan;
- [ ] data bersifat dinamis;
- [ ] label dapat dipilih;
- [ ] multi-label menggunakan AND;
- [ ] reset bekerja;
- [ ] toggle bekerja;
- [ ] empty state aman;
- [ ] admin dapat mengelola category/label sesuai scope;
- [ ] visual desktop mengikuti Figma;
- [ ] responsive fallback bekerja.

---

# 34. Acceptance Criteria — Galeri Event

- [ ] event dapat ditampilkan;
- [ ] tanggal digunakan untuk sorting;
- [ ] Terbaru berfungsi;
- [ ] Terlama berfungsi;
- [ ] foto event dapat ditampilkan;
- [ ] visual desktop mengikuti Figma;
- [ ] responsive fallback bekerja.

---

# 35. Acceptance Criteria — Admin Minimum

- [ ] authentication tersedia;
- [ ] role yang berhak dapat masuk;
- [ ] konten scope dapat dikelola;
- [ ] validation tersedia;
- [ ] upload/media mengikuti storage architecture;
- [ ] user yang tidak berhak tidak dapat mengelola konten.

---

# 36. Definition of Done Produk

Fase 1 baru dianggap selesai jika:

- [ ] empat halaman publik aktif selesai;
- [ ] requirement fungsional utama selesai;
- [ ] panel admin minimum scope selesai;
- [ ] desktop Figma QA selesai;
- [ ] responsive fallback selesai;
- [ ] content/aset sementara tercatat;
- [ ] build/test valid;
- [ ] blocker diketahui;
- [ ] dokumentasi sinkron;
- [ ] tidak ada fitur Fase 2/3 yang ikut dibangun tanpa requirement.

---

# 37. Prinsip Akhir

> **PRD menentukan fungsi.**

> **Figma approved menentukan visual desktop.**

> **UI_SPEC dan USER_FLOW menentukan behavior detail.**

> **RESPONSIVE.md menentukan fallback tablet/mobile.**

Jika Figma sudah menentukan bentuk UI:

> jangan membuat visual baru dari deskripsi PRD.

Jika PRD sudah menentukan fungsi:

> jangan menghapus fungsi hanya karena Figma tidak menunjukkan seluruh state.
