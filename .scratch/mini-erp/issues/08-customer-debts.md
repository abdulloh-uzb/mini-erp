# 08: Customer debts

**What to build:** Users see customer debts per counterparty and record debt payments from the customer card, the debts list or Kassa → + Kirim. A customer's debt is one balance, displayed as paying off the oldest sale first. The owner enters opening customer debts and writes off uncollectable debt as _Umidsiz qarz_. Spec stories 20 (customer part), 87–90, 92; decisions Q19, Q20, Q48b, Q54, D5, D9.

**Blocked by:** 07

**Status:** ready-for-agent

- [ ] Customer debts list with balances; customer card with debt history (sales debt parts, payments, returns, write-offs).
- [ ] One debt payment form opened from three places (customer pre-filled where known): amount > 0 and ≤ debt ("Summa qarzdan oshmasligi kerak (qarz: …)"), "To'liq" button, method, note; creates cash operation + debt entry.
- [ ] Per-sale paid/debt display allocates payments oldest sale first at read time.
- [ ] Opening balance **Mijoz qarzlari** tab.
- [ ] Owner-only write-off of part or all of a debt: reduces debt, records an _Umidsiz qarz_ P&L expense, no cash operation.
- [ ] Domain tests: balance math, oldest-first allocation, write-off effects. HTTP tests: employee pays debt, employee cannot write off, limits.
