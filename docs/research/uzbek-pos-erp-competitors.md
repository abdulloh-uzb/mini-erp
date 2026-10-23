# Competitor and accounting research: Uzbek POS/ERP for a beverage store

Researched 2026-10-23. Scope: BILLZ, BITO, MoySklad (primary), Optimo, Smartup, 1C, iiko, Poster (secondary), plus Uzbek accounting law (NSBU/BHMS, Tax Code).

**Source quality, briefly**

- **MoySklad** publishes a full official help center (support.moysklad.ru). Its articles are the strongest source in this document.
- **BILLZ** and **BITO** have no public end-user help center that I could find. Their **public developer API docs** (docs.billz.io, docs.bito.uz) show their data models. Behaviour I derive from API fields is marked **(inference)**.
- **Optimo** and **Smartup** have only marketing or pricing pages; topics not covered there are marked "not found in public docs".
- **1C** help (its.1c.ru) requires a paid subscription, so it is not used.
- **iiko** is not used: nothing public turned up.

---

## 1. Summary

| Open question                        | Recommendation                                                                                                                                                                                                                                                                                                                                                                  | Main evidence                                                                                                                                                                                                  |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **P&L treatment of loans**           | Loan principal (given, received, repaid) is **not** in P&L. It goes to the cash report and a loan-balance report. Only **interest** (and any write-off of a loan that will never be repaid) hits P&L. To answer the owner, show three numbers side by side: **Net profit**, **Cash on hand**, and **Loans outstanding** (given / received).                                     | NSBU 21 (5830/6810/6820 are balance-sheet accounts; 9610 interest), Tax Code art. 304 p.16, MoySklad P&L uses only flagged expense items, Poster's "Show in P&L" flag                                          |
| **Loan repayment model**             | Add a `Loan` record: counterparty, direction (given/received), principal, date, optional due date. Cash-register operations of four system types (loan given, loan received, repayment received, repayment paid) link to it. Balance = principal − repayments, and partial repayments are allowed. Interest is a separate cash operation with a P&L expense or income category. | NSBU 21 p.241, 291–292; BITO transactions carry `user_type` (supplier/customer/person/other); MoySklad "Взаиморасчеты"                                                                                         |
| **Expense categories**               | User-editable per tenant, seeded with defaults (Transport, Rent, Salary, Utilities, Taxes, Marketing, Other). Each has an **"include in P&L"** flag, default on. System categories (sale, purchase payment, customer/supplier debt payment, loan types, transfer between cash registers) are locked and never counted as P&L expenses.                                          | MoySklad "Статьи расходов" (`Учитывать в прибыли`, system items not archivable); Poster "Show in P&L"; BILLZ custom categories; BITO hierarchical `payment-type`                                               |
| **COGS method**                      | **Moving weighted average** (AVECO), recalculated when a purchase is executed. Store the unit cost on each sale line at execution. FIFO is also legal but needs lot tracking and re-costing when old documents change.                                                                                                                                                          | NSBU 4 p.62–63 (identified / AVECO / FIFO, one method per group per year); MoySklad uses FIFO; Poster uses weighted average                                                                                    |
| **Inventory count differences**      | Executing a count writes stock adjustments. **Shortage** = expense line "Inventory shortage" at current average cost. **Surplus** = income line "Inventory surplus" at current cost. Show both separately in P&L.                                                                                                                                                               | NSBU 4 p.32, 91, 92; NSBU 21 (5910 → 9430; surplus → 9390); MoySklad Инвентаризация → Списание/Оприходование; BILLZ auto write-off/auto import; Poster P&L includes inventory results                          |
| **Supplier debt (buying on credit)** | **Yes, support it, symmetric with customer debt.** An executed purchase creates a payable for the unpaid part. Payments are cash-out operations. Add a per-supplier balance report.                                                                                                                                                                                             | MoySklad: a receipt creates a supplier debt shown in Взаиморасчеты; BILLZ "взаиморасчеты с поставщиками"; BITO supplier transactions + reconciliation act (Recon act); Optimo "Payment history with suppliers" |
| **Transport expense** (sub-point)    | For the MVP, transport is a period expense in P&L. Strict NSBU 4 includes **inbound** delivery in inventory cost. Later you can add a "landed cost" field on purchase documents (MoySklad "Накладные расходы", BITO `extra_cost`).                                                                                                                                              | NSBU 4 p.15 (transport-procurement costs form inventory cost)                                                                                                                                                  |

**On your current decisions** (details in section 4)

- **Match common practice:** multi-tenancy, roles, one pieces-per-block factor, two-level warehouses, Draft → Executed, no negative stock, selling on debt.
- **Main divergence:** no default selling price on the product. Every competitor keeps a sale price (or price list) on the product.
- **Main risks:**
    - **Pricing:** what "previous price" means, and no price at all for a first sale.
    - **Case size changes:** a supplier changes the case size.
    - **Cancelling a purchase whose goods are already sold:** this breaks the no-negative-stock rule and average cost.

---

## 2. Per-topic findings

### 2.1 P&L: what goes in, what is excluded, cash flow vs P&L

**MoySklad**

- **Structure:** Revenue = shipments + retail sales − returns, recognised at shipment, not when money arrives. COGS is computed from purchase documents. Gross profit = Revenue − COGS. Operating profit = Gross − operating expenses. Net profit = Operating − "Налоги и сборы". [P&L article](https://support.moysklad.ru/hc/ru/отчеты/прибыли_и_убытки)
- **What counts as an expense:** expenses come from Исходящий платеж, Расходный ордер, Списание and Кассовый расход. Operating expenses are all of these **except** the items Закупка товаров, Перемещение and Возврат, and except items with "Учитывать в прибыли" switched off. Expenses are dated by accrual date, not payment date. [P&L article](https://support.moysklad.ru/hc/ru/отчеты/прибыли_и_убытки), [Финансы option](https://support.moysklad.ru/hc/ru/деньги/финансы_в_моемскладе)
- **Cash flow (ДДС) is a separate report.** It is built from all payments and cash orders, by day, by expense item or by account, and does not apply the profit flag. [ДДС](https://support.moysklad.ru/hc/ru/отчеты/движение_денежных_средств)
- **Loans:** not mentioned in the P&L or expense-item articles. **(Inference)** The intended setup is an expense or income item with "Учитывать в прибыли" off.

**BILLZ**

- The P&L is per shop. Marketing page: "в одном отчете узнать, какой магазин отработал в плюс, а какой в минус". [financial-management](https://billz.io/why-billz/financial-management)
- **API response:** `income` has `sale_price, discount, returned_price, net_revenue, categories[]`. `expense` has `import, write_off, margin_profit, categories[]`. `profit.total` is the bottom line. [get_v1_profit_and_lose](https://docs.billz.io/reference/api/operations/get_v1_profit_and_lose/)
- **(Inference)** Net profit = net revenue − cost − write-offs − custom expense categories ± custom income categories.
- Cash flow is a separate export. [post_v1_cashflow_excel](https://docs.billz.io/reference/api/operations/post_v1_cashflow_excel/)
- Loans: not found in public docs.

**BITO**

- Separate report endpoints:
    - `reports/profit-loss/get`: "P&L report (revenue, COGS, expenses, net profit)".
    - `reports/cashflow/get`: "in + out + net".
    - `reports/balance/*`: cash-register and account balances.
    - `reports/customer-debt/get`.
    - [Report finance](https://docs.bito.uz/api/report-finance.html)
- Its Telegram channel also lists "Balans; Pul oqimi; Foyda va zarar". [t.me/Bito_ERP](https://t.me/s/Bito_ERP?before=76)
- Loans: no loan entity. Money movements are `transaction/income|expense|transfer|exchange` with a `payment_type` (category). [Transaction](https://docs.bito.uz/api/transaction.html)

**Poster** (secondary, the clearest public P&L definition)

- **Formula:** Profit = Revenue − Food cost − Expenses − Taxes. P&L uses the transaction date, not the payment date. [How the P&L is generated](https://knowledge-base.joinposter.com/en/how-the-pl-is-generated)
- **"Show in P&L" toggle:** every transaction category has one. The "Supply" and "Cash management" categories are excluded by default to avoid double counting: a purchase payment is not an expense, because cost enters through COGS.
- **Inventory:** inventory-check results go into Cost.

**Optimo / Smartup:** P&L not found in public docs. Optimo's plan list shows no P&L. [optimo.uz/en/packages](https://optimo.uz/en/packages)

**Common pattern:**

- **P&L** = revenue − cost of goods sold − expense categories flagged for P&L. Purchase payments, transfers and loans are excluded.
- **Cash flow** = every cash movement, including loans and purchase payments.

### 2.2 Loans given and received, and repayments

- **MoySklad:**
    - **No dedicated loan document** found in public docs.
    - **Counterparty balances** come from goods movements and payments (Приемка, Отгрузка, payments, cash orders). A positive balance means you owe the counterparty; a negative one means they owe you. [Взаиморасчеты](https://support.moysklad.ru/hc/ru/отчеты/взаиморасчеты)
    - **Manual corrections** go through "Корректировка взаиморасчетов". [Корректировка](https://support.moysklad.ru/hc/ru/деньги/корректировка_взаиморасчетов_с_контрагентами_и_сотрудниками)
    - **(Inference)** A loan is recorded as an incoming or outgoing payment against a counterparty, with an item flagged "not in profit". The counterparty balance then tracks what is still owed.
- **BITO:**
    - Every `transaction` names a counterparty kind: `user_type` ∈ {supplier, employee, customer, person, other}. Records carry `balance_changes[]`. [Transaction](https://docs.bito.uz/api/transaction.html)
    - Customer balances can be queried (`balance/get-by-customer`). [Balance](https://docs.bito.uz/api/balance.html)
    - There is an "installment plan" report family, which covers customer instalments, not the store's own loans. [Report finance](https://docs.bito.uz/api/report-finance.html)
    - No loan entity found.
- **BILLZ:** loans not found in public docs. Finance has user-defined income and expense types and "транзакции". [financial-management](https://billz.io/why-billz/financial-management)
- **1C:УТ 11.5:** has a dedicated subsystem "Кредиты, депозиты, займы" with payment schedules and loan payments. The section exists, but its content needs an ITS subscription, so details were not verified. [its.1c.ru](https://its.1c.ru/db/ut115ltsdoc/bookmark/LoansAndDeposits/PaymentOnLoansAndDeposits)
- **Interest is an expense:**
    - NSBU 21 account 9610 "Расходы в виде процентов" (interest expense), 9530 (interest income), 6920 (accrued interest), 4830 (interest receivable). [NSBU 21](https://lex.uz/docs/417624)
    - Tax Code art. 310: interest on borrowings is deductible at the actual rate. [Tax Code](https://lex.uz/docs/4674902)

### 2.3 Expense categories

- **MoySklad** ([Статьи расходов](https://support.moysklad.ru/hc/ru/деньги/статьи_расходов)):
    - **Defaults:** Возврат, Списание, Закупка товаров, Налоги и сборы, Перемещение, Аренда, Зарплата, Маркетинг и реклама.
    - **Editing:** users can add their own items. Users can edit or delete their own items and Зарплата/Маркетинг/Аренда.
    - **Locked system items:** "Закупка товаров, Возврат, Перемещение, Налоги и сборы, Списание — архивировать нельзя".
    - **Profit flag:** "**Учитывать в прибыли**", on by default. Switched off, the item does not reduce profit.
    - **Taxes:** "Налоги и сборы всегда учитываются и считаются отдельно от операционных расходов".
- **BILLZ:**
    - **Custom income and expense types:** "создать разные типы доходов и расходов", with examples "аренда, зарплаты, бонусы, транспорт и налоги". [financial-management](https://billz.io/why-billz/financial-management)
    - **Cashier expenses:** cashiers record expenses and cash collections (инкассации) at the till.
    - The price list mentions "Создание категорий расходов и доходов". [prices](https://billz.io/prices)
- **BITO:**
    - Categories are `payment-type`: user-created, **hierarchical** (`parent_id`), with `transaction_type` ∈ {income, expense, transfer, exchange, …} and a default counterparty kind. [Payment type](https://docs.bito.uz/api/payment-type.html)
    - **Approval:** optional owner approval for expenses above a limit (`expense_approval_enabled`, `expense_approval_limit`). [Finance settings](https://docs.bito.uz/api/finance-settings.html)
- **Poster:**
    - **Categories:** user-defined, each with "Show in P&L".
    - **Placement:** an expense category can be placed under Cost or Expenses.
    - [How the P&L is generated](https://knowledge-base.joinposter.com/en/how-the-pl-is-generated)

**Pattern:** editable list + seeded defaults + locked system items + a per-category P&L flag.

### 2.4 Selling on debt (nasiya) and supplier debt

- **MoySklad:**
    - **"Продажа в долг (Казахстан, Узбекистан)":** choose a customer, payment method "В долг". "Товар списывается с остатков, сумма покупки вносится на счет покупателя как долг."
    - **Repayment:** "Погасить долг" with an amount, by cash, card or mixed. The customer balance then updates.
    - **Not fiscalised:** debt operations are not fiscalised.
    - [Продажа в долг](https://support.moysklad.ru/hc/ru/касса%20и%20розница/работа%20в%20кассе/продажа_в_долг_кз)
    - **Supplier debt:** "В результате приемки появится долг перед поставщиком" (see Взаиморасчеты). [Приемка](https://support.moysklad.ru/hc/ru/закупки/приемка_товаров)
- **BILLZ:**
    - **Debt record:** `amount`, `paid_amount`, `repayment_date`, `status` ∈ {fully_paid, unpaid, overdue, partial_paid}, linked to `order_id`, `customer` and `shop`, with repayment `transactions[]` (each with payment type and cash register). [get_v1_debt](https://docs.billz.io/reference/api/operations/get_v1_debt/)
    - There is also `debt_stats`.
    - **Pricing page:** "Возможность продажи товаров в долг", "Управление долгами клиентов", "Управление взаиморасчетами с поставщиками", "История оплат по поставщикам и заказам". [prices](https://billz.io/prices)
- **BITO:**
    - **Payment method:** payment kinds include `debt`. [Payment type](https://docs.bito.uz/api/payment-type.html)
    - **Approval:** setting `debt_approve_by_boss` (default false) requires the owner to approve sales on debt. [Sale settings](https://docs.bito.uz/api/sale-settings.html)
    - **Reports:** `customer-debt`, `customers-payment-overview`, `installment-plan`. [Report finance](https://docs.bito.uz/api/report-finance.html)
    - **Suppliers:** payments to suppliers are transactions with `supplier_id`. There is a reconciliation act resource. [API index](https://docs.bito.uz/api/)
- **Optimo:** "Payment history with suppliers". Customer debt is not found in public docs. [packages](https://optimo.uz/en/packages)

**Pattern:**

- **Debt record:** each debt is tied to the sale and carries amount, amount paid, optional due date and a status (unpaid/partial/paid/overdue).
- **Repayments:** recorded as cash-register operations.
- **Suppliers:** supplier debt is supported by all the bigger products.

### 2.5 Cost of goods sold method

- **MoySklad uses FIFO:** "Себестоимость считается по принципу FIFO". Cost = price in Приемка/Оприходование + overhead (накладные расходы). A product-card purchase-price change does not affect stock already on hand. [Себестоимость товара](https://support.moysklad.ru/hc/ru/товары%20и%20склады/остатки%20и%20себестоимость/себестоимость_товара)
- **BILLZ:** method not found in public docs.
    - **(Inference)** There is a single `supply_price` per product per shop, and stocktaking/write-offs are valued at "supply price", which points to a single current (average or last) cost per shop, not lots.
    - Users without the right see `supply_price` = 0. [data model](https://docs.billz.io/concepts/data-model/)
- **BITO:** method not found in public docs.
    - **(Inference)** Stock records carry `cost`, `initial_cost` and `income_id` (the receipt), so cost is tracked per receipt batch (FIFO or lots possible). [Product stock](https://docs.bito.uz/api/product-stock.html)
    - Purchase receipts can carry `additional_costs` with `is_affect_price` and a `distribution_method` (landed cost). [Income](https://docs.bito.uz/api/income.html)
- **Poster uses weighted average:** cost = stock value / stock quantity, updated on each supply. [How the cost is calculated](https://knowledge-base.joinposter.com/en/how-the-cost-is-calculated)
- **NSBU 4 allows** identified cost, AVECO or FIFO (p.62), one method per inventory group for the whole year (p.63). [NSBU 4 (uz)](https://lex.uz/docs/4890446)

### 2.6 Inventory count

- **MoySklad:**
    - **The count document alone does not move stock:** "Документ Инвентаризации не корректирует остатки товаров". You then create Списание (shortage) or Оприходование (surplus) from it. [Инвентаризация](https://support.moysklad.ru/hc/ru/товары%20и%20склады/операции/инвентаризация_товаров)
    - **P&L effect:** Списание is an expense item that reaches P&L. [P&L](https://support.moysklad.ru/hc/ru/отчеты/прибыли_и_убытки)
    - **Surplus cost:** Оприходование carries a price and becomes cost under FIFO. [Себестоимость](https://support.moysklad.ru/hc/ru/товары%20и%20склады/остатки%20и%20себестоимость/себестоимость_товара)
- **BILLZ:**
    - **Automatic adjustments:** stocktaking results produce "автоимпорт" (surplus) and "автосписание" (shortage), valued at supply and retail price. [stocktaking summary](https://docs.billz.io/reference/api/operations/get_v1_stocktaking_summary_table/)
    - **Write-off reason:** write-offs require a reason, and there is a service reason for inventory. [processes](https://docs.billz.io/concepts/processes/)
    - **P&L effect:** P&L has an `expense.write_off` line.
- **BITO:**
    - **Count document ("revision"):** states pending → in_progress → done/canceled, with totals `total_expected`, `total_counted`, `total_profit`, `total_loss`.
    - **Pre-filled counts:** an option pre-fills counted = current stock.
    - [Revision](https://docs.bito.uz/api/revision.html)
    - Setting `on_revision` ∈ {warning, error} controls selling during a count. [Sale settings](https://docs.bito.uz/api/sale-settings.html)
- **Poster:** inventory-check deficits and surpluses go into P&L Cost. [P&L](https://knowledge-base.joinposter.com/en/how-the-pl-is-generated)

### 2.7 Pieces vs blocks (packaging)

- **MoySklad:**
    - **Setup:** "Если товар продается в упаковках, нажмите +Упаковка, выберите единицы упаковки и укажите количество единиц товара в одной упаковке". Stock stays in the base unit. [Карточка товара (Узбекистан)](https://support.moysklad.ru/hc/ru/касса%20и%20розница/розница/создание_карточки_товара_узбекистан)
    - **In documents:** lines can be added by scanning a packaging barcode. [Документы](https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/интерфеис/документы)
    - **(From the UI wording)** several packagings per product are possible.
- **BITO:**
    - **Product fields:** `box_type_id` plus `box_item` ("number of items that exist in one box") and `box_item_barcode`. [Product](https://docs.bito.uz/api/product.html), [Box types](https://docs.bito.uz/api/box-types.html)
    - **Purchase lines:** carry `box_count` and `box_item`. [Income](https://docs.bito.uz/api/income.html)
    - This is the closest match to your design.
- **BILLZ:**
    - **Units:** a measurement-unit directory (piece, kg, m) with precision; `is_divisible`.
    - **Packaging:** no box conversion factor in public API docs. [measurement unit](https://docs.billz.io/reference/api/operations/get_v2_measurement_unit/), [glossary](https://docs.billz.io/concepts/glossary/)

### 2.8 Multi-warehouse and nested warehouses

- **MoySklad:**
    - **Nesting:** "Склад может быть отдельным или включать несколько других складов. Тогда основной склад — родительский, а склады в его составе — дочерние. Учет по каждому складу ведется раздельно." The parent is chosen via the "Группа" field. [Создание склада](https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/настроики%20мс/создание_и_редактирование_склада)
    - **Zones and cells:** up to 10 zones per warehouse, plus cells (адресное хранение). Stock can be reported "по ячейкам", and counts are done per cell. [Адресное хранение](https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/инструменты/адресное_хранение)
    - **Reports:** stock by product, by warehouse or by cell. [Отчет Остатки](https://support.moysklad.ru/hc/ru/товары%20и%20склады/остатки%20и%20себестоимость/отчет_остатки)
- **BITO:**
    - **Structure:** flat warehouses under an organisation, with `is-main`, `warehouse_type` and a responsible employee. No parent field. [Warehouse](https://docs.bito.uz/api/warehouse.html)
    - **Transfers:** internal and inter-organisation transfers.
- **BILLZ:** stock is held per shop (`shop_id`). Transfers go between shops. [data model](https://docs.billz.io/concepts/data-model/)

### 2.9 Default prices on document lines

- **MoySklad:**
    - **Purchase:** "Укажите закупочную цену — и она будет автоматически подставляться в документы закупки".
    - **Sale:** sale prices come from price types on the product card.
    - **Minimum price:** a minimum price triggers a warning.
    - [Работа с товарами](https://support.moysklad.ru/hc/ru/товары%20и%20склады/товары%20и%20услуги/работа_с_товарами_и_услугами)
    - **Per-customer prices:** changing the counterparty can re-price lines if the counterparty has its own price type. [Изменение цен](https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/интерфеис/изменение_цен_в_документах)
    - **Saving prices back:** a document can write its prices back to the product card ("Цена → Сохранить цены"). [Отгрузка](https://support.moysklad.ru/hc/ru/продажи/отгрузка_товаров)
    - **"Last price used":** not found in MoySklad docs.
- **BILLZ:**
    - **Per-shop prices:** `shop_prices` hold `retail_price`, `supply_price` and `promo_price` per shop. [data model](https://docs.billz.io/concepts/data-model/)
    - **Set on receipt:** receipts (import) set supply and sale prices (retail/wholesale/min/max). [glossary](https://docs.billz.io/concepts/glossary/)
- **BITO:**
    - **Price lists:** named price lists of type `sale` or `income`, with a default flag. [Price](https://docs.bito.uz/api/price.html)
    - **Below minimum price:** a policy controls selling below the minimum price (`discount_below_min_price_policy` ∈ strict/warning/override). [Finance settings](https://docs.bito.uz/api/finance-settings.html)

**Pattern:** every competitor keeps a stored sale price (or price list) on the product, and none documents "last price used" as the default.

### 2.10 Roles

- **MoySklad:**
    - **System roles:** Администратор and Пользователь, where Пользователь has configurable rights.
    - **Custom roles:** for example "Продавец", saved and reused.
    - **Notable right:** "Видеть себестоимость, цену закупки и прибыль товаров".
    - **Plans:** granular rights only on the Профессиональный/Корпоративный plans.
    - [Права и роли](https://support.moysklad.ru/hc/ru/управление%20аккаунтом/пользователи/разграничение_доступа_настроика_прав_роли)
    - **Closed period:** documents in a closed period can be edited only by an admin or with a special right. [Закрытие периода](https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/настроики%20мс/закрытие_периода_редактирования_документов)
- **BITO:** custom roles with a permission map, plus separate `web_access` (back office) and `mobile_access` (POS). [Role](https://docs.bito.uz/api/role.html)
- **BILLZ:**
    - **Roles:** "Создание ролей на основе должности сотрудников", flexible custom access, and blocking logins from unknown devices. [prices](https://billz.io/prices)
    - **Hidden cost:** cost is hidden without a right. [data model](https://docs.billz.io/concepts/data-model/)
- **Optimo:** "Sub-user management". [packages](https://optimo.uz/en/packages)

### 2.11 Document lifecycle and negative stock (bonus, relevant to your decisions)

- **MoySklad:**
    - **Drafts:** "Непроведенные документы — это черновики, которые не изменяют остатки". [Если остатки считаются неверно](https://support.moysklad.ru/hc/ru/товары%20и%20склады/остатки%20и%20себестоимость/если_остатки_считаются_неверно)
    - **Editing:** posted documents remain editable, limited only by a closed period.
    - **(Inference)** Negative stock is possible: the docs describe "продажа до закупки" yielding a zero or negative balance.
- **BITO:**
    - **Negative stock off by default:** `sell_product_in_negative` defaults to false.
    - **Sale states:** new / draft / in_progress / done / cancelled.
    - [Sale settings](https://docs.bito.uz/api/sale-settings.html)
- **BILLZ:**
    - **Warehouse documents:** "документ, который наполняется позициями и затем завершается". [processes](https://docs.billz.io/concepts/processes/)
    - **Deletion:** finished transfers cannot be deleted.
    - **Sales:** paid sales become receipts.

---

## 3. Uzbek accounting notes (small retailer)

These are statutory rules. A management mini-ERP does not have to replicate them, but its P&L should not contradict them.

1. **Loans are not income or expense, only interest is** (NSBU 21 chart of accounts, [lex.uz/docs/417624](https://lex.uz/docs/417624)).
    - **Bank loans received:** 6810 (bank). Receipt Dt cash / Kt 6810; repayment Dt 6810 / Kt cash (p.291).
    - **Non-bank loans received:** 6820 "Краткосрочные займы" (non-bank, ≤ 12 months). Receipt Dt cash / Kt 6820, repayment the reverse (p.292).
    - **Loans given:** 5830 "Краткосрочные займы выданные". "Предоставленные займы отражаются по дебету счета 5830 … Возврат займа отражается по дебету счета учета денежных средств и кредиту счета 5830" (p.241).
    - **Interest:** expense 9610, income 9530 (financial-activity accounts, closed to 9910 "Конечный финансовый результат").
    - **Tax Code art. 304 p.16:** funds received under credit or loan agreements, and funds received as their repayment, are not income. [Tax Code](https://lex.uz/docs/4674902)
    - **Tax Code art. 310:** interest on borrowings is deductible at the actual rate.
    - **Tax Code art. 317 p.35:** interest on overdue loans above the contract rate is non-deductible.
2. **Receivables and payables:** 4010 "Счета к получению от покупателей" (customer debt / nasiya), 6010 "Счета к оплате поставщикам" (supplier debt). Both are balance-sheet only. [NSBU 21](https://lex.uz/docs/417624)
3. **Period expenses:** 9410 selling, 9420 administrative, 9430 other operating. All are closed to 9910 at period end (p.407 for 9430). COGS: "Списание себестоимости реализованных товаров" Dt 9120 / Kt 2910–2990. **(Inference)** Transport for delivering goods to customers is a selling expense (9410).
4. **Inbound transport is part of inventory cost:** NSBU 4 p.15 lists "транспорт-тайёрлов харажатлари" (transport-procurement costs: loading and freight to the current location) as part of inventory cost. [NSBU 4 (uz)](https://lex.uz/docs/4890446). NSBU 4 is the current edition: order No. 24, reg. No. 3259 of 30.06.2020, [lex.uz/docs/4894931](https://lex.uz/docs/4894931).
5. **Inventory valuation** (NSBU 4 p.62–66):
    - **Methods:** identified unit cost, weighted average (AVECO, "periodic or after each receipt"), or FIFO.
    - **Consistency:** one method per inventory group during the reporting year (p.63).
6. **Inventory differences:**
    - **Surplus:** valued at current value on the date found (NSBU 4 p.32) and booked as other operating income (p.92). NSBU 21: "Излишки товаров … выявленные в результате инвентаризации" Dt 2910–2990 / Kt 9390.
    - **Shortage:** goes to 5910 first. If a culprit is found, it moves to 4730 (employee) or 4860 (claims). Otherwise it is expensed to 9430 "Прочие операционные расходы" (NSBU 21 p.245–246; NSBU 4 p.91).
    - **Tax:** losses above natural-loss norms are non-deductible (Tax Code art. 317 p.1).

---

## 4. Check of your decisions

| Decision                                                                | Verdict                                                                                                                                                                             | Competitors                                                                                                              | Risk / note                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Multi-tenant**: single DB, tenant scoping                             | **Matches.** All are SaaS.                                                                                                                                                          | BITO tokens carry account context ([OAuth](https://docs.bito.uz/guide/api.html)); BILLZ scopes data by company/shop      | Cross-tenant leaks. Use a global scope + a tenant id on every table, including pivot, ledger and report queries, with feature tests per model. Use spatie/laravel-permission "teams" for per-tenant roles.                                                                                                                                                                                                                                                                                                     |
| **Roles**: super-admin, owner, employees (spatie)                       | **Matches**, but competitors allow **custom roles** with granular rights                                                                                                            | MoySklad (Admin/User + custom), BITO (permission map, web vs POS), BILLZ (roles by position)                             | Add a separate permission "view cost price / profit", as in MoySklad and BILLZ. Employees who sell should not see purchase cost or P&L.                                                                                                                                                                                                                                                                                                                                                                        |
| **One pieces-per-block per product**; stock in pieces                   | **Matches** BITO (`box_item`) and MoySklad (упаковка = N units)                                                                                                                     | MoySklad allows several packagings per product                                                                           | The supplier changes the case size (24 → 12), or one product comes in two case sizes. Store `pieces_per_block` **on each document line** at entry, so old documents still show correctly if the product's value changes. If two case sizes are common, make them two products.                                                                                                                                                                                                                                 |
| **Two-level warehouse → sub-warehouse**; stock at either level; roll-up | **Matches** MoySklad (parent/child, each tracked separately)                                                                                                                        | BITO and BILLZ are flat (warehouse/shop)                                                                                 | Define roll-up as parent own stock + Σ children. Counts happen per location. Decide whether a sale may take from a parent that holds no stock of its own (it should not; pick a concrete location).                                                                                                                                                                                                                                                                                                            |
| **Draft → Executed; executed immutable; corrections by reversal**       | **Stricter than MoySklad** (which lets users edit or un-post documents, limited only by a closed period); **matches BITO/BILLZ** (done/cancelled, finished documents not deletable) |                                                                                                                          | Cancelling an executed **purchase** whose goods are already sold → negative stock and a broken average cost. **Block it** if current stock < purchased quantity. Cancelling a **sale** must restore stock at the cost snapshot stored on the line. A cancelled sale on debt must also reverse the debt (and fail, or warn, if repayments exist).                                                                                                                                                               |
| **Block execution if stock is insufficient**                            | **Matches BITO default** (`sell_product_in_negative=false`)                                                                                                                         | MoySklad and Poster tolerate negative stock                                                                              | Race conditions. Lock stock rows (`SELECT … FOR UPDATE`) inside the execution transaction. Backdated documents: decide whether stock checks use "now" or the document date (simplest: execute as of now and disallow backdating).                                                                                                                                                                                                                                                                              |
| **No default sale price; line pre-fills with the previous price**       | **Differs.** Every competitor keeps a price on the product (MoySklad price types + purchase price, BILLZ `retail_price` per shop, BITO price lists)                                 |                                                                                                                          | (1) **First sale of a product has no price.** Fall back to empty and require input. (2) **Define "previous"**: the last _executed_ document of the same kind (sale for sale lines, purchase for purchase lines), across all customers. Optionally per customer later. (3) **Mistakes repeat**: one typo becomes the next default. Show the current average cost next to the price and **warn when the sale price is below cost** (like MoySklad's minimum price and BITO's `discount_below_min_price_policy`). |
| **Selling on debt**                                                     | **Matches** (all three)                                                                                                                                                             | BILLZ debt: amount/paid/repayment_date/status; MoySklad "В долг" + "Погасить долг"; BITO `debt` payment + owner approval | Allow a partial payment at sale; the remainder becomes the customer's debt. Optional due date. Repayments go through the cash register. Add a debt report (per customer, aging/overdue). Consider an "owner must approve" option (BITO `debt_approve_by_boss`). A sale on debt needs a named customer.                                                                                                                                                                                                         |
| **Single currency UZS, decimals**                                       | **Matches** the simplest setup (BITO `multi_currency` is optional, default false)                                                                                                   | MoySklad and BITO support multi-currency                                                                                 | USD-priced purchases are common in Uzbekistan; this is out of scope, so accept it. Use `decimal(18,2)` (or integer tiyin) and never floats. Money totals should be rounded per line.                                                                                                                                                                                                                                                                                                                           |
| **UI in Uzbek only**                                                    | **Fine** for the target user                                                                                                                                                        | BILLZ and BITO ship ru/uz/en                                                                                             | Choose **Latin vs Cyrillic** script explicitly (lex.uz and BITO offer both). Keep all strings in Laravel lang files, so Russian can be added cheaply later.                                                                                                                                                                                                                                                                                                                                                    |

### Recommendations for the open questions (detail)

1. **P&L formula** (management view):
    ```
    Revenue (executed sales − returns)
    − COGS (Σ line qty × unit cost snapshot)
    = Gross profit
    − Operating expenses (cash-out ops whose category has include_in_pnl = true: transport, rent, salary, …)
    − Inventory shortages (+ Inventory surpluses as income)
    − Interest paid (+ interest received)
    + Other income categories flagged for P&L
    = Net profit
    ```
    - **Excluded:** loan principal and repayments, supplier payments, customer debt repayments, transfers between cash registers, owner deposits and withdrawals. The owner's withdrawal is not an expense.
    - **The owner's concern about loans:** add a "Money position" panel next to the P&L showing cash balance, customer debts owed to us, supplier debts we owe, loans given outstanding and loans received outstanding. This shows how loans affect the owner's position without distorting profit.
    - **Uncollectible loans:** if a loan given is written off as never coming back, record a "Bad debt / loan write-off" expense (P&L), as accounting requires.
2. **Loan model:**
    - **Record:** a `loans` table (tenant, counterparty name/phone, direction, principal, issued_at, due_at nullable, note, status).
    - **Movements:** cash operations of types `loan_given`, `loan_received`, `loan_repayment_in` and `loan_repayment_out`, each with a `loan_id`.
    - **Balances and reports:** outstanding = principal − Σ repayments. Report: loans by status.
    - **Interest:** a separate expense or income category (P&L).
3. **Expense categories:** tenant-editable, seeded defaults, `include_in_pnl` flag (default true), and `is_system` categories that cannot be deleted or renamed. Make Transport a seeded default because the owner named it.
4. **COGS: moving weighted average** per product (tenant-wide or per warehouse; tenant-wide is simpler and standard for AVECO).
    - **Purchase execution:** new average = (old qty × old avg + purchased qty × price) / new qty.
    - **Sales:** each sale line stores `unit_cost` at execution.
    - **Inventory surplus:** brought in at the current average.
    - **Purchase cancellation:** reverse at the purchased price, only allowed when stock suffices.
    - Document the choice as the accounting policy (NSBU 4 p.63 consistency).
5. **Inventory count:**
    - **Flow:** a Draft count document holds counted quantities, entered in pieces/blocks. Executing it writes per-product adjustment movements at the current average cost.
    - **P&L lines:** "Inventory shortage" (expense) and "Inventory surplus" (income), shown on separate lines rather than netted, so the owner sees theft or breakage.
    - **Selling during a count:** block sales of the counted warehouse while the count is open, or snapshot expected quantities when counting starts (BITO offers warning/error).
6. **Supplier debt: yes.** Model it the same way as customer debt:
    - **Payable:** an executed purchase has `paid_amount`, and the remainder becomes a payable to the supplier.
    - **Payments:** cash-out ops of type `supplier_payment`.
    - **Report:** per-supplier balance.
    - **Why:** it costs little once customer debt exists, and beverage distributors routinely deliver on consignment or deferred terms. Every major competitor supports it.

---

## 5. Sources

**MoySklad help center** (official)

- P&L: https://support.moysklad.ru/hc/ru/отчеты/прибыли_и_убытки
- Expense items: https://support.moysklad.ru/hc/ru/деньги/статьи_расходов
- Finance option: https://support.moysklad.ru/hc/ru/деньги/финансы_в_моемскладе
- Cash flow: https://support.moysklad.ru/hc/ru/отчеты/движение_денежных_средств
- Settlements: https://support.moysklad.ru/hc/ru/отчеты/взаиморасчеты
- Settlement correction: https://support.moysklad.ru/hc/ru/деньги/корректировка_взаиморасчетов_с_контрагентами_и_сотрудниками
- Sale on debt (KZ/UZ): https://support.moysklad.ru/hc/ru/касса%20и%20розница/работа%20в%20кассе/продажа_в_долг_кз
- Cash expenses at till: https://support.moysklad.ru/hc/ru/касса%20и%20розница/работа%20в%20кассе/учет_наличных_расходов_через_кассу
- Product cost (FIFO): https://support.moysklad.ru/hc/ru/товары%20и%20склады/остатки%20и%20себестоимость/себестоимость_товара
- Inventory: https://support.moysklad.ru/hc/ru/товары%20и%20склады/операции/инвентаризация_товаров
- Surplus (Оприходование): https://support.moysklad.ru/hc/ru/товары%20и%20склады/операции/оприходование_товаров
- Product card (UZ, packaging): https://support.moysklad.ru/hc/ru/касса%20и%20розница/розница/создание_карточки_товара_узбекистан
- Products & services: https://support.moysklad.ru/hc/ru/товары%20и%20склады/товары%20и%20услуги/работа_с_товарами_и_услугами
- Warehouses: https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/настроики%20мс/создание_и_редактирование_склада
- Address storage: https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/инструменты/адресное_хранение
- Stock report: https://support.moysklad.ru/hc/ru/товары%20и%20склады/остатки%20и%20себестоимость/отчет_остатки
- Wrong stock / drafts: https://support.moysklad.ru/hc/ru/товары%20и%20склады/остатки%20и%20себестоимость/если_остатки_считаются_неверно
- Documents: https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/интерфеис/документы
- Price changes in documents: https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/интерфеис/изменение_цен_в_документах
- Shipment: https://support.moysklad.ru/hc/ru/продажи/отгрузка_товаров
- Receipt: https://support.moysklad.ru/hc/ru/закупки/приемка_товаров
- Roles: https://support.moysklad.ru/hc/ru/управление%20аккаунтом/пользователи/разграничение_доступа_настроика_прав_роли
- Closed period: https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/настроики%20мс/закрытие_периода_редактирования_документов

**BILLZ**

- https://billz.io/why-billz/financial-management
- https://billz.io/prices
- https://docs.billz.io/concepts/data-model/
- https://docs.billz.io/concepts/glossary/
- https://docs.billz.io/concepts/processes/
- https://docs.billz.io/concepts/sale-lifecycle/
- https://docs.billz.io/reference/api/operations/get_v1_profit_and_lose/
- https://docs.billz.io/reference/api/operations/get_v1_debt/
- https://docs.billz.io/reference/api/operations/get_v1_stocktaking_summary_table/
- https://docs.billz.io/reference/api/operations/get_v2_measurement_unit/
- https://docs.billz.io/reference/api/operations/post_v1_cashflow_excel/

**BITO**

- https://bito.uz/
- https://t.me/s/Bito_ERP?before=76
- https://docs.bito.uz/api/
- https://docs.bito.uz/guide/api.html
- https://docs.bito.uz/api/report-finance.html
- https://docs.bito.uz/api/transaction.html
- https://docs.bito.uz/api/payment-type.html
- https://docs.bito.uz/api/finance-settings.html
- https://docs.bito.uz/api/sale-settings.html
- https://docs.bito.uz/api/balance.html
- https://docs.bito.uz/api/revision.html
- https://docs.bito.uz/api/product.html
- https://docs.bito.uz/api/box-types.html
- https://docs.bito.uz/api/income.html
- https://docs.bito.uz/api/product-stock.html
- https://docs.bito.uz/api/warehouse.html
- https://docs.bito.uz/api/price.html
- https://docs.bito.uz/api/role.html

**Optimo:** https://optimo.uz/en/packages

**Poster**

- https://knowledge-base.joinposter.com/en/how-the-pl-is-generated
- https://knowledge-base.joinposter.com/en/how-the-cost-is-calculated

**1C (section exists; content behind ITS login):** https://its.1c.ru/db/ut115ltsdoc/bookmark/LoansAndDeposits/PaymentOnLoansAndDeposits

**Uzbek law (lex.uz)**

- NSBU No. 21 Chart of accounts + instructions: https://lex.uz/docs/417624 (Russian: https://lex.uz/ru/docs/417624)
- NSBU No. 4 "Товарно-материальные запасы", 2020 edition: order https://lex.uz/docs/4894931, text (Uzbek) https://lex.uz/docs/4890446
- Tax Code of the Republic of Uzbekistan (arts. 304, 310, 317): https://lex.uz/docs/4674902

**Not found in public docs:**

- **Smartup:** any topic (smartup.uz/docs redirects to a 404).
- **iiko:** any topic.
- **BILLZ/BITO:** COGS method, loans, packaging (BILLZ only).
- **Optimo:** P&L, customer debt, expense categories.
