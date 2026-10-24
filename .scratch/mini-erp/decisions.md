# Grilling decisions (2026-10-23)

Every decision settled in the `/grill-with-docs` session, in question order. Terms follow `GLOSSARY.md`; hard-to-reverse ones are ADRs in `docs/adr/`. Research: `docs/research/uzbek-pos-erp-competitors.md`. Brief: https://telegra.ph/Texnik-topshiriq-Ichimliklar-dokoni-uchun-mini-ERP-10-20

## Stores, people, access

- **Q1/Q15 Tenancy**: multi-tenant at code level only (one DB, rows scoped by `team_id`). The starter kit's Team is the store (UI: _Do'kon_). ADR-0003.
- **Q2 Roles**: super-admin, owner, employee via spatie/laravel-permission (teams mode). Adding the package is approved.
- **Q29**: every user except super-admin belongs to exactly one store with one role. Remove personal teams, team switching, create-team and invitations.
- **Q16**: super-admin manages stores only (list, block/unblock, see owner); no access to store business data.
- **Q30 (b)**: self sign-up: a visitor registers and becomes owner of a new store.
- **Q42**: sign-up form: owner name, phone, password, store name; no email. New store gets warehouse _Asosiy ombor_ and the seeded expense categories.
- **Q31**: owner creates employees directly (name, phone, password). Login is phone + password for everyone; email login and invitations removed.
- **Q43**: owner resets employee passwords; super-admin resets owner passwords; no SMS. Remove email verification; keep 2FA and passkeys as optional.
- **Q44**: phone stored as `+998XXXXXXXXX`, unique system-wide, input mask `+998 __ ___ __ __`.
- **Q45**: blocked store: its users are logged out and cannot log in ("Do'kon bloklangan"); data kept; unblock restores.
- **Q46**: super-admins are created only by `php artisan app:create-super-admin`; they belong to no store.
- **Q17 + Q53 Permissions** (fixed roles, seeded; owner cannot edit in v1):
    - Owner: everything in their store.
    - Employee **executes**: sales, transfers, customer debt payments, expenses.
    - Employee **drafts only** (owner executes): purchases, inventory counts, sales returns. Supplier returns are owner only (D10 fixes the earlier Q17 wording to match Q51).
    - Employee **cannot**: see P&L, cash-flow and balance reports, purchase/cost prices, average cost, loans, write-offs, opening balances; manage employees.

## Products, units, locations

- **Q3**: each product has one fixed _pieces per block_; stock stored in pieces; blocks are display/entry only ("4 blok + 3 dona").
- **Q28**: each document line stores the pieces-per-block value at entry time.
- **Q40**: product fields: name, optional barcode, pieces per block, archived flag. No price fields (ADR-0004). No product groups in v1.
- **Q4**: two levels: warehouse → sub-warehouse; stock may sit at either; reports roll sub-warehouses up into their warehouse.
- **Q35**: every sale, purchase and inventory count has one location on the header.

## Documents

- **Q5 + Q41**: lifecycle draft → executed. Drafts editable/deletable. Executed documents are permanent: no edit, delete or cancel. Corrections: sale → sales return, purchase → supplier return, transfer → reverse transfer, count → new count. ADR-0006.
- **Q47**: no backdating; a document's date is its execution moment.
- **Q6**: executing is blocked if the location lacks the pieces (sales, transfers, supplier returns). No negative stock.
- **Q18 / Q8**: line price pre-fills with the _previous price_: last executed document of the same kind for that product in this store (purchase → last purchase price, sale → last sale price). Empty for a product never bought/sold; required.
- **Q27**: sale lines show average cost and warn when price is below it (only for users allowed to see costs).
- **Q36**: transfer document (from location → to location, lines in pieces/blocks), no money effect.
- **Q37**: inventory count corrects only the products listed; each line shows expected vs counted; unlisted products unchanged.
- **Q23**: on execute, count differences adjust stock at current average cost; shortages = P&L expense line, surpluses = P&L income line.
- **Q50 Sales return**: created from an executed sale; quantity ≤ sold − already returned; pieces come back at the sale line's stored cost; refund at sale price; reduces customer debt first, refunds cash only for the paid part. Employee drafts, owner executes.
- **Q51 Supplier return**: created from an executed purchase; quantity ≤ purchased − already returned; blocked if the location lacks the pieces; valued at that purchase line's price; average cost recalculated; reduces supplier debt first, then refund into the cash register. Owner only.
- **Q52 (A) Opening balance**: one document per store (owner only), with sections for stock per location with cost per piece, cash per payment method, customer debts, supplier debts, open loans. Never affects net profit. Excel import is a possible later add-on.

## Money

- **Q13 + Q21**: UZS only; money DECIMAL(18,2); quantities whole pieces.
- **Q7**: cost of goods sold = moving weighted average per product, store-wide; each sale line stores its cost at execution. ADR-0002.
- **Q9 + Q19 + Q20**: a sale can be paid fully, partly or not at all; unpaid part becomes customer debt; customer required only when not fully paid; paid part auto-creates a cash operation on execute. Debt payment is a cash operation against a customer; customer debts report.
- **Q24**: same for purchases: unpaid part becomes supplier debt; supplier payment operation; supplier debts report. Customers, suppliers and loan parties share one _counterparty_ list.
- **Q38**: superseded by Q41 (no cancelling).
- **Q54**: a debt payment cannot exceed what is owed (customer or supplier).
- **Q34**: every cash operation has a payment method: cash, card, bank transfer. One cash register per store with a balance per method plus total.
- **Q10**: loan principal never enters P&L; only interest. ADR-0001.
- **Q11**: a loan is its own record (counterparty, given/received, principal, date, optional due date); four fixed cash operations link to it: loan given, loan received, repayment received, repayment made. Partial repayments allowed; balance = principal − repayments.
- **Q12 + Q33**: expense categories are fixed and seeded, not editable in UI (ADR-0005): Transport xarajati, Ijara, Ish haqi, Kommunal, Soliq, Qarz foizi, Boshqa, Egasi olgan pul (not in profit), Umidsiz qarz (bad debt); income category Olingan foiz.
- **Q26**: transport is a plain expense; landed cost on purchases is a later feature.
- **Q48b**: accrual: a sale counts in P&L when executed, including the debt part. Owner can **write off** customer debt (no money moves; expense _Umidsiz qarz_).

## Reports (owner only unless noted)

- **Q48a**: three finance reports, each its own page:
    - **Foyda va zarar**: date range; revenue − COGS = gross profit − each counted expense category − shortages + surpluses ± interest = net profit.
    - **Pul oqimi**: every cash operation in a period grouped by type/category/day, including loans and debt payments.
    - **Balans**: current cash per payment method, loans outstanding (given/received), customer and supplier debt totals.
- **Q25**: excluded from P&L: supplier payments, debt payments, loan principal, owner withdrawal.
- **Q39 Sales report**: date range; totals (revenue, number of sales; cost and gross profit for cost-permitted users); by product (pieces/blocks, revenue, profit); by employee; paid vs debt per sale.
- **Q49 Stock report**: current balances only, blocks + pieces; filter by location (warehouse includes sub-warehouses) and product; stock value at average cost for cost-permitted users.

## Language and docs

- **Q14 + Q22 + Q32**: UI in Uzbek (Latin script) only. Code, DB, `GLOSSARY.md`, user guide, architecture doc and prompt log in English.
- Brief rule: every prompt is logged verbatim in `docs/prompts.md` (UserPromptSubmit hook).

## UX

- **U1**: desktop only (laptop/PC browser). No mobile or tablet layout in v1.
- **U2**: one shared sidebar menu; each item shows only if the user's permissions allow it (no separate owner/employee menus).
- **D1 (U3)**: sale screen is a _fast document_ form: autofocused search/barcode box, lines table in the centre (Mahsulot, Blok, Dona, Narx/dona, Summa; average cost + warning for cost-permitted users), right panel with location, customer, total, payment per method and _Qarzga qoladi_. **Saqlash** (F2) saves a draft and clears the screen (parked sale, listed under _Qoralamalar (N)_); **Bajarish** (F9) executes after confirmation. Other documents use a normal document form.
- **D1a (prototype, 2026-10-24)**: the layout is variant B of `prototype/sale-screen`: product tiles + scan box on the left, a receipt-style cart on the right with parked sales as tabs, steppers per line and the payment block at the bottom (one amount box per selected method, "Qolganini", "To'liq naqd"). It replaces D1's "lines table in the centre"; the rest of D1 stands. Details in issue 07.
- **D15**: one scan or Enter adds +1 dona (merging into an existing line); blocks are one Tab away.
- **D3**: owner sees pending drafts via a home widget _Bajarishni kutayotgan qoralamalar_, sidebar count badges on owner-executed kinds, and _Hammasi / Qoralama / Bajarilgan_ tabs plus an author filter on every document list.
- **D12**: employees may quick-create counterparties (inside a sale); products are owner only.
- **D13**: every Bajarish is blocked until the opening balance is executed or the owner clicks _Boshlang'ich qoldiq yo'q_.
- **D2**: no cashier shifts in v1; every cash operation records its author.
- **D5**: one debt payment form, opened from the customer card / debts list (customer pre-filled) and from Kassa → + Kirim.
- **D9**: one debt balance per customer; payments are shown as paying the oldest sale first.
- **D11**: a cash-out above the register balance for that payment method is blocked.
- **D14**: cash operations are permanent; a mistake is corrected with an opposite operation.
- **D4**: employees open the sales report for the whole store without cost and profit columns (resolves the open question).
- **D6**: blind count: employees do not see expected quantity or difference; the owner sees expected vs counted (recomputed) before executing.
- **D7**: employees see the Kassa operations list but not the balance strip.
- **D8**: employees can pick every expense category except _Egasi olgan pul_, _Qarz foizi_ and _Umidsiz qarz_.
- **D10**: supplier returns are owner only (draft and execute).
- Purchases drafted by employees have no prices; the owner fills prices before executing.
- Step-by-step flows, sidebar and home pages: `docs/research/ux-flows.md` sections 2–3 (recommended flows accepted as the baseline).

## Still open

- Sale screen UI: `/prototype` in a fresh session before its ticket.
