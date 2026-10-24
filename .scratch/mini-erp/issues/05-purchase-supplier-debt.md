# 05: Purchase and supplier debt

**What to build:** An employee drafts a purchase (supplier, location, lines in blocks/pieces) without seeing prices; the owner fills prices (pre-filled from the previous purchase price) and payment per method, then executes. Stock increases, average cost is recalculated, the paid part leaves the register and the rest becomes supplier debt. The owner sees supplier debts, pays suppliers, and enters opening supplier debts. Spec stories 20 (supplier part), 50–56, 91; decisions Q8, Q18, Q24, Q54, D11; ADR-0002, ADR-0004.

**Blocked by:** 03, 04

**Status:** ready-for-agent

- [x] Purchase document on the shared form: supplier required (quick-create), location, lines; employees save drafts only and never receive price/sum fields in page props.
- [x] Previous price pre-fill from the store's last executed purchase line of that product; empty when none; execution refused if any price is missing ("Barcha narxlarni kiriting").
- [x] Execution: stock in, average cost blended, payments per method become cash-out operations (≤ method balance), unpaid part becomes a supplier debt entry; payments above total refused.
- [x] Debt ledger (append-only entries per counterparty and side) introduced, with supplier side used here; supplier debts list with balances (owner only).
- [x] Supplier payment from the debts list or Kassa → + Chiqim: amount ≤ debt and ≤ method balance; creates cash operation + debt entry together.
- [x] Opening balance **Yetkazib beruvchi qarzlari** tab.
- [x] Domain tests: average cost after several purchases at different prices, debt split, payment limits. HTTP tests: employee draft hides prices, employee cannot execute, owner executes.
