# Architecture

This document explains how the mini-ERP is built and why. It is for a developer, or a reviewer of the brief, who wants to understand the system before reading the code.

The brief: [Texnik topshiriq: Ichimliklar do'koni uchun mini-ERP](https://telegra.ph/Texnik-topshiriq-Ichimliklar-dokoni-uchun-mini-ERP-10-20).

Related reading:

- [`GLOSSARY.md`](../GLOSSARY.md): the domain language. Every term in this document (store, piece, block, location, document, draft, executed, cash operation, average cost, net profit…) is used as defined there.
- [`docs/adr/`](adr/): the hard-to-reverse decisions, one short record each. They are listed [at the end](#decision-records).
- [`docs/research/uzbek-pos-erp-competitors.md`](research/uzbek-pos-erp-competitors.md): how BILLZ, BITO, MoySklad, Poster and Uzbek accounting rules (NSBU, Tax Code) handle loans, costing, counts, expenses and supplier debt. Most money decisions below come from it.
- [`docs/research/ux-flows.md`](research/ux-flows.md): sale screens, drafts, debt in the pay step, and the sidebar and home pages adopted as the baseline for the UI.
- [`docs/user-guide.md`](user-guide.md): the system from the user's side.

## 1. The problem in one paragraph

A drinks shop in Uzbekistan sells by the piece and by the block, often on debt to small shops, and buys on debt from suppliers. The owner needs to know at any moment what stock sits where, who owes whom, how much money is in the register per payment method, and what they actually earned. Employees must sell quickly without seeing purchase prices or profit, and nobody should be able to quietly rewrite history. Every structural choice below serves one of three goals: **numbers the owner can trust**, **separation between stores and between roles**, and **speed at the counter**.

## 2. Stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13 on PHP 8.5, Fortify for authentication |
| Frontend | Inertia v3 + React + TypeScript, Tailwind, shadcn-style components from the starter kit |
| Routing glue | Wayfinder (typed route functions in `resources/js/routes` and `resources/js/actions`) |
| Roles and permissions | spatie/laravel-permission in teams mode |
| Database | SQLite by default; every lock and query is written to be correct on MySQL and PostgreSQL too |
| Tests | Pest feature tests, PHPStan, Pint |

The app started from the Laravel React starter kit and keeps its shell, sidebar, settings pages, 2FA and passkeys. What the kit did differently (personal teams, invitations, email login) was removed rather than worked around (ADR-0003).

## 3. Code map

```
app/
  Actions/
    Documents/   ExecuteDocument (the execution module), StockLedger, SaveDraft,
                 PreviousPrices, ReturnedQuantities, PendingDrafts
    Cash/        CashRegister, DebtLedger, LoanLedger
    Reports/     ProfitAndLoss, CashFlow, BalanceSheet, SalesReport, StockReport
    Stores/      CreateStore, CreateEmployee
  Concerns/      BelongsToStore (tenancy), shared validation rules
  Enums/         DocumentKind, DocumentStatus, CashOperationType, DebtEntryType,
                 PaymentMethod, StorePermission, StoreRole, …
  Http/          thin controllers, form requests, middleware
  Models/        Eloquent models + Scopes/StoreScope
  Support/       Money, Quantity, Phone, StoreTeamResolver
resources/js/
  pages/         one folder per screen (sales, purchases, returns, cash, reports, …)
  components/    document-form, document-list, list-page, cash-form, …
  lib/navigation.ts   the one permission-filtered sidebar
```

Controllers stay thin. They validate with a form request, call one action, and render an Inertia page. All business rules live in `app/Actions`, and the rules that keep the books correct sit behind a few deep modules: **ExecuteDocument**, **StockLedger**, **CashRegister**, **DebtLedger** and **LoanLedger**. Nothing else writes stock, cost, cash or debt rows.

## 4. Tenancy: the store is the starter kit's Team

**Decision.** Multi-tenancy is done in code: one database, and every business table carries `team_id`. The kit's `Team` model *is* the store. Each user except the super-admin belongs to exactly one store with one role. ([ADR-0003](adr/0003-team-is-the-store.md))

**How it is enforced.**

- Every business model uses the `BelongsToStore` trait (`app/Concerns/BelongsToStore.php`). The trait adds a global `StoreScope`, which filters every query to the signed-in user's `team_id`. A signed-in user with no store (the super-admin) matches nothing (`1 = 0`). The trait's `creating` hook also stamps `team_id` from the signed-in user, so a row can't be written into another store by mistake.
- The `store` middleware (`EnsureUserBelongsToStore`) returns 403 for every store business route when the user has no store, so the super-admin never reaches store data, even through a URL.
- The execution module bypasses global scopes when it locks rows, so it re-checks `user.team_id === document.team_id` itself and always passes `team_id` explicitly.
- `tests/Feature/StoreIsolationTest.php` proves a user of store A can neither see nor change store B.

**Why.** A database per store is expensive to run and migrate for small shops. Reusing the kit's Team gave us membership, a store name and a team-aware permission package for free. Removing team switching and invitations matches the domain: an employee never works for two stores, and the owner creates employees directly with a phone and password.

## 5. Identity and access

- **Login by phone.** Shop owners and employees often have no email, so login is phone + password. The phone is stored as `+998XXXXXXXXX`, is unique across the system, and is typed through the mask `+998 __ ___ __ __` (`app/Support/Phone.php`). Email verification and email password reset are removed. The owner resets employee passwords and the super-admin resets owner passwords. 2FA and passkeys stay optional.
- **Blocking.** A user has an *active* flag and a store has a *blocked* flag. `EnsureAccountIsActive` runs on every request and logs out a deactivated user, or any user of a blocked store, with the reason (`Do'kon bloklangan.`). Data is never deleted.
- **Roles.** `owner` and `employee` are per-store roles. spatie's teams mode resolves the team through `StoreTeamResolver`, which reads it from the user's single store. `super-admin` is global and is created only with `php artisan app:create-super-admin`, so nobody can sign up as one.
- **Permissions, not role checks.** `StorePermission` (`app/Enums/StorePermission.php`) lists fine-grained permissions such as `sale.execute`, `purchase.draft`, `costs.view` and `finance-reports.view`. `StoreRole::permissions()` gives the owner all of them and the employee a fixed subset. Roles are seeded and not editable in the UI. The same permission names are used in four places:
  1. route middleware (`can:…` in `routes/web.php`);
  2. policies and the execution module (`DocumentKind::executePermission()`);
  3. controllers, which **leave cost fields out of the page props** for users without `costs.view`, so hiding them is not left to the UI (average cost, purchase prices, stock value, gross profit);
  4. the sidebar (`resources/js/lib/navigation.ts`), which shows an item only when the permission list shared with every page contains it.

  **Why:** the menu and the server can't drift apart, a forbidden URL is refused even if someone types it, and adding a role later means only a new permission list.

What an employee can and cannot do (decisions Q17, Q53, D4, D6–D8, D10):

| Area | Employee |
| --- | --- |
| Sale, transfer | draft and execute |
| Purchase, inventory count, sales return | draft only; the owner executes |
| Supplier return, opening balance, loans, debt write-off, supplier payments | no access |
| Purchase prices, average cost, stock value, profit, finance reports, Kassa balance | hidden (server-side) |
| Expense categories | all except _Egasi olgan pul_, _Qarz foizi_, _Umidsiz qarz_ |
| Products, counterparties | view; quick-create counterparties inside a document |

## 6. Documents

**Decision.** Every movement of goods is a **document** with a kind: sale, sales return, purchase, supplier return, transfer, inventory count or opening balance. They share one `documents` table and one `document_lines` table. Each document is saved as a **draft** (no effect on anything, editable, deletable) and then **executed** (effects applied, permanent).

Why one table:

- every kind has the same lifecycle (draft → executed), the same per-store, per-kind numbering (`Sotuv №123`), author and executor, and the same list page;
- returns link to their source document and source line inside the same tables, which makes "sold − already returned" one query (`ReturnedQuantities`);
- the owner's inbox (`PendingDrafts`) is a single query over drafts of the owner-executed kinds.

Kind-specific data sits in nullable columns (destination location for transfers, refund method for returns, expected and counted quantities for counts). The opening balance keeps its extra sections in child tables (`document_payments`, `opening_debts`, `opening_loans`). Payments planned on a draft are stored per method and only turn into cash operations when the document is executed.

**Quantities.** Stock is held in whole **pieces**. **Blocks** are only for entry and display ("4 blok + 3 dona", `app/Support/Quantity.php`). Each line keeps a snapshot of the product's pieces per block, so history still reads correctly if the product is edited later.

**Executed documents are permanent** ([ADR-0006](adr/0006-executed-documents-are-permanent.md)). There is no edit, delete, cancel or un-execute. A wrong sale is fixed with a sales return, a wrong purchase with a supplier return, a wrong transfer with a reverse transfer (the **Teskari ko'chirish** button pre-fills one), and a wrong count with a new count. Why: un-executing a debt sale that was partly paid leaves the customer's balance ambiguous, and the owner trusts a history that only grows. For the same reason, **a document's date is the moment it is executed**, and nothing can be backdated. A late purchase can therefore never change the average cost, and with it the profit, of sales already made.

**Optional sale price** ([ADR-0007](adr/0007-optional-product-sale-price.md), superseding [ADR-0004](adr/0004-no-stored-sale-price.md)). A product may carry a sale price per piece. A new sale line pre-fills from it, else from the **previous price**, the price on the store's last executed document of the same kind (`PreviousPrices`); purchase lines use the previous price only. A product with neither gets an empty, required price. The risk of a wrong price spreading is covered by showing the average cost on sale lines, with the warning _Narx o'rtacha tannarxdan past_, to users allowed to see costs. The price stays optional because some prices move often or differ by customer.

## 7. The document execution module

`app/Actions/Documents/ExecuteDocument.php` is the system's main seam: the **only** way a document takes effect. One call, `handle(Document, User)`, does everything inside **one database transaction**:

1. **Lock the document row** and check it is still a draft and that the user holds the execute permission for its kind and belongs to its store.
2. **Lock the store row** and check the **opening-balance gate** (D13): no document can be executed until the opening balance has been executed or the owner chose _Boshlang'ich qoldiq yo'q_. Holding the store lock means "execute opening balance" and "skip opening balance" can't both succeed.
3. **Lock each product's cost row in id order**, then each stock balance row it touches. Taking locks in a fixed order means two executions that share products wait for each other instead of deadlocking.
4. **Validate per kind**: stock covers every outgoing line, every price is present, returned quantity ≤ returnable, payments ≤ total, a customer is present when part of a sale stays unpaid, from ≠ to for transfers, and no cash-out exceeds the method's balance.
5. **Apply the effects** through the ledgers: stock movements, average-cost updates, cash operations, debt entries and loans.
6. **Stamp** status, executor and `executed_at`.

Any failure throws a `ValidationException` keyed like the form (`lines.3.pieces`, `payments.cash`, `counterparty_id`) with an Uzbek message, such as _Joyda yetarli emas. Qoldiq: 2 blok + 4 dona._ The transaction rolls back, so **a document is either fully applied or not at all**.

What each kind does:

| Kind | Stock | Average cost | Money and debt |
| --- | --- | --- | --- |
| Opening balance | in, per line location | blends at the entered cost | opening cash per method; opening customer and supplier debts; open loans. Never in P&L |
| Purchase | in | blends at the purchase price | paid part out of the register per method; the rest becomes supplier debt |
| Sale | out (must be covered) | unchanged; the line stores the current average as `unit_cost` | paid part into the register; the rest becomes customer debt (customer required) |
| Sales return | in | blends at the sale line's stored cost | refund at the sale price: customer debt first, the rest paid out in the refund method |
| Supplier return | out (must be covered) | removes the pieces at the purchase price | supplier debt first, the rest comes back into the register |
| Transfer | out of one location, into another | unchanged | none |
| Inventory count | each listed product set to the counted quantity | unchanged | the difference, valued at average cost, is a shortage or surplus in P&L |

**Why one deep module.** The invariants (no negative stock, no negative register, returns within limits, profit computed from stable costs) cut across stock, cost, cash and debt. Keeping them behind one entry point means every route that executes a document, whether a sale screen, a purchase page or the opening balance, gets the same checks and the same locking. Tests can drive it directly (`tests/Feature/Documents/Execute*Test.php`) without going through HTTP.

**Concurrency.** Two employees selling the last block at the same moment is a real case. Each check reads a row that it has already locked with `lockForUpdate()`: the stock balance for stock, the cash balance for the register, the counterparty for debt payments, and the source document for returns. The second execution therefore waits and then sees the first one's result. SQLite serialises writes anyway, but the code doesn't depend on that.

## 8. Stock ledger and average cost

`StockLedger` keeps the current balance per (store, location, product) in `stock_balances`, plus an append-only `stock_movements` history linked to document lines. No negative balance is ever written. If a line asks for more than is there, it throws `InsufficientStock` with the quantity actually available.

**Average cost** ([ADR-0002](adr/0002-moving-average-cost.md)) is stored per (store, product) in `product_costs`. It is store-wide, across all locations, and kept to 6 decimal places, while money has 2.

- Incoming at a known cost (purchase, opening balance, sales return): `new avg = (on hand × avg + qty × cost) / (on hand + qty)`.
- Outgoing (sale, transfer, shortage): the average is unchanged. Each sale line stores the average at execution as its `unit_cost`, which is what P&L uses as cost of goods sold.
- Supplier return: `new avg = (on hand × avg − qty × price) / (on hand − qty)`. If on hand reaches 0, the average stays as it was.
- Surplus enters at the current average, so the average doesn't change.

**Why moving average and not FIFO.** NSBU 4 allows average, FIFO and identified cost. FIFO, which MoySklad uses, needs batch tracking and re-costing when older documents change. Moving average needs one row per product and is easy to explain to a shop owner ("what one bottle cost us, on average"). Storing the cost on each sale line freezes profit at the moment of sale, and the no-backdating rule keeps that frozen value correct.

## 9. Money: cash register, debts, loans

All money is `DECIMAL(18,2)` in the database and handled as `bcmath` strings in PHP, never floats. The currency is UZS only.

### Cash register

`CashRegister` (`app/Actions/Cash/CashRegister.php`) is the only way money enters or leaves a store.

- A **cash operation** has a fixed type (sale payment, purchase payment, refunds, customer debt payment, supplier payment, expense, income, loan given/received, repayment received/made, opening balance). The type fixes the direction, and every operation has a payment method: _Naqd_, _Karta_ or _Bank o'tkazmasi_.
- `cash_balances` keeps one row per (store, method). Each operation locks its method's row before moving it, and a **cash-out above that method's balance is refused** (D11), for example _Kassada naqd yetarli emas_. The register therefore can't go negative, even under concurrency.
- Operations are **append-only** (D14). A mistake is corrected with **Tuzatish**, which records the opposite operation once (`reverse()`). A reversal can't itself be reversed.

There are no cashier shifts or Z-reports (D2). Every operation records its author, which covers the "who took the money" question for a small shop without adding an opening and closing ritual.

### Debts

`DebtLedger` keeps an append-only `debt_entries` ledger per counterparty and side (customer or supplier). Positive entries (from a sale, a purchase or the opening balance) raise the debt. Negative entries (payment, return, write-off) lower it. **A balance is the sum of its entries**, so it can always be explained line by line.

- A customer has **one balance**, not one per sale (D9). The customer card shows which sales are still unpaid by allocating every payment to the **oldest sale first** at read time (`allocation()`), which is how shop owners explain debt to customers.
- A payment can't exceed the debt (Q54). The counterparty row is locked, so two payments at once can't overshoot.
- A **debt write-off** (owner only) lowers the customer's debt and records an _Umidsiz qarz_ (bad debt) expense. No money moves.
- Customers, suppliers and loan parties are one **counterparty** list, because one shop can buy from you, sell to you and lend to you.

**Accrual.** A sale counts in revenue and cost of goods sold when it is executed, including the part on debt. Collecting the debt later is a cash movement, not income.

### Loans

`LoanLedger` records a loan (counterparty, given or received, principal, optional due date). Opening a loan moves the principal through the register. Repayments in either direction are cash operations linked to the loan and limited to its outstanding balance. Interest is recorded from the loan card as an expense (_Qarz foizi_) or income (_Olingan foiz_).

**Loans stay out of net profit** ([ADR-0001](adr/0001-loans-stay-out-of-net-profit.md)). The brief asks for net profit "taking loans into account". Under NSBU 21 and Tax Code art. 304, borrowed money is not income and lent money is not an expense, and MoySklad and Poster build P&L the same way. So principal and repayments never enter P&L, only interest does. The loans still "count": **Foyda va zarar** shows _Kassada hozir_ and loans given and received next to net profit, and **Balans** shows them as part of the store's position.

### Expense categories

Categories are **fixed, global, seeded rows**, not editable per store ([ADR-0005](adr/0005-fixed-expense-categories.md)): Transport xarajati, Ijara, Ish haqi, Kommunal, Soliq, Qarz foizi, Boshqa (note required), Egasi olgan pul (owner withdrawal, **not** in profit), Umidsiz qarz (used by write-offs only) and the income category Olingan foiz. Each row carries flags for "counts in net profit" and "employee may use". Why: the product targets one kind of business, a fixed list keeps every store's P&L comparable, and the owner's own withdrawals can't be passed off as a cost.

## 10. Reports are read models

Each report is a query module in `app/Actions/Reports` that takes the store and filters and returns plain arrays. Pages only render them. Reports never write.

- **Qoldiqlar** (`StockReport`): current balances in blocks + pieces. A warehouse's figures include its sub-warehouses. Stock value at average cost is shown only to users with `costs.view`.
- **Sotuvlar hisoboti** (`SalesReport`): revenue net of returns, number of sales, by product, by employee, and paid vs debt per sale. Cost and gross profit are shown only to users with `costs.view`, so employees see the same report without margins (D4).
- **Foyda va zarar** (`ProfitAndLoss`): `revenue − cost of goods sold = gross profit − each counted expense category − shortages + surpluses ± interest = net profit`. Returns reverse both revenue (at sale price) and cost (at the stored line cost). Supplier payments, debt payments, loan principal, _Egasi olgan pul_ and the opening balance never appear.
- **Pul oqimi** (`CashFlow`): every cash operation in a period, totalled by type, by category and by day. Each day links to Kassa filtered to that day.
- **Balans** (`BalanceSheet`): cash per method now, loans outstanding (given and received), and customer and supplier debt totals.

Dates follow the store's timezone, `Asia/Tashkent`, so "today" and day boundaries in reports match the shop's working day.

## 11. Frontend

- **Uzbek UI, English code.** All screens and validation messages are in Uzbek Latin (`APP_LOCALE=uz`). Code, the database, the glossary and these documents are in English. Money is shown as `1 250 000 so'm` and quantities as `4 blok + 3 dona`.
- **Desktop only** (U1). The users work at a counter PC or laptop.
- **One sidebar** filtered by permissions (U2), not separate owner and employee menus. Owners see count badges on Xaridlar, Qaytarishlar and Inventarizatsiyalar while drafts wait for them (D3).
- **Two document UIs.** The **sale screen** is a fast document: the cursor starts in the search/barcode box, a scan or Enter adds +1 dona, there are product tiles and a receipt-style cart, parked sales appear as tabs, and F2 saves while F9 executes. Its layout was chosen from three throwaway prototypes (variant B, D1a). Every other document uses one shared `document-form`: a header, lines with block/piece inputs, totals, and **Saqlash / Bajarish / O'chirish / Bekor qilish**. **Bajarish** always confirms, because executing is permanent.
- **One list pattern** (`document-list`, `list-page`): _Hammasi / Qoralama / Bajarilgan_ tabs, date range, location, counterparty and author filters, search, empty states, and a _Filtrni tozalash_ button.

## 12. Testing

Tests assert behaviour from outside: HTTP status, Inertia props, rows in the database and report numbers. They don't test private methods or query shapes. There are two seams:

1. **HTTP feature tests** act as owner, employee, super-admin or guest. They cover sign-up, phone login, blocked stores, the permission matrix (`PermissionsTest`), store isolation, every document flow, cash and debt flows, and that cost fields are absent for employees.
2. **Domain tests** call the execution module, ledgers and report modules directly (`tests/Feature/Documents`, `tests/Feature/Cash`, `tests/Feature/Reports`). They cover the arithmetic: every average-cost case, no negative stock, returnable limits, debt-first refunds, payments ≤ total, cash-out ≤ balance, the opening-balance gate, the P&L formula and its exclusions, oldest-sale-first allocation, and stock roll-up. A concurrency test shows the second of two executions against the same stock sees the first one's effect.

Run them with `php artisan test --compact`.

## 13. What was left out, on purpose

Mobile layouts. Cashier shifts. Fiscal receipts (the receipt is a browser print). SMS and email. Stored prices, price lists and discounts. Product groups and low-stock alerts. Excel import. Landed cost on purchases (transport is a plain expense). FIFO. Multi-currency. Fractional quantities. Editing or cancelling executed documents. Owner-editable roles or categories. Transfers between stores. One user in several stores. Billing. Each of these can be added later without changing the core: documents, ledgers and reports.

## Decision records

| ADR | Decision |
| --- | --- |
| [0001](adr/0001-loans-stay-out-of-net-profit.md) | Loan principal stays out of net profit; only interest enters it |
| [0002](adr/0002-moving-average-cost.md) | Cost of goods sold uses a moving weighted average, stored on each sale line |
| [0003](adr/0003-team-is-the-store.md) | The starter kit's Team is the store; code-level multi-tenancy |
| [0004](adr/0004-no-stored-sale-price.md) | Products have no stored price; lines pre-fill from the previous price |
| [0005](adr/0005-fixed-expense-categories.md) | Expense categories are fixed per release, not user-editable |
| [0006](adr/0006-executed-documents-are-permanent.md) | Executed documents are permanent; corrections are opposite documents; no backdating |
