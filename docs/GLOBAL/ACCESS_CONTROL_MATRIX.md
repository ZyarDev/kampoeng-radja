# Access Control Matrix — Kampoeng Radja

**Status:** Final as-built baseline, except Dashboard payload details and KPI formalization.
**Canonical reconciliation:** `docs/AS_BUILT_PRD_RECONCILIATION.md`

## Authentication

| Area | Guest | Active authenticated account | Inactive account |
|---|---|---|---|
| Public pages | View | View | View |
| Login | View | Redirect/normal session | Rejected |
| Internal routes | No | According to module capability | No |
| First PIN change | No | Own account when `must_change_pin=true` | No |

## Dashboard Home

Dashboard access is allowed for active internal accounts. Final card and payload scope remains partial/TBD. Module permissions are evaluated by the module capability, not by dashboard card visibility.

## Employee

| Role | View basic company-wide | Employee mutation | Account/master management | Sensitive files | Export |
|---|---:|---:|---:|---:|---:|
| `super_admin` | Yes | Yes | Yes | Yes | Yes |
| `admin` | Yes | No | No | No | No |
| `user` | Yes | No | No | No | No |

Admin/User do not receive NIK, address, marital status, KTP/path, or private employee signature. Phone is not sensitive under the final decision.

## Attendance

| Role | View company-wide | Input/edit today | Input/edit yesterday | Older mutation | Export |
|---|---:|---:|---:|---:|---:|
| `super_admin` | Yes | Yes | Yes | No | Yes |
| `admin` | Yes | No | No | No | No |
| `user` | Yes | No | No | No | No |

## CMS

| Condition | View | Create | Update | Delete | Upload/manage |
|---|---:|---:|---:|---:|---:|
| `super_admin` | Yes | Yes | Yes | Yes | Yes |
| Department `MARCOM` | Yes | Yes | Yes | Yes | Yes |
| `admin` + Placement `MARCOM` | Yes | Yes | Yes | Yes | Yes |
| Other | No | No | No | No | No |

## Closing Event — Data Closing Event

| Condition | View | Create | Update | Delete | Export |
|---|---:|---:|---:|---:|---:|
| `super_admin` | Yes | Yes | Yes | Yes | Yes |
| Department `MARKETING` or `MARCOM` | Yes | Yes | Yes | Yes | Yes |
| `admin` + Placement `MARKETING` or `MARCOM` | Yes | No | Yes | No | Yes |
| Other active internal user | Yes | No | No | No | No |

## Closing Event — Master Data Event

| Condition | View | Create | Update | Delete |
|---|---:|---:|---:|---:|
| `super_admin` | Yes | Yes | Yes | Yes |
| `admin` + Placement `MARKETING` or `MARCOM` | Yes | Yes | Yes | Yes |
| Department `MARKETING` or `MARCOM` | Yes | No | No | No |
| Other | No | No | No | No |

## KPI

KPI authorization is stateful and cannot be represented by a role-only CRUD table. Capability depends on actor identity, employee hierarchy, assignment, selected period, readiness state, deadline, signature revision, correction state, and Super Admin timing. See `docs/DASHBOARD/KPI/PRD.md` and `docs/AS_BUILT_PRD_RECONCILIATION.md`.

## Organization and account source

- Canonical roles: `super_admin`, `admin`, `user`.
- New account role: `jabatan.role_id`.
- Jabatan, Departemen, and Penempatan are separate concepts.
- Department/Placement values are normalized by module access helpers, not numeric IDs.

## Protected files

KTP, new employee signatures, KPI evidence, and KPI signature snapshots use private storage and protected record-based delivery. CMS content remains public. Legacy internal public paths are read compatibility only.
