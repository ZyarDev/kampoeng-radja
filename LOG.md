# LOG.md — Kampoeng Radja Project Tracker

Terakhir diperbarui: **23 September 2026**
Dasar status: audit source code pada working tree aktif, bukan klaim dokumentasi
Fokus terbaru: **Perbaikan responsive Produk, swipe Promo mobile, dan pembaruan aset Tentang Kami**

Dokumen ini adalah pintu masuk tunggal untuk melihat posisi proyek. Untuk detail requirement tetap buka dokumen sumber yang dirujuk; `LOG.md` tidak menggantikan PRD, aturan agent, atau Figma.

## Perubahan Terbaru — 23 September 2026

- Section **Produk** di Beranda menggunakan container mobile rasio `16:9` dan gambar `object-cover`, sehingga gambar memenuhi area container pada layar HP. Rasio aset Hero Produk yang tersimpan terukur sekitar `1896×829` atau `±16:7`; rasio desktop `16:7` tetap dipertahankan.
- Section **Promo** di Beranda sekarang mendukung swipe kiri/kanan pada perangkat sentuh. Tombol panah dan carousel desktop tetap dipertahankan; gesture swipe tidak mengganggu scroll vertikal.
- Aset gambar **Bagian 1** Tentang Kami diperbarui ke `public/assets/temporary/about-foundation.png` dan aset **Bagian 2** ditambahkan sebagai `public/assets/temporary/about-transformation.jpeg`.
- Aset gambar **Bagian 3** Tentang Kami diperbarui ke `public/assets/temporary/about-growth.png`.
- Referensi gambar Bagian 1, Bagian 2, dan Bagian 3 pada `resources/js/Pages/TentangKami.vue` sudah diarahkan ke aset terbaru.
- Verifikasi terakhir: Vite production build berhasil dan `git diff --check` tidak menemukan error whitespace. Warning line-ending CRLF pada file existing tetap ada.

## Handoff Snapshot — 26 Agustus 2026

> Bagian ini adalah baseline terbaru untuk task/chat baru dan **mengungguli status lama di bawah jika terjadi konflik**. Riwayat lama tetap dipertahankan sebagai audit trail.

### Workspace Canonical

- Repository canonical sekarang berada di `D:\kampoeng-radja`.
- Path lama `D:\KAMPOENG RADJA\kampoeng-radja` sudah tidak dipakai dan tidak boleh digunakan lagi.
- Task Codex yang dibuat sebelum folder dipindahkan masih membawa sandbox root lama dan gagal dengan `apply deny-read ACLs`; pekerjaan berikutnya harus dimulai dari task/project lokal baru yang memilih `D:\kampoeng-radja`.
- Verifikasi awal setiap task baru: jalankan `pwd`/`Get-Location`, `git rev-parse --show-toplevel`, dan `git status --short` sebelum mengubah source.

### Repository Safety

- Working tree sengaja belum bersih dan berisi banyak perubahan serta file untracked dari rangkaian pengembangan aktif.
- Jangan menjalankan `git reset --hard`, `git clean -fd`, `git checkout .`, atau membuang perubahan massal.
- `git diff --check` terakhir lulus; hanya ada warning line-ending CRLF existing pada dokumen Employee.
- Temporary recovery Hero `.codex-home-index.patch`, `.codex-home-public.patch`, dan `resources/js/Pages/Home.vue.rej` sudah diaudit, diterapkan, lalu dihapus. Tidak ada `.rej`, `.orig`, atau `.codex-*.patch` tersisa saat normalisasi.

### CMS / Public Baseline Terbaru

- **CMS Beranda → Hero:** source recovery sudah konsisten. Tersedia migration `home_hero`, model `HomeHero`, `UpdateHomeHeroRequest`, `HomeHeroController`, route `PATCH /dashboard/cms/beranda/hero`, `HeroManager.vue`, integrasi CMS Beranda, dan prop public Home.
- Public Hero mendukung video CMS dengan `autoplay`, `muted`, `loop`, `playsinline`, poster fallback, overlay, konten CMS, serta CTA internal Inertia/external HTTP(S) yang tervalidasi.
- Replacement video/poster menyimpan file baru terlebih dahulu dan membersihkan file lama setelah transaksi berhasil.
- **CMS Promo:** implementasi Phase 1 dan integrasi public Home berada di working tree; test existing Home/Promo terakhir lulus.
- **CMS Wahana dan CMS Galeri Event:** controller/request/component/test terkait sudah berada di working tree. Karena normalisasi workspace tidak mengaudit ulang seluruh behavior kedua modul, agent berikutnya wajib membaca source dan test aktual sebelum melanjutkan.
- Jangan membuat migration/model/controller/component Hero, Promo, Wahana, atau Galeri kedua tanpa audit duplicate terlebih dahulu.

### Storage dan Upload

- `public/storage` sudah dibuat ulang sebagai junction valid menuju `D:\kampoeng-radja\storage\app\public`.
- Integritas akses diverifikasi menggunakan hash sampel: Promo, Wahana, dan Galeri Event identik antara source upload dan jalur `public/storage`.
- Snapshot file upload: `event-promo` 4 file, `wahana` 6 file, `galeri-event` 13 file.
- Folder `storage/app/public/home/hero` belum ada karena belum ada upload Hero development; ini bukan kehilangan data.
- Jangan menghapus atau membuat ulang `storage/app/public`; jika link rusak, hanya junction `public/storage` yang boleh diperbaiki.

### Database dan Migration

- MySQL development `127.0.0.1:3306` belum aktif pada recovery terakhir, sehingga migration Hero belum diterapkan ke database development.
- Migration Hero valid secara syntax dan berhasil dilalui pada test environment SQLite in-memory.
- Jangan menjalankan `migrate:fresh`, `migrate:refresh`, `db:wipe`, reset, atau reseed.
- Setelah MySQL aktif, mulai dengan `php artisan migrate:status`; review seluruh migration pending sebelum menjalankan migration normal karena working tree juga memuat migration modul lain.

### Verification Terakhir

- PHP lint seluruh file Hero terkait: lulus.
- Pint terarah dan verifikasi route Hero: lulus; tepat satu route update Hero.
- Existing feature tests `LandingPageTest` + `EventPromoCrudTest`: **20 test lulus, 285 assertions**.
- `php artisan about`: berhasil; Laravel 12.67.0 dan PHP 8.2.12.
- `php artisan optimize:clear`: berhasil setelah perpindahan folder.
- Vite production build: **berhasil, 829 modules transformed**.
- `git diff --check`: lulus.
- Browser QA Hero berbasis database development belum dilakukan karena MySQL belum aktif.

### First Actions untuk Agent Baru

1. Pastikan workspace benar-benar `D:\kampoeng-radja` dan command standar tidak meminta elevated.
2. Jalankan `git status --short` dan pertahankan seluruh perubahan existing.
3. Baca snapshot ini, lalu audit dokumen/source modul yang akan dikerjakan; jangan mengandalkan memory chat lama.
4. Aktifkan MySQL development secara manual bila diperlukan, lalu jalankan `php artisan migrate:status` sebelum mutation database.
5. Selesaikan migration dan browser QA CMS Hero terlebih dahulu sebelum mengembangkan section CMS Beranda berikutnya.

## Status Legend

| Status | Arti |
| --- | --- |
| `IMPLEMENTED` | Source code dan fungsi utama tersedia, serta memiliki bukti test/build yang relevan. |
| `PARTIAL` | Sebagian fungsi/fondasi tersedia, tetapi requirement modul belum seluruhnya terpenuhi. |
| `DESIGN / REQUIREMENT ONLY` | Desain, PRD, atau requirement tersedia, tetapi implementasi belum ditemukan. |
| `NOT STARTED` | Tidak ditemukan implementasi maupun pekerjaan source code yang signifikan. |
| `NEEDS VERIFICATION` | Source code ditemukan, tetapi bukti yang tersedia belum cukup untuk memastikan fungsinya. |

## Project Snapshot

- Produk: website publik, CMS Landing Page, dan fondasi sistem internal Kampoeng Radja.
- Stack aktual: PHP 8.2+, Laravel 12, Inertia.js 2, Vue 3 Composition API, Tailwind CSS, Vite, MySQL, serta Laravel Excel/PhpSpreadsheet untuk workbook `.xlsx`; test menggunakan SQLite in-memory.
- Autentikasi: session Laravel dengan `username` + PIN 6 digit.
- Frontend navigation: Laravel/Inertia; tidak menggunakan Vue Router untuk route utama.
- Media upload CMS: disk Laravel `public`, dengan URL publik `/storage/...`.
- Dokumentasi utama: `AGENTS.md`, `docs/README.md`, `docs/GLOBAL/`, dan `docs/LANDING-PAGE/`.
- Kondisi repository saat audit: working tree memiliki banyak perubahan dan file untracked dari pekerjaan sebelumnya. Audit ini mencerminkan working tree tersebut, bukan hanya commit terakhir.

## Current State

1. Landing Page publik dan integrasi data CMS sudah tersedia, tetapi belum memenuhi Definition of Done Fase 1 karena CMS minimum belum lengkap, masih ada placeholder/aset sementara, dan QA visual Figma belum tercatat selesai.
2. Login username/PIN, role dasar, middleware, seeder development, dan Dashboard Home role-aware sudah tersedia. Dashboard merangkum Karyawan aktif, Absensi hari ini, grafik nilai Closing Event aktif per Tanggal Mulai, ringkasan event aktif, dan shortcut capability-aware. `/dashboard` menjadi entry point canonical; `/admin` mengarah ke `/dashboard`.
3. CRUD internal yang tersedia mencakup Kelola Karyawan (role-aware), Jabatan & Departemen (Super Admin), Media & Berita, serta Event & Promotion.
4. Data Karyawan menyediakan list/search/filter/pagination company-wide untuk ketiga role, create/detail/edit, protected Foto KTP, deactivate, proses keluar, conditional hard delete, create/manage Akun Sistem, serta export Excel aktif/nonaktif oleh Super Admin. Jabatan menjadi sumber authoritative role akun: perubahan `jabatan_id` akun existing menyinkronkan `users.role_id` dalam transaksi, sedangkan Karyawan tanpa akun tidak dibuatkan akun otomatis. Admin/User read-only dan hanya menerima 12 atribut umum.
5. Data Absensi sudah company-wide untuk ketiga role. Super Admin dapat input/edit H/I/A, jam manual, dan keterangan pada hari berjalan serta satu hari kalender sebelumnya, serta export Excel bulanan multi-sheet; Admin/User memperoleh halaman read-only dan mutation/export ditolak backend.
6. Closing Event mendukung event satu hari dan multi-hari dalam satu record serta dua status bisnis: `aktif` dan `dibatalkan`. Pembatalan menyimpan alasan/actor/waktu, tetap editable, dapat diaktifkan kembali tanpa menghapus histori, tetap ikut export, tetapi dikecualikan dari ongoing dan seluruh agregat Dashboard. Filter/export bulan tetap memakai period overlap tanpa membagi atau menggandakan Harga Total. Capability existing tidak berubah. KPI tetap belum memiliki implementasi.
7. Verifikasi terbaru Dashboard Home: **7 test lulus (168 assertions)** dan **Vite production build berhasil (821 modules transformed)**; visual QA desktop/tablet/mobile tidak menemukan page-level horizontal overflow atau console error.
8. Dokumentasi Employee dan access-control global sudah mencatat sinkronisasi Jabatan → role; audit akun development terakhir menunjukkan **0 mismatch**.

## Implemented Pages

### Public / Landing Page

| Route | Inertia Page | Status | Catatan aktual |
| --- | --- | --- | --- |
| `/` | `Home.vue` | `PARTIAL` | Section utama tersedia; Media Berita, Promo Event, Wahana unggulan, dan Mitra membaca database dengan fallback. Masih ada konten/aset Figma sementara dan belum ada bukti visual QA final. |
| `/tentang-kami` | `TentangKami.vue` | `PARTIAL` | Halaman dan section utama tersedia, tetapi konten perusahaan masih mengandung placeholder/sementara dan status visual final belum diverifikasi. |
| `/wahana` | `Wahana.vue` | `PARTIAL` | Data database, toggle, tombol Cari/Reset, empty state, modal, dan filter multi-label AND tersedia. Model label masih berupa string dipisah koma dan CRUD admin wahana/kategori/label belum ada. |
| `/galeri-event` | `GaleriEvent.vue` | `PARTIAL` | Data event/foto, urutan Terbaru/Terlama, empty state, dan modal tersedia. CRUD admin Galeri belum ada dan QA visual final belum terbukti. |
| `/media-berita` | `Berita.vue` | `PARTIAL` | Data CMS diurutkan terbaru, item pertama menjadi featured, dan pencarian client-side bekerja. Fallback terpisah tersedia saat database kosong; halaman detail belum masuk scope final. |

Catatan: navbar publik utama tetap mengarah ke empat halaman scope PRD; `/media-berita` adalah halaman publik tambahan yang sudah memiliki route aktual.

### Authentication

| Route | Inertia Page | Status | Catatan aktual |
| --- | --- | --- | --- |
| `/login` | `Auth/Login.vue` | `IMPLEMENTED` | Login username + PIN 6 digit, rate limit, pemeriksaan hash, dan blokir akun inactive telah diuji. |
| `/ganti-pin` | `Auth/ChangePin.vue` | `IMPLEMENTED` | Akun temporary-PIN wajib mengganti PIN sebelum route internal lain; PIN baru di-hash dan flag dibersihkan. |
| `/coming-soon` | `ComingSoon.vue` | `IMPLEMENTED` | Placeholder authenticated tersedia; bukan dashboard bisnis. |
| `/dashboard` | `Internal/Dashboard/Index.vue` | `IMPLEMENTED` | Dashboard Home dinamis: Super Admin/Admin menerima summary Absensi company-wide; actor dengan capability Closing Event menerima grafik nilai event dan ringkasan event; User lain tetap self-scoped. Kalender/Karyawan Terbaru telah diganti Akses Cepat. |

### Dashboard Internal

| Route/Menu | Inertia Page | Status | Catatan aktual |
| --- | --- | --- | --- |
| `/admin` | redirect `/dashboard` | `IMPLEMENTED` | Entry lama dipertahankan untuk backward compatibility; route management `/admin/*` tetap dilindungi middleware existing. |
| `/dashboard/karyawan` | `Internal/Employee/Index.vue` | `IMPLEMENTED` | Ketiga role memiliki company-wide row scope, search/filter termasuk Departemen, sorting nama, pagination 15 baris; Admin/User hanya menerima 12 atribut umum. Super Admin dapat mengekspor workbook aktif/nonaktif yang tidak dipengaruhi filter halaman. |
| `/dashboard/karyawan/create` | `Internal/Employee/Create.vue` | `IMPLEMENTED` | Form penuh Super Admin, Form Request, Action create, nullable departemen, dan upload KTP private. |
| `/dashboard/karyawan/{id}` | `Internal/Employee/Show.vue` | `IMPLEMENTED` | Detail company-wide; mutation, field sensitif, protected KTP, deactivate, keluar, conditional delete, serta section create/manage Akun Sistem hanya Super Admin. |
| `/dashboard/karyawan/{id}/edit` | `Internal/Employee/Edit.vue` | `IMPLEMENTED` | Prefill, unique NIK ignore-current, replace/cleanup KTP, sinkronisasi role ketika Jabatan berubah, dan akun tetap inactive saat Employee direaktivasi. |
| `/dashboard/jabatan-departemen` | `Internal/Employee/Masters.vue` | `IMPLEMENTED` | CRUD dua master untuk Super Admin; delete ditolak ketika masih direferensikan karyawan. |
| `/admin/absensi` | `Internal/Absensi/Index.vue` | `IMPLEMENTED` | Ketiga role dapat melihat data company-wide; mutation hari ini/kemarin dan export Excel bulanan multi-sheet hanya Super Admin. |
| KPI | — | `NOT STARTED` | Hanya label `Soon`; tidak ada migration/model/controller/page/test KPI. |
| `/dashboard/closing-event` + create/detail/edit/export | `Internal/ClosingEvent/*` | `IMPLEMENTED` | Event satu/multi-hari tetap satu record; status aktif/dibatalkan, filter status, pembatalan beralasan dengan audit, reaktivasi, export histori, list range compact, filter/export overlap bulan, highlight aktif-only, pagination 15, Detail lengkap, multi-lokasi, dan action mengikuti capability. |
| `/dashboard/closing-event/master` | `Internal/ClosingEvent/Masters.vue` | `IMPLEMENTED` | CRUD PIC/Jenis Event/Lokasi khusus Super Admin; delete master terpakai ditolak. |

### Admin CMS

| Route/Page | Status | Catatan aktual |
| --- | --- | --- |
| `/admin/media-berita` + create/edit | `IMPLEMENTED` | List, create, upload, edit/replace foto, delete dengan konfirmasi, audit user, validation, dan cleanup file telah diuji. |
| `/admin/event-promo` + create/edit | `IMPLEMENTED` | List, create, upload poster, edit/replace, link WhatsApp opsional, delete, audit user, validation, dan cleanup file telah diuji. |
| Wahana / kategori / label | `NOT STARTED` | Tabel/model dan public read tersedia; tidak ada CRUD admin. |
| Mitra | `NOT STARTED` | Tabel/model dan public read tersedia; tidak ada CRUD admin. |
| Galeri Event / foto | `NOT STARTED` | Tabel/model dan public read tersedia; tidak ada CRUD admin. |
| Site Settings | `NOT STARTED` | Tabel tersedia; belum ditemukan model, controller, route, atau UI. |
| Detail Media & Berita | `DESIGN / REQUIREMENT ONLY` | Node Figma `1:650` tersedia, tetapi PRD/TODO masih meminta keputusan scope. |

Total page component yang terhubung ke route Inertia: **21** setelah penambahan halaman Ganti PIN. `Dashboard.vue` root dan `Welcome.vue` masih ada di source tetapi tidak ditemukan terhubung ke route aktif.

## Module Status

| Module | Status | Notes |
| --- | --- | --- |
| Landing Page | `PARTIAL` | Lima route publik aktif dan fungsi utama tersedia, tetapi visual/content/asset QA serta CMS minimum Fase 1 belum lengkap. |
| Authentication | `IMPLEMENTED` | Username/PIN hash, `is_active`, status Karyawan, `must_change_pin`, forced first-PIN flow, session, logout, rate limiting, factory, seeder, dan test tersedia. |
| Admin Foundation | `PARTIAL` | Dashboard Home, middleware, role scope, dan route management tersedia; navigasi internal CMS masih belum sinkron dengan CRUD aktual. |
| Dashboard Home | `IMPLEMENTED` | Empat summary operasional, Ringkasan Absensi lima metric, grafik nilai Closing Event berdasarkan Tanggal Mulai, ringkasan Closing Event overlap/inclusive, quick access capability-aware, role-aware payload, dan responsive fallback tersedia. |
| Kelola Karyawan/Jabatan/Departemen | `IMPLEMENTED` | Data Karyawan company-wide untuk semua role; Admin/User read-only dengan 12 field umum, Super Admin memperoleh data lengkap, mutation, dan export Excel menurut status aktif/nonaktif. Private KTP, account lifecycle dan sinkronisasi role authoritative berbasis Jabatan, command audit idempotent, master constraints, integration test, dan responsive UI tersedia. |
| Data Absensi | `IMPLEMENTED` | View company-wide lintas role, H/I/A, jam manual nullable, keterangan, input/edit hari ini dan kemarin, backend capability, dan export Excel bulanan multi-sheet tersedia. |
| CMS Landing Page | `PARTIAL` | Media Berita dan Event Promo selesai secara fungsional; Wahana, Mitra, Galeri, kategori/label, dan Site Settings belum ada CRUD. |
| Detail Media & Berita | `DESIGN / REQUIREMENT ONLY` | Desain ada, keputusan scope belum final. |
| KPI | `NOT STARTED` | Tidak ada implementasi aktual. |
| Closing Event | `IMPLEMENTED` | Data Closing Event satu/multi-hari dan multi-lokasi; status aktif/dibatalkan dengan metadata pembatalan dan reaktivasi; highlight serta Dashboard hanya untuk event aktif; Detail lengkap; Export Excel histori overlap bulanan satu row/event; master, capability, sidebar, seed, tests, dan responsive fallback tersedia. |

## Database Snapshot

Migration aktual dapat membangun tabel berikut dari database kosong.

### CORE dan autentikasi

- `departemen`: `nama_departemen` unique.
- `jabatan`: `nama_jabatan` unique.
- `role`: `nama_role` unique.
- `karyawan`: NIK unique; FK ke `jabatan` dan nullable `departemen` menggunakan restrict delete; data personal/status sesuai enum schema final. Migration koreksi 17 Agustus menyelaraskan nullability departemen dengan PRD.
- `users`: satu akun per karyawan (`karyawan_id` unique); FK `role_id`; `username` unique; hash PIN; `is_active`; `must_change_pin` default false; timestamps.
- `pin_reset_requests`: FK pemohon dan pemroses ke `users`; workflow implementasinya belum tersedia.
- `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`: tabel infrastruktur Laravel.

### CMS Landing Page

- `media_berita`
- `event_promo`
- `wahana`
- `mitra`
- `galeri_event`
- `galeri_event_foto`
- `site_settings`

Seluruh tabel CMS memakai FK audit `created_by`/`updated_by` sesuai schema final, kecuali `site_settings` hanya memiliki `updated_by`. Child `galeri_event_foto` cascade ketika event induk dihapus; FK audit memakai restrict/null-on-delete sesuai nullable field.

### Absensi

- `absensi`: FK `karyawan_id` dengan restrict delete, `tanggal_absensi`, enum `status_kehadiran` (`H`, `I`, `A`), `jam_masuk`/`jam_keluar` nullable, `keterangan` nullable, timestamps.
- Constraint unik `karyawan_id + tanggal_absensi` mencegah duplikasi kehadiran per hari.
- Index pada `tanggal_absensi` tersedia.
- Relasi model: `Karyawan hasMany Absensi`; `Absensi belongsTo Karyawan`.

Tidak ditemukan migration KPI.

### Closing Event

- `pic`: nama PIC unique.
- `event`: jenis event unique.
- `lokasi`: nama lokasi unique.
- `closing_event`: FK PIC/Jenis Event/audit User; `tanggal` sebagai Tanggal Mulai; `tanggal_selesai DATE NULL` sebagai akhir periode; `status_event` (`aktif`/`dibatalkan`, default aktif); alasan serta audit `cancelled_by/cancelled_at`; data konsumen/operasional, boolean konsumsi, jumlah pengunjung, dan satu harga `DECIMAL(15,2)` per event.
- `closing_event_lokasi`: pivot composite primary key; cascade hanya ketika Closing Event induk dihapus.
- PIC/Jenis Event/Lokasi yang masih digunakan dilindungi FK restrict dan guard controller.

## Authentication & Access Control

- Login memakai `username` dan PIN tepat 6 digit; tidak ada `email/password` pada schema autentikasi final.
- `User.pin` menggunakan cast `hashed`; login memverifikasi dengan `Hash::check`. PIN tidak disimpan plaintext.
- Query login mensyaratkan `users.is_active = true` serta `karyawan.status_keaktifan = aktif`, dan dibatasi 5 percobaan per kombinasi username/IP.
- Akun baru Employee memiliki `must_change_pin = true`; middleware global membatasi akses ke Ganti PIN dan logout sampai PIN baru tersimpan.
- Middleware alias `admin` mengizinkan user aktif dengan role `admin` atau `super_admin`.
- Middleware alias `super_admin` mengizinkan hanya user aktif dengan role `super_admin`.
- Seluruh CRUD CMS berada di backend route group `auth + admin`.
- View Data Absensi berada pada route `auth + active` dan memeriksa capability backend; ketiga role menerima scope company-wide.
- Store Absensi dan export Excel memakai capability backend Super Admin. Admin/User tetap mendapat HTTP 403 jika memanggil mutation/export secara langsung.
- Role `user` tidak dapat mengakses CRUD CMS, tetapi dapat membuka Data Absensi read-only sesuai requirement final.
- Role dibaca melalui relasi database, bukan disimpan manual di session.
- Route read Data Karyawan memakai `auth + active` dan membentuk query/payload sesuai role; seluruh mutation Employee serta Jabatan/Departemen memakai backend middleware `super_admin` ditambah authorization Form Request.
- Create/manage Akun Sistem memakai route `super_admin`; role ditentukan backend dari Jabatan dan aktivasi akun ditolak ketika Karyawan nonaktif.
- Update Jabatan Karyawan yang memiliki akun menyinkronkan hanya `users.role_id` dalam transaksi yang sama; username, hash PIN, `is_active`, dan `must_change_pin` dipertahankan. Middleware membaca role dari database pada request berikutnya, bukan dari salinan session.
- Foto KTP disimpan pada disk private `local` dan hanya dikirim melalui endpoint stream Super Admin; Admin/User tidak menerima path maupun URL dokumen.
- Closing Event memakai capability backend dari kombinasi role, jabatan, dan departemen. Semua actor yang berhak melihat mendapat scope company-wide; delete dan master hanya Super Admin. Perubahan status memakai capability Update existing, sedangkan `cancelled_by/cancelled_at` selalu ditentukan backend.
- Seeder development idempotent membuat role `super_admin`, `admin`, `user`, master IT/Admin Sistem, karyawan `ADMIN001`, dan akun lokal `admin` (PIN input development `123456`, tersimpan sebagai hash). Seeder hanya dipanggil otomatis pada environment local/testing.

## Data Absensi Audit

| Requirement | Kondisi source aktual |
| --- | --- |
| Migration/model/relasi | `IMPLEMENTED` |
| Hanya karyawan aktif | `IMPLEMENTED` di query dan validation backend |
| Input tunggal H/I/A | `IMPLEMENTED` sebagai radio-style button per baris |
| Keterangan opsional | `IMPLEMENTED`, nullable string maksimal 255 karakter |
| Jam Masuk/Jam Keluar manual | `IMPLEMENTED`; input HH:MM keyboard-first dengan stepper satu menit, navigasi Enter/Arrow, nullable, batas jam masuk 12:00, indikator Terlambat >08:30, Pulang Awal <16:30, dan validasi urutan jam |
| Status I/A membersihkan jam | `IMPLEMENTED` di frontend dan dipaksakan kembali oleh backend |
| Simpan seluruh karyawan | `IMPLEMENTED`; request harus berisi setiap karyawan aktif tepat satu kali |
| Maksimal satu record/karyawan/hari | `IMPLEMENTED` melalui unique constraint dan `updateOrCreate` |
| Input/edit hari berjalan | `IMPLEMENTED` |
| Input/edit satu hari kalender sebelumnya | `IMPLEMENTED`; menggunakan tanggal `Asia/Jakarta`, bukan rolling 24 jam |
| Tanggal sebelum kemarin read-only | `IMPLEMENTED` di UI dan ditolak backend untuk mutation |
| Tanggal masa depan | `IMPLEMENTED`; route index menolak tanggal future |
| View company-wide lintas role | `IMPLEMENTED`; Super Admin/Admin/User menerima daftar yang sama |
| Authorization mutation/export | `IMPLEMENTED`; hanya Super Admin, Admin/User mendapat 403 |
| Export Excel bulanan | `IMPLEMENTED`; periode memakai `tanggal_absensi`, sheet pertama Rekap Bulanan, lalu seluruh tanggal kalender bulan termasuk empty sheet; kalender/leap year dinamis |
| Feedback/disable saat request | `IMPLEMENTED` melalui state Inertia form |
| Responsive/sidebar drawer | `IMPLEMENTED` pada layout internal; tabel tetap dapat scroll horizontal |
| Foto profil karyawan | `PARTIAL`; UI memakai fallback inisial karena schema hanya memiliki `foto_ktp`, bukan foto profil |
| Tampilan Admin/User | `IMPLEMENTED`; seluruh kolom tampil read-only tanpa tombol Simpan/Edit/Export |

## Verification Snapshot — 23 Agustus 2026

- Status Closing Event diverifikasi pada SQLite terisolasi: create default `aktif`; transisi ke `dibatalkan` menyimpan alasan/actor/waktu; scope Dashboard aktif menghasilkan nilai 0; export memuat status/alasan; reaktivasi mengembalikan nilai Rp15.000.000 dan mempertahankan metadata pembatalan.
- Focused regression Closing Event + Dashboard Home: **18 test lulus (425 assertions)**.
- Production build setelah status Closing Event berhasil dengan **821 modules transformed**; Pint dan PHP lint file terkait berhasil.
- Migration development MySQL dan browser QA belum dapat dijalankan karena service MySQL `127.0.0.1:3306` sedang tidak aktif. Migration berhasil dijalankan oleh test suite pada SQLite in-memory.

- Dashboard Home: focused regression **7 test lulus (168 assertions)**, termasuk company-wide Super Admin/Admin, self-scope User, metrik jam, capability payload, serta agregasi dua Closing Event pada Tanggal Mulai yang sama tanpa duplikasi multi-hari.
- Browser QA Dashboard Home: desktop 1536×1024 mendekati reference dengan empat summary card, grafik sebagai focal point, dua panel kanan, dan Akses Cepat; tablet 768×1024 memakai summary 2×2; mobile 375×812 memakai single column dan chart scroll internal tanpa page-level overflow. Selector periode/empty state Rp0/31 hari berhasil dan console bersih.
- Production build Dashboard terbaru berhasil dengan **821 modules transformed**; Pint file PHP terkait dan `git diff --check` bersih dari whitespace error task ini.
- Migration additive `tanggal_selesai`: siklus **migrate → rollback satu step → migrate** berhasil pada database development; existing NULL tetap valid sebagai event satu hari.
- Lima kasus manual berbasis transaksi rollback berhasil: single-day, multi-day inclusive, overlap Agustus/September, validasi end-before-start, dan export multi-hari satu row.
- Workbook aktual berhasil dibuka dan diverifikasi: 1 data row, 14 kolom, Tanggal Mulai/Tanggal Selesai terpisah, dan Harga Total numeric hanya satu kali.
- Focused Closing Event regression: **11 test lulus (257 assertions)**; production build **821 modules**, Pint, PHP lint, dan diff check berhasil.
- Audit akun development menemukan satu mismatch: akun `arif` dengan Jabatan Direktur masih memiliki role `admin`. Sinkronisasi memperbaruinya menjadi `super_admin`; dry-run berikutnya bersih dengan 0 mismatch.
- Verifikasi transaksional yang di-rollback membuktikan lima skenario: Manajer→Direktur, Direktur→Manajer, Supervisor→Facility, Karyawan tanpa akun, serta akun inactive. Hanya `role_id` berubah; username, hash PIN, `is_active`, dan `must_change_pin` tetap.
- Focused regression Employee: **29 test lulus (475 assertions)**. Pint file PHP terkait dan `git diff --check` berhasil; warning yang tersisa hanya normalisasi line-ending existing.
- Production build berhasil dengan **821 modules transformed**.

- Revisi window Absensi diverifikasi di browser aktual: Super Admin mendapat controls mutation pada 20 dan 19 Agustus 2026, sedangkan 18 Agustus read-only. Save tanggal 19 berhasil dan record QA sementara telah dibersihkan kembali.
- Validasi backend ringan membuktikan today/yesterday valid, tanggal dua hari lalu ditolak dengan pesan window, future ditolak dengan pesan khusus, serta Admin/User tetap unauthorized pada mutation.
- Browser QA Admin dan User pada 19 Agustus membuktikan halaman tetap company-wide read-only tanpa controls Simpan/Edit/Export.
- Build setelah revisi window Absensi berhasil dengan **819 modules transformed**; PHP lint, Pint, dan `git diff --check` berhasil. Automated test tidak ditambah/dijalankan sesuai instruksi stakeholder.
- Revisi final Closing Event diverifikasi pada browser lokal dengan akun Super Admin, Manager, SPV Marcom, SPV Marketing, Marketing biasa, OPS, dan Marcom biasa. Capability/menu/action/export sesuai matrix; OPS dan Marcom biasa mendapat 403 pada direct URL.
- Download dari tombol `Export Excel` berhasil setelah koreksi konstruksi periode Carbon. Workbook `closing-event-agustus-2026.xlsx` berisi 5 event terurut, 13 kolom bisnis, lokasi multiple dalam satu cell, nilai nullable `-`, dan Harga Total numeric berformat Rupiah.
- Data Closing Event browser menampilkan 11 kolom final tanpa Kontak/Harga Total; Detail tetap menampilkan keduanya dan seluruh informasi sistem. Highlight ongoing tetap aktif pada dua event tanggal 20 Agustus 2026.
- Periode export kosong redirect dengan flash error; direct endpoint export actor unauthorized mendapat HTTP 403.
- `npm.cmd run build`: berhasil, **819 modules transformed**. Pint file PHP terkait berhasil; `git diff --check` tidak menemukan whitespace error (hanya warning line-ending existing).
- Automated test tidak ditambah dan full suite tidak dijalankan sesuai instruksi stakeholder pada revisi final Closing Event ini.
- Update highlight Closing Event: **11 test lulus (257 assertions)**; test memastikan beberapa event pada tanggal hari ini seluruhnya mendapat `isOngoing=true`, sedangkan tanggal lain false.
- Build setelah update highlight berhasil dengan **819 modules transformed**; Pint dan `git diff --check` berhasil. Database development belum memiliki event tanggal 20 Agustus 2026 sehingga visual row aktual belum dapat diamati tanpa menambahkan data dummy.
- Manual HTTP QA export Data Karyawan: Super Admin berhasil mengunduh `data-karyawan-aktif.xlsx` dan `data-karyawan-nonaktif.xlsx`; Admin dan User mendapat HTTP 403 pada direct URL.
- Inspeksi workbook Data Karyawan: file aktif berisi 11 baris dan file nonaktif 2 baris, masing-masing memiliki tepat 17 kolom requirement, terurut nama ASC/id ASC, tanpa Foto KTP, path dokumen, username, role akun, PIN, atau hash PIN.
- Nilai NIK dan No. HP dipaksa sebagai teks agar format identifier serta nol awal tidak berubah di Excel. Header, border, column width, filter, freeze pane, dan formula-error scan telah diverifikasi; kedua workbook dapat diimpor dan dirender ulang.
- `npm.cmd run build`: berhasil; **818 modules transformed**. Route export terdaftar sebelum route detail dinamis dan `git diff --check` bersih dari whitespace error.
- Automated test tidak ditambah dan full test tidak dijalankan sesuai instruksi task export Data Karyawan. Browser berhenti di halaman login; QA UI authenticated belum diklaim karena kredensial tidak dimasukkan melalui browser tanpa persetujuan tindakan saat itu.

- Update Attendance final: migration `2026_08_19_000001_add_attendance_times_to_absensi_table` berhasil pada database development.
- Manual HTTP QA: Super Admin menyimpan `H` dengan `jam_masuk=08:03` dan jam keluar kosong, mengisi `jam_keluar=17:05`, lalu perubahan H→I terbukti membersihkan kedua jam. Data akhir dikembalikan ke kondisi hadir pagi.
- Manual permission QA: Admin dan User masing-masing menerima 11 karyawan company-wide dengan capability read-only; direct store dan export sama-sama mendapat HTTP 403.
- Manual Excel QA: export Agustus 2026 menghasilkan `absensi-karyawan-agustus-2026.xlsx` (HTTP 200, MIME `.xlsx`, 10.618 byte) dengan tiga sheet berurutan: `17 Agt 2026`, `18 Agt 2026`, dan `19 Agt 2026`. Inspeksi serta render workbook membuktikan delapan kolom final, nomor reset per sheet, nama terurut, jam NULL menjadi `-`, dan tidak ada formula error.
- Manual permission QA export: Admin dan User masing-masing mendapat HTTP 403 pada direct URL; periode kosong redirect ke halaman Absensi dengan flash error dan tidak mengirim workbook kosong.
- `npm.cmd run build` setelah update Attendance: berhasil; **818 modules transformed**.
- `composer validate`, package discovery, PHP lint, dan Pint untuk file export/controller: berhasil. `maatwebsite/excel` 3.1.70 aktif; Dompdf sudah tidak terpasang.
- Browser UI QA tombol export belum dapat dijalankan karena browser internal menolak trusted runtime path. Visibility tetap diverifikasi dari capability payload dan conditional Vue; tidak ada klaim browser visual QA.
- Automated test **tidak dijalankan dan tidak ditambah** pada update Attendance ini sesuai instruksi stakeholder. Angka test di bawah adalah snapshot regression task sebelumnya, bukan hasil task Attendance ini.
- `php artisan route:list --except-vendor`: berhasil; **63 route aplikasi** terdaftar.
- `php artisan test`: berhasil; **95 passed, 1.227 assertions**.
- `npm.cmd run build`: berhasil; **818 modules transformed**.
- Migration Closing Event: `migrate → rollback --step=1 → migrate` berhasil pada database development.
- `ClosingEventMasterSeeder`: idempotent dan tervalidasi menghasilkan 7 PIC, 28 Jenis Event, serta 11 Lokasi melalui automated test.
- Migration `must_change_pin`: `migrate → rollback --step=1 → migrate` berhasil pada database development.
- `vendor/bin/pint`: berhasil dan merapikan file PHP terkait.
- `git diff --check`: tidak menemukan whitespace error; hanya warning normalisasi line-ending pada tiga dokumen existing Dashboard Home/Employee.
- Catatan environment Windows: `npm run build` melalui `npm.ps1` ditolak PowerShell Execution Policy; penggunaan executable `npm.cmd` berhasil. Ini bukan kegagalan source aplikasi.
- Visual browser QA lintas role untuk Data Karyawan belum dijalankan karena browser lokal meminta login dan penggunaan kredensial development menunggu izin eksplisit pengguna.

## Current Focus

Fokus kode terakhir adalah **status bisnis Closing Event**: status hanya `aktif`/`dibatalkan`; pembatalan tidak menghapus record, menyimpan audit, dapat direaktivasi, tetap masuk export histori, tetapi tidak masuk ongoing maupun agregat Dashboard.

## Recent Work Log

### 23 Agustus 2026 — Status Aktif/Dibatalkan Closing Event

- Menambahkan migration additive `status_event`, `alasan_pembatalan`, `cancelled_at`, dan `cancelled_by`; event existing dan baru default aktif.
- Menambahkan transisi aktif→dibatalkan dengan alasan wajib serta actor/waktu authoritative dari backend, dan reaktivasi tanpa menghapus metadata pembatalan terakhir.
- Menambahkan filter/status badge list, kontrol status pada Edit, informasi pembatalan pada Detail, dan empat kolom histori pembatalan pada Export Excel.
- Mengunci `isOngoing`, chart nilai, Event Bulan Ini, ongoing hari ini, serta total pengunjung Dashboard agar hanya memakai event aktif.
- Menyinkronkan PRD, Permissions, dan UI Spec tanpa mengubah capability matrix Closing Event.

### 22 Agustus 2026 — Redesign Dashboard Home Operasional

- Mengganti Kalender Kerja dan Karyawan Terbaru dengan grafik Pendapatan Harian, Ringkasan Closing Event, serta Akses Cepat capability-aware sesuai reference baru.
- Mengubah empat card menjadi Karyawan Aktif, Hadir, Terlambat, dan Izin/Alfa; Ringkasan Absensi kini juga mencakup Pulang Awal.
- Grafik mengagregasi `closing_event.harga_total` satu kali pada `tanggal` (Tanggal Mulai), mengisi seluruh tanggal kalender, dan tidak membagi/menggandakan nilai event multi-hari.
- Ringkasan event memakai overlap bulan dan inclusive ongoing range; payload chart/event hanya dikirim bagi actor dengan capability View Closing Event.
- Menyinkronkan README, PRD, Permissions, dan UI Spec Dashboard Home dengan struktur serta access behavior terbaru.

### 22 Agustus 2026 — Closing Event Multi-Hari

- Menambahkan migration additive `closing_event.tanggal_selesai DATE NULL` tanpa rename/backfill kolom `tanggal` existing.
- Menambahkan validation `after_or_equal:tanggal`, cast model, dan scope overlap reusable untuk list serta export.
- Mengubah ongoing menjadi inclusive range `tanggal <= today <= tanggal_selesai ?? tanggal` setelah menormalisasi tanggal kalender ke zona `Asia/Jakarta`.
- Menambahkan Tanggal Selesai opsional pada Create/Edit, range compact pada list, serta Tanggal Mulai/Selesai terpisah pada Detail.
- Export sekarang memiliki 14 kolom dengan Tanggal Mulai dan Tanggal Selesai; event multi-hari tetap satu row dan Harga Total numeric hanya satu kali.
- Verifikasi: migration rollback cycle berhasil, lima kasus manual lulus, workbook aktual valid, **11 test/257 assertions**, build **821 modules**, Pint, dan diff check berhasil.

### 22 Agustus 2026 — Sinkronisasi Jabatan → Role → Hak Akses

- Menetapkan resolver Jabatan existing sebagai satu sumber mapping untuk Create Account dan update Karyawan.
- Menambahkan sinkronisasi `users.role_id` di dalam transaksi update Karyawan tanpa mengubah username, PIN/hash, `is_active`, atau `must_change_pin`.
- Menambahkan command idempotent `employees:sync-account-roles` dengan dry-run default dan opsi `--apply` untuk memperbaiki akun existing yang tidak sesuai mapping.
- Middleware tetap membaca role melalui relasi database, sehingga request berikutnya langsung memakai hak akses terbaru tanpa role cache di session.
- Memperbarui PRD, Permissions, UI Spec, dan baseline global access control untuk mencatat lifecycle role yang authoritative.
- Menjalankan sinkronisasi development: satu mismatch `arif` (`Direktur`, role lama `admin`) diperbarui menjadi `super_admin`; dry-run ulang menghasilkan 0 mismatch.
- Verifikasi lima skenario perubahan Jabatan berhasil dalam transaksi rollback; focused regression **29 test/475 assertions**, build **821 modules**, Pint, dan diff check berhasil.

### 22 Agustus 2026 — Aturan Jam + Rekap Export Absensi Bulanan

- Menambahkan batas jam masuk maksimal `12:00`, indikator Terlambat setelah `08:30`, serta Pulang Awal sebelum `16:30` tanpa menambah kolom status database.
- Mengembangkan input `HH:MM` keyboard-first dengan stepper mouse satu menit, Enter untuk alur antarkolom/baris, Arrow Up/Down untuk perpindahan antarkaryawan, dan skip row I/A.
- Mengubah workbook bulanan: sheet pertama `Rekap Bulanan`, diikuti seluruh tanggal kalender bulan termasuk tanggal tanpa data; jumlah sheet memakai kalender aktual dan aman untuk leap year.
- Rekap menghitung total Hadir/Izin/Alfa/Terlambat/Pulang Awal per karyawan berdasarkan `tanggal_absensi`.
- Browser QA membuktikan batas `08:30`/`12:00`/`16:30`, stepper, indikator, navigasi Enter/Arrow, skip I/A, dan last-row safety. Workbook Agustus 2026 berisi 32 sheet; simulasi Februari 2028 berisi 30 sheet sampai `29 Feb 2028`. Pint, PHP lint, build 821 modul, dan diff check berhasil.

### 20 Agustus 2026 — Window Input/Edit Absensi Hari Ini + Kemarin

- Mengganti aturan mutation Super Admin dari hanya hari berjalan menjadi hari berjalan dan satu hari kalender sebelumnya.
- Menambahkan `canMutateDate`, shortcut `Kemarin`/`Hari Ini`, serta sinkronisasi form ketika berpindah tanggal pada halaman Inertia yang sama.
- Menolak tanggal sebelum kemarin dan future di backend dengan pesan berbeda; perhitungan memakai Carbon zona `Asia/Jakarta` sehingga aman melintasi bulan, tahun, dan leap year.
- Permission Admin/User dan format Export Excel tidak berubah.
- QA browser membuktikan today/yesterday editable, dua hari lalu read-only, dan Admin/User tetap read-only. Build 819 modul, PHP lint, Pint, dan diff check berhasil; automated test tidak ditambah/dijalankan sesuai instruksi stakeholder.

### 20 Agustus 2026 — Revisi Final Closing Event + Export Excel

- Menambahkan `canExportClosingEvent` untuk Super Admin, seluruh Manager/Manajer, SPV Marcom, SPV Marketing, dan seluruh karyawan Marketing; kelompok lain tetap denied.
- Mengubah list menjadi 11 kolom final, memindahkan Kontak/Harga Total ke Detail, serta menambahkan Additional/Panitia compact tanpa merusak highlight event hari berjalan.
- Menambahkan endpoint dan workbook Export Excel bulanan company-wide dengan 13 kolom bisnis, lokasi multiple dalam satu cell, harga numeric Rupiah, null `-`, dan sorting `tanggal ASC, id ASC`.
- Manual browser QA lintas akun membuktikan menu/action/export sesuai capability; tombol UI mengunduh workbook dan periode kosong memberi feedback.
- Verifikasi ringan sesuai instruksi: build 819 modul, Pint berhasil, dan diff check tanpa whitespace error. Full automated test tidak dijalankan.

### 20 Agustus 2026 — Highlight Closing Event Hari Berjalan

- Implementasi historis awal menandai `isOngoing` ketika `closing_event.tanggal = hari ini`; aturan ini telah superseded pada 22 Agustus 2026 oleh inclusive range `tanggal` sampai `tanggal_selesai ?? tanggal`.
- Menandai seluruh event pada tanggal yang sama—bukan hanya satu record—menggunakan highlight biru-oranye, aksen kiri, badge `Sedang berlangsung`, dan pulse ringan.
- Menambahkan fallback `prefers-reduced-motion` agar animasi dinonaktifkan tanpa menghilangkan informasi visual.
- Menyinkronkan PRD dan UI Spec tanpa menambah status database atau mengubah authorization/filter/sorting.
- Verifikasi: 11 test Closing Event lulus (257 assertions), build 819 modul, dan Pint berhasil.

### 20 Agustus 2026 — Export Data Karyawan Excel

- Menambahkan endpoint export company-wide khusus Super Admin dengan pilihan status `aktif` atau `nonaktif` dan filename `data-karyawan-{status}.xlsx`.
- Menambahkan modal Export Data pada halaman Data Karyawan; tombol dan capability hanya dikirim untuk Super Admin, sedangkan endpoint tetap dilindungi middleware serta pemeriksaan role backend.
- Menetapkan 17 kolom laporan sesuai requirement, urutan nama ASC/id ASC, format tanggal Indonesia, nilai nullable `-`, serta NIK/No. HP sebagai teks.
- Memastikan query export tidak membawa Foto KTP maupun data autentikasi dan tidak dipengaruhi search/filter/pagination halaman.
- Memperbarui PRD, Permissions, dan UI Spec Employee; memverifikasi dua workbook development, permission 403 Admin/User, build 818 modul, route, Pint, dan whitespace.

### 19 Agustus 2026 — Export Absensi Excel Multi-Sheet

- Mengganti export bulanan PDF menjadi workbook `.xlsx` menggunakan Laravel Excel/PhpSpreadsheet.
- Mengelompokkan `tanggal_absensi` menjadi sheet harian berurutan; row pada setiap sheet diurutkan berdasarkan nama karyawan dan nomor dimulai dari 1.
- Menetapkan delapan kolom final, format jam nullable menjadi `-`, nama sheet tanggal Indonesia, dan filename `absensi-karyawan-{bulan}-{tahun}.xlsx`.
- Mempertahankan export company-wide khusus Super Admin; tombol disembunyikan dan endpoint tetap 403 untuk Admin/User.
- Menghapus view serta dependency Dompdf yang sebelumnya hanya dipakai laporan Absensi.

### 19 Agustus 2026 — Attendance Final: Read-only Lintas Role, Jam Manual, dan Export Bulanan (superseded)

- Menambahkan migration additive `jam_masuk` dan `jam_keluar` bertipe `TIME NULL` tanpa mengubah unique attendance existing.
- Memisahkan capability view/manage/export: semua role dapat view company-wide, tetapi mutation dan export hanya Super Admin.
- Menambahkan input jam manual, validasi urutan jam, serta pembersihan jam frontend+backend untuk status I/A.
- Implementasi export PDF pada entry ini kemudian digantikan oleh export Excel multi-sheet pada task berikutnya.
- Mengubah sidebar menjadi `Kelola Absensi` bagi Super Admin dan `Data Absensi` bagi Admin/User.
- Melakukan manual HTTP/database/export QA; automated test sengaja tidak dijalankan sesuai instruksi task.

### 19 Agustus 2026 — Closing Event End-to-End

- Menambahkan tabel `pic`, `event`, `lokasi`, `closing_event`, dan pivot `closing_event_lokasi` dengan FK/history-safe behavior serta composite primary key.
- Menambahkan master seed idempotent: 7 PIC, 28 Jenis Event, dan 11 Lokasi.
- Mengimplementasikan capability View/Create/Update/Delete/Manage Master dari kombinasi role, jabatan, dan departemen tanpa mengubah mapping akun global.
- Mengimplementasikan list/filter bulan-tahun berdasarkan `tanggal`, pagination 15, sorting tanggal ascending, create/detail/edit/delete, multi-lokasi, audit actor, dan tiga CRUD master.
- Mengaktifkan nested sidebar Closing Event hanya untuk actor berhak; Master Data Event hanya tampil bagi Super Admin.
- Verifikasi: 95 test lulus (1.227 assertions), build Vite berhasil (818 modules), 63 route terdaftar, migration rollback cycle berhasil, dan Pint berhasil.
- Browser visual QA belum selesai karena runtime plugin browser lokal ditolak sebelum tab dapat dibuka; tidak ada klaim visual pixel-accurate.

### 18 Agustus 2026 — Data Karyawan Company-wide Scope & Field Visibility

- Mengubah row scope Data Karyawan menjadi company-wide untuk Super Admin, Admin, dan User tanpa mengubah scope Dashboard Home atau Absensi.
- Mempertahankan mutation, field sensitif, protected Foto KTP, master Jabatan/Departemen, dan account management hanya untuk Super Admin.
- Menetapkan payload Admin/User hanya berisi 12 atribut umum; NIK, alamat, status perkawinan, no. HP, dan metadata Foto KTP tidak diserialisasi.
- Mengaktifkan search nama dan filter Departemen/Jabatan/status untuk Admin/User; pencarian NIK tetap khusus Super Admin.
- Menyinkronkan PRD, Permissions, UI Spec, dan global access-control matrix dengan keputusan stakeholder terbaru.
- Regression verification: 86 test lulus (987 assertions), build Vite berhasil (812 modules), dan Pint berhasil.

### 17 Agustus 2026 — Employee PRD Rebaseline & Account Management

- Menyinkronkan `PERMISSIONS.md`, `UI_SPEC.md`, documentation map, Dashboard map, Employee README, dan access-control matrix dengan PRD Employee terbaru.
- Menambahkan migration `users.must_change_pin` default false; migrate, rollback satu step, dan migrate ulang berhasil.
- Menambahkan create/manage Akun Sistem khusus Super Admin dengan one-account guard, hash PIN, status akun, dan role authoritative dari mapping Jabatan yang dinormalisasi.
- Menolak Jabatan tanpa mapping; tidak ada fallback role dan browser tidak dapat mengirim `role_id` authoritative.
- Menambahkan forced first-PIN flow: login temporary PIN diarahkan ke `/ganti-pin`, middleware memblokir route lain, lalu PIN baru di-hash dan flag dibersihkan.
- Memperkuat login/middleware agar Karyawan nonaktif tidak dapat mengakses sistem, serta mempertahankan reaktivasi Karyawan tanpa auto-enable akun.
- Verifikasi: 79 test lulus (740 assertions), 46 route, build Vite 812 modules, dan Pint file terkait berhasil.
- Visual browser QA: belum terverifikasi karena browser runtime gagal menyiapkan koneksi lokal sebelum halaman dapat dibuka.

### 17 Agustus 2026 — Employee Visual UI & Sidebar Regression Fix

- Mengubah sidebar flat menjadi parent accordion `Kelola Karyawan` dengan child Data Karyawan dan Jabatan & Departemen sesuai permission.
- Menambahkan auto-expand berdasarkan route Employee/master serta active state child yang terpisah.
- Memperbaiki root cause menu Absensi disabled: halaman Employee sebelumnya tidak meneruskan `canViewAttendance`; layout kini memakai role context server-side untuk membentuk link top-level Super Admin tanpa memindahkan route/modul Absensi.
- Memadatkan Data Karyawan menjadi header ringkas, toolbar filter terintegrasi, row/table/pagination compact, status badge stabil, serta action icon.
- Mengubah Jabatan & Departemen menjadi grid dua kolom desktop dengan header berikon, tombol Tambah jelas, tabel tiga kolom compact, action icon, dan modal yang lebih ringkas.
- Verifikasi fungsional: 70 test lulus (643 assertions), build Vite berhasil (811 modules), route list tetap 42, Pint dan diff check berhasil.
- Visual/manual navigation QA: **belum terverifikasi** karena runtime browser lokal gagal tersambung sebelum halaman dapat dibuka.

### 17 Agustus 2026 — Kelola Karyawan

- Implementasi awal list/search/filter/pagination Data Karyawan; row scope awal dari pekerjaan ini telah disupersede oleh keputusan company-wide tanggal 18 Agustus 2026.
- Menjamin field sensitif tidak masuk props Admin/User; Foto KTP disimpan private dan dilayani oleh endpoint Super Admin.
- Menambahkan halaman Create, Detail, Edit, reusable form, Actions create/update/deactivate/exit/delete, serta conditional hard-delete yang menjaga User dan histori Absensi.
- Menambahkan CRUD Jabatan & Departemen dengan delete protection jika master masih digunakan.
- Menyelaraskan `departemen_id` menjadi nullable melalui migration korektif minimal dan memperbaiki eligibility Absensi berdasarkan `tanggal_masuk` tanpa menyembunyikan histori existing.
- Menormalisasi nama visual reference menjadi `references/departemen_jabatan.png`.
- Verifikasi: 70 test lulus (643 assertions), Vite build berhasil (811 modules), route list 42 route, Pint berhasil, dan diff check bersih.
- Visual QA browser: **belum terverifikasi** karena runtime browser lokal gagal tersambung.

### 16 Agustus 2026 — Dashboard Home + Visual QA Fix

- Menetapkan `/dashboard` sebagai Dashboard Home canonical dan `/admin` sebagai redirect backward-compatible tanpa mengubah proteksi `/admin/*`.
- Mengimplementasikan scope global Super Admin, scope departemen Admin tanpa fallback, dan self-only User di backend sebelum props Inertia dibentuk.
- Menambahkan summary database, formula persentase berdasarkan karyawan aktif dalam scope, kalender bulan berjalan, serta latest employees berdasarkan `tanggal_masuk DESC, id DESC`.
- Memperbaiki visual terhadap screenshot approved: banner, empat summary card, proporsi main/right column, kalender grid 7 kolom, serta posisi langsung Karyawan Terbaru di bawah kalender.
- Mengunci calendar/main/sidebar-widget layout menggunakan scoped CSS statis agar tidak bergantung pada runtime/dynamic Tailwind class.
- Visual QA browser: desktop 1280px, tablet 768px, mobile 375px; tanpa overflow atau console error.
- Verifikasi: 57 test lulus (512 assertions), Vite build berhasil (805 modules), dan `git diff --check` bersih selain warning line-ending existing.

### 15 Agustus 2026 — Documentation Audit & Rebaseline

- Mengklasifikasikan seluruh 20 dokumen existing: 12 `CURRENT`, 3 `PARTIAL`, 4 `OUTDATED`, dan 1 `REDUNDANT`/archive candidate.
- Memperbarui documentation map, konteks workstream, aturan agent, architecture cross-reference, dan backlog Landing Page berdasarkan bukti source tanpa mengubah business requirement agar cocok dengan implementation.
- Membuat baseline `docs/DASHBOARD/` untuk Attendance dan status Employee; tidak membuat folder KPI/Closing karena belum aktif.
- Membuat `GLOBAL/ACCESS_CONTROL.md` dan matrix yang memisahkan permission truth dari implementation truth.
- Mencatat delapan mismatch utama dan keputusan tim yang masih `TBD` di `docs/DOCUMENTATION_AUDIT.md`.
- Tidak mengubah application source code.

### 15 Agustus 2026 — Audit source dan sinkronisasi tracker

- Menginventarisasi route, controller, request, middleware, model, migration, seeder, Vue pages/layout/components, serta test aktual.
- Mengoreksi status Absensi dari “baru desain” menjadi implementasi Super Admin yang sudah diuji, dengan status modul keseluruhan tetap `PARTIAL`.
- Mengonfirmasi CRUD Media Berita dan Event Promo tersedia; modul CMS lain belum memiliki CRUD.
- Mengonfirmasi KPI dan Closing Event belum diimplementasikan meskipun tampil sebagai menu `(Soon)`.
- Menjalankan test dan production build dengan hasil berhasil.

### 15 Agustus 2026 — Desain Data Absensi disepakati

- Referensi desktop Data Absensi diterima.
- Requirement menetapkan H/I/A, catatan opsional, unique karyawan/tanggal, edit hanya hari berjalan, dan akses Super Admin.
- Catatan historis: entry ini mendahului implementasi yang sekarang sudah tersedia di working tree.

### 15 Agustus 2026 — `LOG.md` dibuat

- Tracker awal dibuat sebagai entry point progres proyek.
- Catatan historis awal telah digantikan oleh hasil audit source aktual pada entry terbaru di atas.

## Known Issues / Technical Debt

1. **Working tree belum bersih.** Banyak perubahan/untracked file berasal dari pekerjaan sebelum audit; commit/baseline perlu ditetapkan agar status yang dilacak dapat direproduksi.
2. **Visual QA Kelola Karyawan tertunda.** Runtime browser bawaan kembali gagal tersambung pada task UI/navigation; klik Dashboard/accordion/Absensi serta desktop/tablet/mobile perlu diperiksa ulang pada halaman aktual sebelum klaim visual/navigation final.
3. **Navigasi internal CMS tidak sinkron.** Sidebar menandai `CMS (Soon)` padahal Media Berita dan Event Promo sudah memiliki CRUD; keduanya juga belum ditautkan dari layout dashboard internal.
4. **Audit actor Absensi belum tersedia pada schema existing.** PRD final menggambarkan `created_by/updated_by`, tetapi migration awal Absensi tidak memiliki kedua kolom dan task final hanya menginstruksikan migration additive jam. Implementasi tidak mengarang kolom audit tambahan; keputusan migration audit perlu dikonfirmasi terpisah.
5. **Absensi mengirim seluruh karyawan aktif dalam satu request.** Validasi ini sesuai requirement saat ini tetapi perlu ditinjau jika jumlah karyawan besar.
6. **Foto profil belum memiliki sumber khusus.** `foto_ktp` tidak semestinya otomatis dipakai sebagai avatar publik/internal; UI memakai inisial.
7. **Model label Wahana belum sesuai model relasional PRD.** Label disimpan sebagai string dipisah koma pada `wahana.label`; filter AND bekerja di client, tetapi kategori/label/assignment dinamis dan CRUD-nya belum tersedia.
8. **Site Settings belum memiliki layer aplikasi.** Tabel ada, tetapi model/controller/UI belum ditemukan.
9. **PIN reset belum memiliki workflow.** Tabel ada, tetapi route/controller/UI belum ditemukan.
10. **Mapping role Jabatan bersifat tertutup.** Jabatan di luar kategori PRD (Dirut/Direktur/Admin Sistem, Manajer/Manager/Supervisor, Mitra/Operasional/OPS/Facility/FLT) ditolak sampai stakeholder menentukan mapping.
11. **Kebijakan delete Karyawan belum final di PRD.** Implementasi sementara tetap conditional hard delete dan menolak User/Absensi/dependency historis; deactivate adalah flow normal.
12. **Landing Page belum release-ready.** Masih terdapat placeholder/aset Figma sementara, keputusan Detail Media Berita terbuka, dan checklist visual/responsive belum memiliki bukti final.
13. **Aset publik belum sepenuhnya konsisten.** Media Berita dan Event Promo membentuk URL storage di backend; beberapa payload Wahana/Mitra/Galeri masih meneruskan path mentah dan perlu diverifikasi terhadap data production/storage aktual.
14. **File page bawaan tidak terpakai.** `resources/js/Pages/Dashboard.vue` dan `Welcome.vue` ada tetapi tidak terhubung ke route aktif; jangan hapus tanpa audit khusus.
15. **Responsive QA tabel Closing Event belum diulang pada task final.** Desktop, Detail, permission lintas role, download, dan workbook sudah diverifikasi pada browser lokal; tablet/mobile tetap mengandalkan responsive fallback existing.
16. **Responsive QA Attendance belum lengkap.** Browser QA desktop untuk Super Admin/Admin/User serta window today/yesterday/historical sudah dilakukan pada revisi 20 Agustus; tablet/mobile belum diperiksa ulang.
17. **Migration/status Closing Event belum diverifikasi pada MySQL development.** Service MySQL lokal tidak aktif saat task 23 Agustus; schema dan transisi berhasil pada SQLite terisolasi, tetapi `php artisan migrate` serta browser QA development perlu diulang setelah MySQL aktif.

18. **Workspace Codex lama menyimpan sandbox root yang stale.** Task yang dibuat sebelum repository dipindahkan tetap menunjuk path lama dan memerlukan elevated; buat task/project lokal baru pada `D:\kampoeng-radja` untuk pekerjaan normal.
19. **Migration dan browser QA CMS Hero masih pending di MySQL development.** Source, lint, build, route, serta test SQLite sudah lulus, tetapi MySQL lokal belum aktif saat recovery terakhir.
## Next Step

**Satu task berikutnya yang direkomendasikan:** dari task Codex baru pada `D:\kampoeng-radja`, aktifkan MySQL development, jalankan `php artisan migrate:status`, review seluruh migration pending (termasuk CMS Hero dan modul aktif lain), lalu jalankan migration normal serta browser QA CMS Hero tanpa reset database.

Backlog berikutnya: sisa CMS minimum Landing Page dan responsive QA lanjutan. KPI tetap tidak aktif; Closing Event sudah aktif dan implemented.

## Handoff Quick Start

Agent berikutnya cukup memulai dengan urutan ini:

1. Baca bagian `Current State`, `Module Status`, `Known Issues`, dan `Next Step` di file ini.
2. Jalankan `git status --short` karena working tree saat audit belum bersih.
3. Buka `AGENTS.md` dan dokumen khusus modul yang akan dikerjakan.
4. Untuk Dashboard, mulai dari `docs/DASHBOARD/README.md`, access-control global, lalu dokumen modul.
5. Verifikasi source terkait; jangan menganggap label menu `(Soon)` sebagai implementasi atau 403 sebagai permission final.
6. Untuk Landing Page, ikuti urutan baca di `docs/README.md` dan protokol Figma-first.
7. Perbarui entry terbaru, hasil test/build, technical debt, mismatch, dan next step di file ini setelah pekerjaan selesai.
8. Sebelum perubahan massal akun, jalankan `php artisan employees:sync-account-roles` sebagai dry-run; gunakan `--apply` hanya setelah daftar mismatch diperiksa.
