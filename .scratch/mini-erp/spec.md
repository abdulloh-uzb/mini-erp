# Spec: Mini-ERP for a beverage store

Status: ready-for-agent

Sources: `.scratch/mini-erp/decisions.md` (Q1–Q54, U1–U2, D1–D15), `GLOSSARY.md`, `docs/adr/0001`–`0006`, `docs/research/uzbek-pos-erp-competitors.md`, `docs/research/ux-flows.md` (sections 2–3 are the accepted baseline for screens and flows). Brief: https://telegra.ph/Texnik-topshiriq-Ichimliklar-dokoni-uchun-mini-ERP-10-20

## Problem Statement

The owner of a drinks shop in Uzbekistan keeps stock, sales, debts and cash in notebooks or spreadsheets. They cannot tell, at any moment:

- how many pieces and blocks of each drink sit in each warehouse and sub-warehouse;
- which customers owe them money and how much they owe suppliers;
- how much money is in the cash register, per payment method;
- what they actually earned in a period once the cost of goods, shortages, expenses and loan interest are taken out.

Employees sell on the spot, often by the block and often on debt to small shops, and the owner needs them to work fast without seeing purchase prices or profit, and without being able to quietly change history. Existing products (BILLZ, BITO, MoySklad) are general-purpose: they carry stored prices, editable expense lists, un-postable documents and many features the shop does not need.

## Solution

A desktop web application, in Uzbek (Latin script), where each **store** is an isolated space with one **owner** and any number of **employees**.

- Every movement of goods is a **document** (sale, purchase, sales return, supplier return, transfer, inventory count, opening balance) that is saved as a **draft** and then **executed**; executed documents are permanent and mistakes are fixed by an opposite document.
- Stock is held in **pieces** and entered or shown as **blocks + pieces**, per **location** (warehouse or sub-warehouse).
- Sales and purchases can be paid fully, partly or not at all; the paid part becomes **cash operations** per **payment method**, the rest becomes **customer debt** or **supplier debt**.
- The **cash register** also records debt payments, expenses (from a fixed list of **expense categories**), **loans** and **repayments**.
- Cost of goods sold uses a moving **average cost**; reports show stock balances, sales, **Foyda va zarar** (P&L), **Pul oqimi** (cash flow) and **Balans**.
- Employees sell, transfer, take debt payments and record expenses; purchases, counts and sales returns they only draft, and the owner executes them. Costs, profit, loans and finance reports are owner-only.
- A **super-admin** manages stores (block/unblock, reset owner password) without seeing any store's business data.

## User Stories

### Sign-up, login and access

1. As a visitor, I want to register with my name, phone, password and store name, so that I get my own store and become its owner.
2. As a new owner, I want my store to start with a warehouse called _Asosiy ombor_ and the fixed expense categories, so that I can start working without setup.
3. As a user, I want to log in with my phone (`+998 __ ___ __ __` mask) and password, so that I don't need an email address.
4. As a user, I want a clear error when my phone is already registered, so that I don't create a duplicate account.
5. As an owner, I want to create employees with name, phone and password, so that they can log in to my store.
6. As an owner, I want to reset an employee's password, so that a forgotten password doesn't need SMS or email.
7. As an owner, I want to deactivate an employee, so that someone who left can no longer log in while their history stays.
8. As a user, I want to optionally turn on two-factor authentication or a passkey, so that my account is safer.
9. As a user of a blocked store, I want to be logged out and see "Do'kon bloklangan" when I try to log in, so that I understand why I cannot work.
10. As a super-admin, I want to list all stores with their owner and status, so that I can manage the platform.
11. As a super-admin, I want to block and unblock a store, so that I can stop non-paying or abusive stores without deleting their data.
12. As a super-admin, I want to reset an owner's password, so that owners locked out can get back in.
13. As a super-admin, I want to be unable to open any store's products, documents, cash or reports, so that store data stays private.
14. As a platform operator, I want to create super-admins only from the command line, so that nobody can sign up as one.
15. As an employee, I want to see only the menu items I am allowed to use, so that the interface is not cluttered with things I cannot open.
16. As an employee, I want a direct URL to a forbidden page to be refused, so that hiding menu items is not the only protection.

### Opening balance and setup

17. As a new owner, I want a home card "Boshlang'ich qoldiqni kiriting" until the opening balance is done, so that I don't forget it.
18. As an owner, I want to enter opening stock per location with blocks/pieces and cost per piece, so that average cost starts correctly.
19. As an owner, I want to enter opening cash per payment method, so that the cash register balance is right from day one.
20. As an owner, I want to enter opening customer debts and supplier debts per counterparty, so that existing debts are tracked.
21. As an owner, I want to enter open loans (given or received, principal, optional due date), so that loans already running are tracked.
22. As an owner, I want to save the opening balance many times before executing it, so that I can enter a large stock list over several sessions.
23. As an owner, I want the opening balance to never affect net profit, so that reports only show what happened after I started.
24. As an owner, I want document execution blocked until the opening balance is executed or I click "Boshlang'ich qoldiq yo'q", so that a late opening balance cannot distort average cost and debts.
25. As an owner, I want to manage warehouses and one level of sub-warehouses, so that stock places match my shop.
26. As an owner, I want to create, edit and archive products (name, optional barcode, pieces per block), so that the catalogue stays clean without losing history.
27. As an employee, I want to view products but not create or edit them, so that the catalogue stays under the owner's control.
28. As an employee or owner, I want to quick-create a counterparty (name, phone) inside a sale or purchase, so that I don't leave the screen.
29. As an owner, I want one counterparty list that holds customers, suppliers and loan parties, so that one shop that both buys from me and lends to me is one record.

### Sale (fast document)

30. As an employee, I want the sale screen to open with the cursor in the search/barcode box, so that I can scan immediately.
31. As an employee, I want a scan or Enter to add +1 dona, merging into an existing line of the same product, so that scanning several bottles builds one line.
32. As an employee, I want to type blocks and pieces on a line, so that wholesale orders by the block are quick.
33. As an employee, I want each line's price to pre-fill with the previous sale price of that product, so that I rarely type prices.
34. As an employee, I want a product never sold before to have an empty, required price field, so that no wrong default is used.
35. As an employee, I want to see the current stock of the product at the sale's location ("Qoldiq: 10 blok + 4 dona"), so that I don't promise what isn't there.
36. As an owner, I want sale lines to show average cost and warn "Narx o'rtacha tannarxdan past" when the price is lower, so that typos and loss-making sales are caught.
37. As an employee, I want the location pre-filled with my last-used location, so that I don't pick it every time.
38. As an employee, I want to enter payment amounts per method (Naqd, Karta, Bank o'tkazmasi) and a "To'liq naqd" button, so that mixed payments are easy.
39. As an employee, I want to see "Qarzga qoladi" (remaining debt) live, so that I know what becomes debt.
40. As an employee, I want an optional change helper (received cash → change), so that I give correct change without it being stored.
41. As an employee, I want the customer to become required only when there is a debt part, so that walk-in cash sales need no customer.
42. As an employee, I want to see a selected customer's current debt, so that I can decide whether to sell on debt.
43. As an employee, I want Saqlash (F2) to save the sale as a draft and clear the screen, so that I can park a customer and serve the next one.
44. As an employee, I want a "Qoralamalar (N)" dropdown on the sale screen, so that I can reopen parked sales.
45. As an employee, I want Bajarish (F9) to execute after a confirmation, so that a sale isn't executed by accident.
46. As an employee, I want execution refused with a clear message when a line exceeds stock, so that stock never goes negative.
47. As an employee, I want payments above the total refused, so that overpayment cannot be recorded.
48. As an employee, I want a receipt view with a print button after execution, so that I can give the customer a paper receipt.
49. As an owner, I want two sales executed at the same moment never to oversell the same stock, so that balances stay correct.

### Purchase

50. As an employee, I want to draft a purchase with supplier, location and lines in blocks/pieces, without seeing prices, so that I can record what arrived.
51. As an owner, I want purchase line prices to pre-fill with the previous purchase price, so that I mostly confirm them.
52. As an owner, I want to fill prices and payment on an employee's purchase draft, then execute it, so that goods enter stock with correct cost.
53. As an owner, I want execution refused if any price is missing, so that average cost is never computed from blanks.
54. As an owner, I want an executed purchase to increase stock and recalculate each product's average cost, so that later sales carry the right cost.
55. As an owner, I want the unpaid part of a purchase to become supplier debt and the paid part to leave the register by method, so that I know what I owe.
56. As an owner, I want a purchase paid from a method with too little money refused ("Kassada naqd yetarli emas"), so that the register never goes negative.

### Sales return and supplier return

57. As an employee, I want to create a sales return from an executed sale ("Qaytarish yaratish"), with every line at 0 and its sold/returned/returnable quantities shown, so that I only return what was sold.
58. As an employee, I want returnable quantity enforced ("Ko'pi bilan 2 blok + 0 dona qaytarish mumkin"), so that a sale cannot be returned twice.
59. As an employee, I want the return to show how much reduces the customer's debt and how much is refunded in cash, so that I tell the customer correctly.
60. As an owner, I want to execute sales returns drafted by employees, so that refunds are controlled.
61. As an owner, I want returned pieces to come back at the sale line's stored cost and the revenue to be reversed at the sale price, so that profit stays correct.
62. As an owner, I want to create a supplier return from an executed purchase, limited to purchased minus already returned, so that I send back only what I bought.
63. As an owner, I want a supplier return refused when the location lacks the pieces, so that stock never goes negative.
64. As an owner, I want a supplier return to reduce supplier debt first and then bring money back into the register, so that balances are right.
65. As an employee, I want the supplier return menu hidden from me, so that purchase prices stay private.

### Transfer

66. As an employee, I want to move pieces from one location to another with one document, so that stock places stay accurate.
67. As an employee, I want the same from/to location refused ("Bir xil joy tanlangan"), so that empty transfers aren't made.
68. As an employee, I want execution refused when the source lacks the pieces, so that stock never goes negative.
69. As an owner, I want a "Teskari ko'chirish" button on an executed transfer that pre-fills a swapped draft, so that a mistake is quick to undo.

### Inventory count

70. As an employee, I want to count products at one location by scanning, searching or "Joydagi barcha mahsulotlarni qo'shish", so that I can do partial or full counts.
71. As an employee, I want to enter counted blocks/pieces without seeing the expected quantity, so that I count honestly (blind count).
72. As an employee, I want to save the count many times while counting, so that a long count isn't lost.
73. As an owner, I want to see expected vs counted and the difference per line, valued at average cost, so that I can review before executing.
74. As an owner, I want expected recomputed at execution time, so that sales made during counting don't produce false differences.
75. As an owner, I want executing a count to change only the listed products, so that uncounted products keep their stock.
76. As an owner, I want shortages to show as a loss and surpluses as a gain in P&L, so that stock losses are visible.

### Drafts and the owner's inbox

77. As an owner, I want a home widget "Bajarishni kutayotgan qoralamalar" listing drafts of purchases, sales returns and counts with author and last change, so that I know what waits for me.
78. As an owner, I want count badges in the sidebar on those document lists, so that I notice pending work anywhere.
79. As a user, I want every document list to have Hammasi / Qoralama / Bajarilgan tabs, a date range, location, counterparty and author filters, and search by number or counterparty, so that I find documents fast.
80. As an owner, I want executed documents to show who created and who executed them, so that I can audit.
81. As a user, I want drafts to be editable and deletable and executed documents read-only, so that history can't change.
82. As a user, I want every document numbered per store and kind, so that I can refer to "Sotuv №123".
83. As a user, I want a document's date to be its execution moment, so that nobody backdates.

### Cash register and debts

84. As an owner, I want the Kassa page to show the balance per payment method and total, so that I know how much money I have.
85. As an employee, I want to see the list of cash operations without the balance strip, so that I can check what I recorded.
86. As a user, I want to filter cash operations by date, type, method, author and counterparty, so that I can find a payment.
87. As an employee, I want to record a customer debt payment from the customer card, the debts list or Kassa → + Kirim, so that I can take money wherever I am.
88. As an employee, I want a debt payment above the debt refused ("Summa qarzdan oshmasligi kerak"), so that overpayment can't happen.
89. As an owner, I want a customer's debt to be one balance, shown as paying off the oldest sale first, so that it's simple to explain to customers.
90. As a user, I want a customer debts list with balances, so that I know who owes what.
91. As an owner, I want a supplier debts list and to pay a supplier from it or from Kassa → + Chiqim, so that I settle what I owe.
92. As an owner, I want to write off part or all of a customer's debt as uncollectable, so that dead debts stop inflating balances and show as an expense.
93. As an employee, I want to record an expense with category, amount, method and note (note required for _Boshqa_), so that money leaving the register is explained.
94. As an employee, I want _Egasi olgan pul_, _Qarz foizi_ and _Umidsiz qarz_ hidden from my expense categories, so that I can't misuse owner-only categories.
95. As an owner, I want to record the money I take out as _Egasi olgan pul_, excluded from profit, so that withdrawals don't look like costs.
96. As a user, I want any cash-out above that method's balance refused, so that the register never goes negative.
97. As a user, I want cash operations to be permanent, corrected only by an opposite operation, so that the history is trustworthy.

### Loans

98. As an owner, I want to record a loan given or received with counterparty, principal, method and optional due date, so that loans are tracked separately from sales.
99. As an owner, I want the loans list to show principal, repaid, balance and due date with overdue in red, so that I chase late loans.
100.    As an owner, I want to record partial repayments in either direction, limited to the balance, so that loans close correctly.
101.    As an owner, I want to record interest paid or received from the loan card as _Qarz foizi_ / _Olingan foiz_, so that only interest reaches P&L.
102.    As an owner, I want loan principal never to appear in net profit, so that borrowing doesn't look like income.

### Reports

103. As a user, I want a stock report of current balances in blocks + pieces, filtered by location (a warehouse includes its sub-warehouses) and product, so that I know what is where.
104. As an owner, I want the stock report to show stock value at average cost, so that I know how much money sits on shelves.
105. As a user, I want a sales report for a date range with revenue, number of sales, by product, by employee and paid vs debt per sale, so that I see how sales go.
106. As an owner, I want the sales report to also show cost and gross profit, so that I see margins.
107. As an employee, I want the sales report without cost and profit columns, so that I see daily totals without seeing margins.
108. As an owner, I want a Foyda va zarar report for a date range: revenue − cost of goods sold = gross profit − each counted expense category − shortages + surpluses ± interest = net profit, so that I know what I earned.
109. As an owner, I want supplier payments, debt payments, loan principal and owner withdrawals excluded from P&L, so that profit isn't distorted by money movements.
110. As an owner, I want a Pul oqimi report of every cash operation in a period grouped by type, category and day, so that I see where money went.
111. As an owner, I want a Balans page with cash per method, loans outstanding (given/received) and customer and supplier debt totals, so that I see my position now.

### Home pages and shell

112. As an owner, I want my home page to show the opening-balance card, pending drafts, today's revenue / number of sales / gross profit / cash received, cash per method and debt totals, so that I see the day at a glance.
113. As an employee, I want my home page to show big buttons (Yangi sotuv, Yangi ko'chirish, Qarz to'lovi, Xarajat), my drafts and my sales today without cost, so that I start work in one click.
114. As a user, I want empty lists to show one sentence and the main button ("Hali sotuv yo'q" + Yangi sotuv), and filtered empty lists to offer "Filtrni tozalash", so that I know what to do.
115. As a user, I want money shown as "1 250 000 so'm" and quantities as "4 blok + 3 dona", so that numbers read naturally.
116. As a user, I want the whole interface and every error message in Uzbek Latin, so that I understand it.

## Implementation Decisions

### Tenancy, identity and permissions

- The starter kit's Team is the store (ADR-0003). Every business table carries `team_id`; all queries are scoped to the current user's store by a global scope or equivalent, so a query can never cross stores.
- Each non-super-admin user belongs to exactly one store with one role. Personal teams, team switching, create-team, invitations and the team-slug URL prefix are removed; the current store is resolved from the user's membership. The existing team member/invitation UI and its tests are replaced (deleting starter tests needs the user's approval at ticket time).
- Roles via spatie/laravel-permission in teams mode, team = store: `owner`, `employee`; `super-admin` is global (no team). Roles and permissions are seeded and not editable in the UI.
- Permissions are fine-grained names checked by policies and by the sidebar (U2). At minimum: execute per document kind (sale, transfer executed by both; purchase, inventory count, sales return executed by owner only), draft per document kind, supplier return (owner only), opening balance, view costs (average cost, purchase prices, stock value, cost/profit columns), view finance reports (P&L, cash flow, balance), view cash balance, loans, debt write-off, supplier payments, owner-only expense categories, manage employees, manage products, manage locations.
- Login uses phone + password. Phone is stored normalised as `+998XXXXXXXXX`, unique across the system; the UI applies the mask `+998 __ ___ __ __`. Email is removed from registration, login and profile; email verification and email password reset are removed. 2FA and passkeys stay optional.
- Sign-up creates the user, the store, the owner role, the warehouse _Asosiy ombor_ and nothing else (expense categories are global seeded rows, see below).
- Users have an active flag (owner can deactivate employees); stores have a blocked flag. Login and every request check both; a blocked store logs its users out with "Do'kon bloklangan".
- `app:create-super-admin` Artisan command creates a super-admin (name, phone, password). Super-admin routes form a separate area: store list, block/unblock, reset owner password. Policies deny super-admins every store business resource.

### Catalogue

- Product: name, optional barcode (unique per store when present), pieces per block (≥ 1), archived flag. No price fields (ADR-0004). Archived products cannot be added to new documents but stay in history and reports.
- Location: name, optional parent (one level only: a sub-warehouse's parent must be a warehouse). Archiving instead of deleting once used.
- Counterparty: name, optional phone, note. One list for customers, suppliers and loan parties; roles are implied by usage.
- Expense category: global seeded rows with a code, Uzbek name, direction (expense or income), "counts in net profit" flag and "employee may use" flag (ADR-0005): Transport xarajati, Ijara, Ish haqi, Kommunal, Soliq, Qarz foizi (employee: no), Boshqa (note required), Egasi olgan pul (not in profit, employee: no), Umidsiz qarz (system, used by write-offs only), Olingan foiz (income).

### Documents

- One `documents` table with a `kind` (sale, sales return, purchase, supplier return, transfer, inventory count, opening balance), `status` (draft, executed), per-store per-kind sequential `number`, location (or from/to locations for transfers), optional counterparty, optional source document (the sale or purchase a return comes from), note, author, executor, `executed_at`. One `document_lines` table: product, quantity in pieces, pieces-per-block snapshot (Q28), price per piece (nullable while draft), stored unit cost (set at execution for outgoing lines), counted and expected quantities (inventory count), source line (returns). Payments planned on a draft are stored per method on the document and only become cash operations at execution.
- The opening balance has extra sections (cash per method, customer debts, supplier debts, loans) stored as its own child rows; one per store; owner only; never in P&L.
- **Document execution module** — the deep module and main domain seam. One entry point "execute this document as this user" that, in one database transaction:
    1. checks permission, status = draft, and the opening-balance gate (D13);
    2. locks the affected stock balance rows, product cost rows and cash balance rows (pessimistic row locks, so concurrent executions serialise);
    3. validates per kind (stock sufficiency, prices present, returnable quantities, payments ≤ total, cash-out ≤ method balance, customer present when there is a debt part, from ≠ to);
    4. applies stock movements, average-cost updates, cash operations, debt entries;
    5. stamps `executed_at`, executor and status.
       Failures return a validation error per line or field, in Uzbek, and change nothing.
- **Stock ledger** (part of the execution module's internals, tested through it): current balance per (store, location, product) in pieces plus an append-only stock movement history linked to document lines. No negative balance is ever written.
- **Average cost** (ADR-0002): stored per (store, product), store-wide. Incoming at a known cost (purchase line price, opening balance cost, sales return at the sale line's stored cost) blends: new avg = (on-hand × avg + qty × cost) / (on-hand + qty), on-hand counted across all locations. Outgoing (sale, transfer, shortage) leaves average unchanged; sale lines store the average at execution as their unit cost. Surplus enters at current average (no change). Supplier return removes pieces at that purchase line's price: new avg = (on-hand × avg − qty × price) / (on-hand − qty); if on-hand becomes 0 the average stays as it was. Money is DECIMAL(18,2); average cost keeps more precision internally (decided at ticket time) and rounds for display.
- Previous price (Q18): the price on the store's most recent executed document line of the same kind for that product (sale → sale, purchase → purchase); empty if none.
- Transfers move pieces between locations with no money or cost effect. Reverse transfer pre-fills a swapped draft.
- Inventory count: lines only for listed products; at execution expected is recomputed; difference > 0 is surplus, < 0 shortage, both valued at current average cost and recorded on the line for P&L.
- Sales return: quantity per line ≤ sold − already returned (across executed returns of that sale); pieces back at the sale line's stored cost; refund at sale price; refund reduces that customer's debt first (up to the debt), the rest is a cash refund in the chosen method (default: the original sale's method), subject to the cash-out balance rule.
- Supplier return: quantity ≤ purchased − already returned; stock check; reduces supplier debt first, the rest is money back into the register.
- Executed documents are read-only (ADR-0006); no edit, delete, cancel, or backdating.

### Money

- Cash operation: store, type (sale payment, purchase payment, sales return refund, supplier return refund, customer debt payment, supplier payment, expense, income, loan given, loan received, repayment received, repayment made, opening balance), direction (in/out), payment method (cash, card, bank transfer), amount > 0, optional counterparty, expense category, loan, document, note, author, timestamp. Append-only (D14).
- Cash balance per (store, method) is kept as a row locked during any cash-out so the "cannot exceed balance" rule (D11) holds under concurrency.
- Debts: an append-only debt entry ledger per counterparty and side (customer / supplier): sale debt part, opening debt, payment, return reduction, write-off. Balance = sum. One balance per customer (D9); the per-sale "paid / debt" display allocates payments oldest-sale-first at read time.
- Customer debt payment and supplier payment: amount ≤ current debt (Q54); creates a cash operation and a debt entry together.
- Debt write-off (owner): reduces customer debt, records an _Umidsiz qarz_ P&L expense, no cash operation.
- Loan: counterparty, direction (given/received), principal, date, optional due date, note. Creating it creates its loan given/received cash operation. Repayments are cash operations linked to the loan, ≤ outstanding balance. Interest is an expense (_Qarz foizi_) or income (_Olingan foiz_) cash operation linked to the loan. Principal and repayments never enter P&L (ADR-0001).
- Accrual: a sale counts in revenue and cost of goods sold at execution, including the debt part.

### Reports (read models)

- Each report is a query module taking store + filters and returning plain data; pages render it. Cost-related fields are omitted server-side for users without "view costs" (not just hidden in the UI).
- Foyda va zarar: revenue (executed sales − executed sales returns at sale price), cost of goods sold (sale line stored cost − returned lines' stored cost), gross profit, each expense category that counts in net profit (including _Umidsiz qarz_), shortages, surpluses, interest paid / received, net profit.
- Pul oqimi, Balans, Sales report, Stock report as described in the user stories; the stock report rolls sub-warehouses into their warehouse.

### Frontend

- Desktop only (U1); minimum supported width a laptop screen. Built on the existing app shell, sidebar and UI components.
- Sidebar groups (from `ux-flows.md` §3): Bosh sahifa; Savdo (Sotuvlar, Qaytarishlar); Xarid (Xaridlar, Yetkazib beruvchiga qaytarishlar); Ombor (Qoldiqlar, Ko'chirishlar, Inventarizatsiyalar, Mahsulotlar, Omborlar); Pul (Kassa, Mijoz qarzlari, Yetkazib beruvchi qarzlari, Qarzlar); Kontragentlar; Hisobotlar (Sotuv hisoboti, Foyda va zarar, Pul oqimi, Balans); Sozlamalar (Xodimlar, Do'kon, Boshlang'ich qoldiq). Each item is shown from the shared permission list sent with every page.
- Sale screen: fast document form (D1) with keyboard shortcuts F2 (Saqlash) and F9 (Bajarish); its exact layout is settled by a `/prototype` before its ticket.
- Other documents use one shared document-form pattern: header, lines table with block/piece inputs, totals/payment panel, buttons Saqlash, Bajarish (only when permitted), O'chirish (drafts), Bekor qilish. Bajarish always confirms: "Bajarilgan hujjatni o'zgartirib yoki o'chirib bo'lmaydi. Davom etasizmi?". Status badges Qoralama (grey) / Bajarilgan (green).
- Shared list-page pattern for all documents with the columns, filters and empty states from `ux-flows.md` §3.
- All UI copy and validation messages in Uzbek Latin; the app locale is `uz`. Money formatted with space thousands separators and "so'm"; quantities as "N blok + M dona".

## Testing Decisions

- Good tests assert external behaviour: HTTP responses, Inertia page props, database state after an action, and report numbers. They never assert private methods, query shapes or component internals.
- **Seam 1 — HTTP feature tests (primary).** Act as owner, employee, super-admin or guest; call routes (create/update draft, Bajarish, cash operations, reports); assert status, redirects, validation errors, Inertia props (including that cost fields are absent for employees) and resulting rows. Covers: sign-up, phone login, blocked store, employee management, permission matrix (each forbidden action returns 403 for employees and for super-admins on store data), tenant isolation (a user of store A never sees or changes store B), every document flow end to end, cash and debt flows, report pages.
- **Seam 2 — domain tests on the document execution module and report query modules**, called directly without HTTP, using factories and the database. Covers the arithmetic and invariants: average-cost updates for every incoming/outgoing case including supplier return to zero stock, stored line cost, no negative stock, returnable quantities, debt-first refund split, payments ≤ total, cash-out ≤ method balance, opening-balance gate, P&L formula and exclusions, oldest-sale-first allocation, stock roll-up to warehouses. These live under the feature suite (they need the database) but do not go through HTTP.
- Concurrency (row locking) is covered by a domain test that proves the second of two executions against the same stock sees the first one's result and fails on insufficient stock; true parallel execution is not simulated.
- Artisan command test for `app:create-super-admin`.
- No browser tests in v1.
- Prior art: `tests/Feature/Auth/*` and `tests/Feature/Teams/*` (Pest, factories, `RefreshDatabase`, `assertDatabaseHas`, acting-as patterns). Follow the `testing-best-practices` skill.

## Out of Scope

- Mobile and tablet layouts.
- Cashier shifts / Z-reports, multiple cash registers, cash counting against expected.
- Fiscal receipts / online cash register (soliq onlayn kassa) integration; the receipt is a browser print.
- SMS, email notifications, email login.
- Stored sale prices, price lists, discounts as a separate concept (the line price is the price).
- Product groups, variants, minimum stock levels and low-stock alerts.
- Excel import/export (possible later add-on for the opening balance).
- Landed cost on purchases (transport is a plain expense).
- FIFO or per-batch costing; multi-currency; fractional quantities.
- Cancelling, un-executing or editing executed documents; backdating.
- Owner-editable roles, permissions or expense categories.
- Transfers between stores; one user in several stores.
- Super-admin access to store business data, billing/subscriptions.

## Further Notes

- **Assumption to confirm at ticket time:** the owner can deactivate (not delete) an employee (story 7); this was implied, not explicitly decided.
- **Assumption:** product barcodes are unique per store; counterparties are never deleted once used.
- Removing the starter kit's team features deletes existing starter tests; get the user's approval in that ticket (AGENTS.md: do not delete tests without approval).
- The sale screen ticket must be preceded by a `/prototype` session (2–3 variants on one route); its result is linked from that ticket.
- Brief requirements outside the code: an architecture document explaining why the system is built this way, a user guide in English, and the full prompt log `docs/prompts.md`. These are written as their own tickets near the end.
- Concurrency: every execution and every cash-out locks the rows it reads for its checks; SQLite serialises writes, but the code must also be correct on MySQL/PostgreSQL.
