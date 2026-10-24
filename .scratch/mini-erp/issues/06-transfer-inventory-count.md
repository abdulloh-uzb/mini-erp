# 06: Transfer and inventory count

**What to build:** Users move pieces between locations with a transfer and undo one with "Teskari ko'chirish". Users count products at a location (scan, search, or add all products with stock there); employees count blind and save drafts, the owner reviews expected vs counted and executes. Only listed products change; shortages and surpluses are valued at average cost for P&L. Spec stories 66–76; decisions Q23, Q36, Q37, D6.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Transfer: from/to locations (equal refused "Bir xil joy tanlangan"), lines with source stock shown; employees and owner can execute; source stock checked with row locks; no money or cost effect.
- [ ] Executed transfer offers "Teskari ko'chirish", which opens a swapped draft.
- [ ] Inventory count: one location, lines by scan (+1 dona, merging), search or "Joydagi barcha mahsulotlarni qo'shish"; counted as blok + dona; save repeatedly.
- [ ] Employees never receive expected/difference values (blind count); the owner sees expected, counted, difference and shortage/surplus totals at average cost.
- [ ] Only the owner executes counts; expected is recomputed at execution; differences adjust stock; shortage/surplus value stored on lines for P&L; unlisted products unchanged.
- [ ] Domain tests: transfer stock check, count recompute after an intervening sale/transfer, shortage and surplus values, unlisted products untouched. HTTP tests: blind props for employees, execute permissions.
