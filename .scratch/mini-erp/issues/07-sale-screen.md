# 07: Sale screen (fast document)

**What to build:** The sale screen: the search/barcode box has focus, a scan or Enter adds +1 dona, prices pre-fill from the previous sale price, the owner sees average cost and a below-cost warning, the right panel takes payment per method and shows _Qarzga qoladi_, a customer is required only for a debt part, F2 parks the sale as a draft, F9 executes, and a printable receipt follows. Spec stories 30–49; decisions Q9, Q18–Q20, Q27, Q35, Q48b, D1, D15.

**Blocked by:** 03, 04, and a `/prototype` of the sale screen — done: branch `prototype/sale-screen` (commit 5c693b3, `/prototype/sale?variant=A|B|C`).

**Prototype outcome (2026-10-24):** the owner picked **variant B, "Kassa: plitkalar + chek"** over A (table + right panel) and C (two-step list → payment sheet). Build the real screen from B (rewrite it properly; don't copy the prototype code):

- Left: the autofocused search/scan box plus a filter box, and a grid of product tiles (name, previous sale price or "Narx yo'q", "Qoldiq" at the location, a badge with the quantity already in the cart). Tapping a tile adds +1 dona; tiles with no stock are disabled.
- Right: a receipt-style cart column. Its top has parked sales as tabs ("Joriy sotuv" plus one tab per draft, showing customer/number and total), which is how "Qoralamalar (N)" appears, then location + customer side by side. Each line shows name and Summa, a −/+ stepper showing "N blok + M dona", "+1 blok", an editable Narx/dona (red when empty), Qoldiq (red when short) and, for cost-permitted users, Tannarx + "Narx o'rtacha tannarxdan past".
- The bottom of the cart: a large Jami, payment-method toggle buttons (showing the amount entered per method), one amount box for the selected method, "Qolganini" (fills the rest into that method), "To'liq naqd", _Qarzga qoladi_, the change helper while Naqd is selected, and the buttons Saqlash F2 / Bajarish F9 (large and green).

**Status:** ready-for-agent

- [x] Layout follows the prototype outcome (variant B above): search/barcode autofocus, lines (Mahsulot, Blok, Dona, Narx/dona, Summa; average cost + "Narx o'rtacha tannarxdan past" for cost-permitted users only), right panel (location pre-filled with last used, customer, total, Naqd/Karta/Bank o'tkazmasi, "To'liq naqd", _Qarzga qoladi_, change helper not stored).
- [x] Previous price from the store's last executed sale line of that product; empty and required when none.
- [x] Live stock at the location per line ("Qoldiq: 10 blok + 4 dona").
- [x] Customer search + quick-create; required when debt > 0 ("Qarzga sotish uchun mijozni tanlang"); shows the customer's current debt.
- [x] Saqlash (F2) saves a draft and resets the screen; "Qoralamalar (N)" reopens parked sales.
- [x] Bajarish (F9) confirms, then executes: stock out with row locks, sale lines store average cost, paid part → cash-in operations, unpaid part → customer debt entry; refused on short stock, payments > total, missing price, missing customer.
- [x] After execution: toast "Sotuv №N bajarildi", receipt view with "Chop etish", fresh sale ready.
- [x] Domain tests: stored line cost, debt split, overpayment refused, sequential executions never oversell. HTTP tests: employee executes, cost fields absent for employees, previous price pre-fill.
