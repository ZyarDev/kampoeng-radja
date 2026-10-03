# Storage & Protected Files — KRIS

**Status:** FINAL — AS-BUILT

## Public content

The public disk and `/storage/...` delivery remain valid for CMS/public content:

- Hero
- Media & Berita
- Promo/Event Promo
- Produk
- Mitra
- Wahana/Tempat Makan
- Galeri Event

## Private content

The local/private disk is required for:

- employee KTP;
- new employee signature uploads;
- Daily Evidence;
- K-OPS Evidence;
- Daily, Individual, OPS, Monthly, and Final signature snapshots.

## Protected delivery

Internal files are delivered through record-based Laravel routes:

- `dashboard.karyawan.photo`
- `dashboard.karyawan.signature`
- `dashboard.kpi.daily.activity.evidence`
- `dashboard.kpi.daily.approval-signature`
- `dashboard.kpi.ops.item.evidence`
- `dashboard.kpi.signature.file`

The route receives an authorized record identifier. The backend resolves the path from the database and does not accept an arbitrary filesystem path. Authentication and record authorization are checked before delivery. Unauthorized access is rejected with 403; missing record/file is 404.

## Legacy compatibility

Existing public internal paths may remain readable while legacy records are being migrated/audited. The fallback order is local/private first, then legacy public. This fallback is compatibility behavior, not the desired destination for new writes.

## Write rule

All new internal uploads and KPI signature snapshots must write to the local/private disk. Public disk writes are reserved for public CMS assets.

## Export

Exports may read private files directly on the backend after the export's existing business authorization succeeds. Export layout and score calculation are independent of the storage disk.
