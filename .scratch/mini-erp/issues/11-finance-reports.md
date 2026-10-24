# 11: Foyda va zarar, Pul oqimi, Balans

**What to build:** Three owner-only finance pages. Foyda va zarar for a date range: revenue − cost of goods sold = gross profit − each counted expense category − shortages + surpluses ± interest = net profit, excluding supplier payments, debt payments, loan principal and owner withdrawals. Pul oqimi lists every cash operation in a period grouped by type, category and day. Balans shows cash per method, loans outstanding (given/received) and customer and supplier debt totals now. Spec stories 108–111; decisions Q25, Q48a; ADR-0001.

**Blocked by:** 04, 06, 08, 09

**Status:** ready-for-agent

- [x] Foyda va zarar query module and page with every line of the formula, including _Umidsiz qarz_ write-offs and returns reversing revenue and cost; opening balance never included.
- [x] Pul oqimi: period filter; totals in/out per type, category and day; includes loans and debt payments.
- [x] Balans: current cash per method + total, loans given/received outstanding, customer and supplier debt totals.
- [x] All three owner-only (403 for employees, hidden in sidebar).
- [x] Domain tests on the P&L module covering a scenario with opening balance, purchases, sales (paid and debt), return, count shortage and surplus, expenses (counted and _Egasi olgan pul_), loan with interest, write-off; cash flow and balance totals for the same scenario.
