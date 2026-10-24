# 02: Products, locations, counterparties

**What to build:** The owner manages the catalogue: products (name, optional barcode, pieces per block, archived), warehouses with one level of sub-warehouses, and counterparties. Employees can view products and quick-create counterparties. Archived items disappear from pickers but stay in history. Spec stories 25–29; decisions Q3, Q4, Q24, Q40, D12; ADR-0004.

**Blocked by:** 01

**Status:** ready-for-agent

- [x] Products: create, edit, archive/unarchive; pieces per block ≥ 1; barcode optional and unique per store; no price fields; list with search by name/barcode.
- [x] Locations: warehouses and sub-warehouses (parent must be a warehouse; no deeper nesting); rename; archive instead of delete. _Asosiy ombor_ from sign-up appears.
- [x] Counterparties: one list (name, optional phone, note); create/edit; a quick-create modal component reusable by later document screens.
- [x] Employees see products read-only and can create counterparties; managing products and locations is owner-only (403 otherwise).
- [x] Shared Uzbek list-page pattern (search, empty state "Hali … yo'q" + main button) introduced here and reused later.
- [x] Expense and income categories seeded globally (codes, Uzbek names, direction, counts-in-net-profit flag, employee-may-use flag) per spec, ready for ticket 04.
- [x] HTTP feature tests: CRUD, archive behaviour, one-level rule, barcode uniqueness per store, permissions, store isolation.
