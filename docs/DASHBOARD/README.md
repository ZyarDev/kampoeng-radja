# Dashboard Internal — Documentation Map

Status workstream: **aktif secara terbatas**

Dashboard Internal menggunakan Laravel, Inertia, Vue, Tailwind, dan session authentication yang sama dengan Landing Page. Visual Dashboard dapat menggunakan screenshot/reference approved; workflow Figma-first hanya wajib jika modul tersebut memang diserahkan melalui Figma.

## Status Modul

| Modul | Requirement | Implementation | Dokumentasi |
| --- | --- | --- | --- |
| Dashboard Home | Role-aware Super Admin/Admin/User | `IMPLEMENTED` | `DASHBOARD-HOME/` |
| Data Absensi | Company-wide monitoring; mutation/export khusus Super Admin | `IMPLEMENTED` | `ATTENDANCE/` |
| Data Karyawan | CRUD, scoped read-only, akun Karyawan | `IMPLEMENTED` | `EMPLOYEE/` |
| Jabatan, Departemen & Penempatan | Master data Employee | `IMPLEMENTED` | `EMPLOYEE/` |
| CMS | Requirement berasal dari Landing Page Fase 1 | Media Berita dan Event Promo tersedia | `LANDING-PAGE/PRD.md` + `LOG.md` |
| KPI | Implemented as-built; PRD formalization required | `IMPLEMENTED` | `DASHBOARD/KPI/PRD.md` + `docs/AS_BUILT_PRD_RECONCILIATION.md` |
| Closing Event | Requirement final role+jabatan+departemen | `IMPLEMENTED` | `CLOSING-EVENT/` |

## Urutan Baca Dashboard

1. `AGENTS.md`
2. `LOG.md`
3. `docs/README.md`
4. `docs/GLOBAL/PROJECT_CONTEXT.md`
5. `docs/GLOBAL/ARCHITECTURE.md`
6. `docs/GLOBAL/ACCESS_CONTROL.md`
7. `docs/GLOBAL/ACCESS_CONTROL_MATRIX.md`
8. Dokumen modul Dashboard terkait
9. Reference visual modul
10. Source dan test aktual

## Aturan Visual

- Screenshot approved menjadi visual truth jika handoff modul berupa screenshot.
- UI spec hanya menjelaskan behavior yang tidak terlihat dari screenshot.
- Jangan mengarang desain yang belum diberikan.
- Jangan mengklaim pixel-accurate tanpa comparison pada viewport reference.

## Aturan Scope

- Data Absensi mengikuti requirement final: seluruh role dapat melihat data company-wide; hanya Super Admin dapat input/edit hari berjalan dan export Excel bulanan multi-sheet.
- Data Karyawan, Jabatan/Departemen, dan account lifecycle mengikuti requirement aktif pada `EMPLOYEE/`.
- Closing Event mengikuti matrix final pada `CLOSING-EVENT/`; KPI mengikuti implementation as-built dan tidak lagi berstatus planned/not started.
