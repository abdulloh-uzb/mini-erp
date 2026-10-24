# 04: Cash register, expenses, loans

**What to build:** The Kassa page shows the balance per payment method (owner only) and the list of cash operations (everyone). Users record expenses and income from the fixed categories, the owner records withdrawals (_Egasi olgan pul_), and the owner manages loans given and received with repayments and interest. Opening cash and opening loans are added to the opening balance. No cash-out may exceed the method's balance; cash operations are permanent. Spec stories 19, 21, 84–86, 93–102; decisions Q10–Q13, Q25, Q34, D2, D7, D8, D11, D14; ADR-0001, ADR-0005.

**Blocked by:** 03

**Status:** ready-for-agent

- [x] Cash operations (type, direction, payment method cash/card/bank transfer, amount > 0, optional counterparty, category, loan, document, note, author, timestamp), append-only; corrections by an opposite operation.
- [x] Cash balance per (store, method), locked during any cash-out; cash-out above the balance refused with an Uzbek message ("Kassada naqd yetarli emas" or per-method equivalent).
- [x] Kassa page: balance strip Naqd | Karta | Bank o'tkazmasi | Jami for the owner only; operations list with date, type, method, author, counterparty filters; + Kirim / + Chiqim actions.
- [x] Expense / income form: category, amount, method, note (required for _Boshqa_); employees cannot use _Egasi olgan pul_, _Qarz foizi_, _Umidsiz qarz_ (hidden and refused server-side).
- [x] Opening balance **Kassa** tab (cash per method) and **Qarzlar** tab (open loans), executed with the rest of the opening balance.
- [x] Loans (owner only): create given/received with counterparty, principal, method, optional due date → creates its cash operation; list with principal, repaid, balance, due (overdue red); repayment ≤ balance; interest paid/received from the loan card as _Qarz foizi_ / _Olingan foiz_.
- [x] Money as DECIMAL(18,2), displayed "1 250 000 so'm".
- [x] Domain tests: balance per method, cash-out limit, loan balance and repayment limit. HTTP tests: employee category restrictions, employee sees list but not balance, loans owner-only.
