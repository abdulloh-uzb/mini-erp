# 10: Home pages, owner inbox, sales report

**What to build:** The owner's home shows the opening-balance card (until done), drafts waiting for execution, today's figures, cash per method and debt totals; the sidebar shows count badges on owner-executed document lists. The employee's home has big action buttons, their drafts and their sales today. The sales report shows totals, by product, by employee and paid vs debt per sale, with cost and profit only for the owner. Spec stories 77–78, 105–107, 112–114; decisions Q39, D3, D4.

**Blocked by:** 05, 06, 09

**Status:** ready-for-agent

- [ ] Owner home widget "Bajarishni kutayotgan qoralamalar": drafts of purchases, sales returns and counts with author, last change, "Ochish" link to the filtered list.
- [ ] Sidebar count badges for the owner on Xaridlar, Qaytarishlar, Inventarizatsiyalar.
- [ ] Owner home: today's revenue, number of sales, gross profit, cash received; cash per method; customer debt, supplier debt and loans outstanding totals.
- [ ] Employee home: Yangi sotuv, Yangi ko'chirish, Qarz to'lovi, Xarajat buttons; "Mening qoralamalarim"; my sales today (count, revenue).
- [ ] Sales report for a date range: revenue (net of returns), number of sales, by product (blok/dona, revenue, profit), by employee, paid vs debt per sale; cost and gross profit omitted server-side for employees.
- [ ] Report query module tests (domain seam) for sales report numbers; HTTP tests for cost hiding and home widgets per role.
