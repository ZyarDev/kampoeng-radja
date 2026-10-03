# PERMISSIONS — Closing Event

**Status:** FINAL — AS-BUILT
**Canonical source:** `app/Support/ClosingEventAccess.php` and `docs/AS_BUILT_PRD_RECONCILIATION.md`

Data Closing Event and Master Data Event use separate authorization scopes.

## Data Closing Event

| Condition | View | Create | Update | Delete | Export |
|---|---:|---:|---:|---:|---:|
| `super_admin` | ✅ | ✅ | ✅ | ✅ | ✅ |
| Department `MARKETING` or `MARCOM` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `admin` + Placement `MARKETING` or `MARCOM` | ✅ | ❌ | ✅ | ❌ | ✅ |
| Other active internal user | ✅ | ❌ | ❌ | ❌ | ❌ |

Data Closing Event is company-wide. Active internal users outside the mutation groups retain read-only access.

## Master Data Event

Master Data Event includes Master PIC, Master Jenis Event, and Master Lokasi.

| Condition | View | Create | Update | Delete |
|---|---:|---:|---:|---:|
| `super_admin` | ✅ | ✅ | ✅ | ✅ |
| `admin` + Placement `MARKETING` or `MARCOM` | ✅ | ✅ | ✅ | ✅ |
| Department `MARKETING` or `MARCOM` | ✅ | ❌ | ❌ | ❌ |
| Other | ❌ | ❌ | ❌ | ❌ |

## Backend requirements

- Every view and mutation route checks the relevant capability server-side.
- Frontend visibility is UX only and is not the security boundary.
- Department and Placement are read from authenticated relationships using canonical names, never numeric IDs.
- Null relationships fall back safely: Data Closing Event is read-only; Master Data Event is denied unless a higher-priority rule applies.
- `super_admin` is evaluated before employee relationships.
- Delete/reference guards and existing business validation remain unchanged.

## Historical documentation note

Earlier Manager/Supervisor/Marketing matrices are superseded by the department/placement matrix above. They are retained only in historical logs and must not be used as current authorization guidance.
