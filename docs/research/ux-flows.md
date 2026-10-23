# UX flows: screens and step-by-step user flows

Research date: 2026-10-23. Fixed constraints come from `.scratch/mini-erp/decisions.md` and `GLOSSARY.md`; terms follow the glossary. Every competitor claim has a URL. **Inference:** marks my own conclusions. The accounting and data-model research is in `uzbek-pos-erp-competitors.md` and is not repeated here.

Source caveats:

- Some Lightspeed X-Series pages return HTTP 403 to fetchers. Those claims come from search-result excerpts of the official pages, cited by URL.
- BILLZ and BITO publish API docs, not user guides. Their UI behaviour is **inferred** from the API shapes.
- I found no public Smartup documentation. iiko is restaurant-only, so I skipped it.

## 1. Summary

| System                  | Sale screen style                                                                                                                                                | Draft / held sale                                                                                                                            | Debt in the pay step                                                                                                                               | Cashier shifts                                                                                                                   |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| **MoySklad**            | Two separate tools. Web _Отгрузка_ is a document form (header + lines + Сохранить) ([1]). _Касса МойСклад_ is a POS app (list/search/scanner, then Итого) ([2]). | Web: a document without the _Проведено_ flag is a draft, excluded from reports ([3]). Касса has _Отложенные чеки_ (article title only, [4]). | Касса: pick **Покупатель**, then the payment method **В долг**. Repayment through Покупатель → ⋯ → **Погасить долг**, amount can be partial ([5]). | Касса: open/close a shift. It opens automatically on the first sale, with cash in/out operations ([6]). Web documents: none.     |
| **Odoo**                | POS screen (products → cart → Payment → Validate) ([7]). Sales/Inventory apps use document forms with a **Validate** button ([8]).                               | POS: "New Order" per customer ([7]).                                                                                                         | Payment method **Customer Account** with _Identify Customer_ (customer required). Settle via Customer → ☰ → **Settle orders** ([9]).              | Mandatory POS session: opening cash control, then a closing screen with expected vs counted per payment method ([7]).            |
| **Loyverse**            | POS app with a back office.                                                                                                                                      | **Open tickets**: tap _Save_ instead of paying, reopen from _Open tickets_ ([10]).                                                           | No customer-credit feature found in the help centre.                                                                                               | Optional shifts: opening cash, Pay In/Pay Out, actual cash at close, Z-report. Expected cash can be hidden from cashiers ([11]). |
| **Square for Retail**   | POS checkout (search/scan/grid → Checkout → Charge) ([12]).                                                                                                      | **Save Cart**: saved carts work as open tickets, purged after 180 days ([12]).                                                               | Not in checkout.                                                                                                                                   | Optional cash-drawer sessions with start cash, paid in/out and an end count ([13]).                                              |
| **Lightspeed X-Series** | Sell screen (POS).                                                                                                                                               | **Park sale**, retrieved from the Sell screen ([14]).                                                                                        | Payment types **On account** / **Layaway**. On-account sales count in reports at creation, and stock is deducted ([15]).                           | Open/close the register, recommended daily. Expected = float ± cash movements, with shortfall/overpayment recorded ([16]).       |
| **BILLZ**               | POS (API: `POST /v2/order`, lines added one by one) ([17]).                                                                                                      | Order **черновик** (draft), plus a separate **отложена** state, "приостановлен, но не потерян" (paused, not lost) ([17]).                    | Payment body is an _array of payments_, so several methods can be mixed ([17]). A debt record has amount, paid amount and a status ([18]).         | Optional: "если в магазине используются кассовые смены" (if the store uses cash shifts), payment needs an open shift ([17]).     |
| **BITO**                | POS (inference from API).                                                                                                                                        | `open-ticket` = "a held order / table bill with its draft receipt" ([19]).                                                                   | Payment kinds include `debt` ([20]). `debt_approve_by_boss` setting ([21]).                                                                        | `cashbox-session` open/close with `expected` and `actual` per payment method and a `has_diff` flag ([22]).                       |
| **Poster**              | POS register. Supplies are a back-office form ([23]).                                                                                                            | Open orders (restaurant model).                                                                                                              | Split the total across methods ([24]). No credit feature found.                                                                                    | Shift starts with a cash balance. Variance becomes an automatic adjustment transaction ([25]).                                   |

Takeaways:

- Retail systems use a **fast checkout screen**. Systems with wholesale/B2B also keep a **document form** (MoySklad Отгрузка, Odoo Sales). When both exist they are separate tools that write to the same stock and reports ([1], [2], [7]).
- Every POS has some form of **hold/park**, so a held sale is common practice.
- Debt is **a payment method chosen in the pay step** and needs a selected customer (MoySklad, Odoo, Lightspeed, BITO).
- Shifts are near-universal in POS apps but **absent from back-office document flows**. Most make them optional (Loyverse, Square, BILLZ).

## 2. Flows

General UI rules (inference, applied to every document below):

**Buttons and statuses**

- Document form buttons: **Saqlash** (save draft), **Bajarish** (execute), **O'chirish** (delete draft) and **Bekor qilish** (leave without saving).
- An employee sees **Bajarish** only on the kinds they may execute. On other kinds they see only **Saqlash**.
- Status badges: _Qoralama_ (grey) and _Bajarilgan_ (green).

**Execute confirmation and lock**

- **Bajarish** always opens a confirmation dialog: "Bajarilgan hujjatni o'zgartirib yoki o'chirib bo'lmaydi. Davom etasizmi?" (An executed document cannot be changed or deleted. Continue?)
- An executed document opens read-only, with action buttons for its correction document. Example: a sale shows **Qaytarish yaratish**.

**Quantity entry**

- Every line has **Blok** and **Dona** inputs plus a computed _Jami dona_.
- The block size is shown beside the product name ("1 blok = 12 dona").

**Product search**

- One search box finds a product by name or barcode.
- A barcode scanner works as keyboard input, so the box keeps focus after each add.
- Scanning the same product again adds +1 dona to its existing line.

**Errors**

- Validation errors appear inline on the line. For example, "Omborda yetarli emas: bor 3 blok + 2 dona" (not enough in stock: 3 blocks + 2 pieces available).

### 2.1 Sale (sotuv)

Competitors:

- **POS checkout:** Square adds items by search, scan or grid, then Checkout → Charge ([12]). MoySklad Касса adds by list, search or scanner, then **Итого**, with cash, card or **mixed** payment and automatic change ("Приложение автоматически посчитает сдачу", the app calculates change automatically) ([2]).
- **Document form:** MoySklad Отгрузка has header fields (counterparty, warehouse), lines added by search or **Добавить из справочника** (add from catalogue), **Сохранить** (save), and payment created from the document via _Создать документ → Входящий платеж_ (create document → incoming payment) ([1]).
- **Debt in the pay step:** MoySklad selects **Покупатель** (customer), then the payment method **В долг** (on debt) ([5]). Odoo requires a customer for the Customer Account method ([9]). Lightspeed On-account sales count in reports at creation ([15]).
- **Held sales:** Loyverse **Save** with a name and comment ([10]); Square **Save Cart** ([12]); Lightspeed **Park sale** ([14]); BILLZ draft vs postponed ([17]).

**Recommendation: one "fast document" screen** (inference)

- It is a document form tuned for keyboard checkout, not a tile-grid POS.
- Why it fits:
    - Prices are not stored and must be typed or confirmed per line, which a tile-grid POS assumes away.
    - Beverage stores sell by the block to small shops as well as by the piece.
    - A sale has a lifecycle (draft → executed) and later sales returns.
    - The screen is desktop-only.
- Layout:
    - **Top:** search/barcode box with autofocus.
    - **Centre:** lines table (Mahsulot, Blok, Dona, Narx/dona, Summa; plus _O'rtacha tannarx_ and a warning for cost-permitted users).
    - **Right panel:** location, customer, total, payment amounts per method and remaining debt.

Recommended flow, **Savdo → Sotuvlar → Yangi sotuv**. The employee and the owner do every step.

1. The screen opens a new unsaved sale. **Joy** (location) is pre-filled with the user's last-used location; first time: _Asosiy ombor_.
2. Scan or type in the search box, then press Enter to add the line with _Dona_ = 1 or _Blok_ = 1. Which one is a UX option; recommend Dona = 1.
3. **Narx** (price per piece) pre-fills from the _previous price_.
    - If the product has never been sold, the field is empty and red. "Narx kiritilishi shart" (price is required).
    - Owner only: if the price is below average cost, show the yellow warning "Narx o'rtacha tannarxdan past" (price below average cost). It does not block.
4. Edit Blok/Dona. Show the stock live for this location: "Qoldiq: 10 blok + 4 dona".
5. Payment panel, labelled **To'lov**:
    - **Fields:** Naqd, Karta and Bank o'tkazmasi, plus a **To'liq naqd** button that fills cash = total.
    - **Remaining:** _Qarzga qoladi_ = total − paid, shown live.
    - **Change:** an optional "Olingan naqd → Qaytim" helper that computes change and is not stored.
6. **Mijoz** (customer) field: search counterparties, or **+ Yangi mijoz** (name, phone) in a modal.
    - It becomes required as soon as _Qarzga qoladi_ > 0.
    - Error: "Qarzga sotish uchun mijozni tanlang" (select a customer to sell on debt).
    - When a customer is chosen, show their current debt: "Joriy qarzi: 1 250 000 so'm".
7. **Saqlash** (F2): saves a draft and clears the screen for the next customer. This is the "park / held sale" mechanism.
    - Drafts appear in a **Qoralamalar (N)** dropdown on the sale screen and in the Sotuvlar list.
8. **Bajarish** (F9 / Ctrl+Enter): confirmation dialog, then execute.
    - Possible errors: insufficient stock per line; payments > total ("To'lov summasi jamidan oshib ketdi", payment exceeds the total); missing price; missing customer when there is a debt part.
    - On success: a toast "Sotuv №123 bajarildi", the receipt view with a **Chop etish** (print) button, and the cursor back on a fresh sale.

### 2.2 Purchase (xarid)

Competitors:

- **MoySklad Приемка:** a receipt creates supplier debt ([26]).
- **Poster supply:** supplier, storage, quantity/price lines, **+Add more**, then **Add payment** (account, sum), then **Save** ([23]).
- **Lightspeed:** receive a full or partial PO. Once received it is closed, and a correction is a stock return plus a new PO ([27]). Lightspeed R-Series splits the permissions "Purchase Orders" and "Add Received to Inventory" ([28]), which matches our "employee drafts, owner executes".

Important constraint (inference): employees **cannot see purchase prices**. An employee's purchase draft therefore has a hidden price column. The employee records what physically arrived; the owner fills or confirms prices and payment, then executes.

Recommended flow, **Xarid → Xaridlar → Yangi xarid**:

1. _(employee or owner)_ Header: **Yetkazib beruvchi** (supplier; required; + Yangi), **Joy** (location), optional **Izoh** (supplier invoice number, note).
2. _(employee or owner)_ Add lines by search or scan, entering Blok + Dona. The owner also sees **Narx/dona** pre-filled with the last purchase price, plus _Summa_.
3. _(employee)_ **Saqlash**. The draft shows "Egasi bajarishini kutmoqda" (waiting for the owner to execute) and the owner's pending counter increases (see 2.12).
4. _(owner)_ Opens the draft from the home widget or the list filter.
    - Checks quantities and fills prices. Error if any is empty: "Barcha narxlarni kiriting" (enter all prices).
    - Enters payment: per-method amounts; _Qarzga qoladi_ = supplier debt; shows the supplier's current debt.
5. _(owner)_ **Bajarish**. Stock increases and average cost is recalculated. The paid part creates cash-out operations; the rest becomes supplier debt.
    - Error if the cash register lacks the money for a method: "Kassada naqd yetarli emas" (not enough cash in the register). Recommend this hard rule; see open decision D11.

### 2.3 Sales return (qaytarish), created from an executed sale

Competitors:

- **Odoo:** **Return** on the validated delivery opens a wizard. Quantities default to the delivered amounts and can be lowered, and the trash icon drops a line ([29]).
- **Loyverse:** Receipts → **Refund** opens a refund screen; tap items and set a quantity with +/− ([30]).
- **Square:** **Issue refund** offers all items, specific items or an amount, with an optional restock ([31]).

Recommended flow:

1. _(employee or owner)_ Open the executed sale and click **Qaytarish yaratish**. Alternatively, Qaytarishlar → **Yangi qaytarish**, then pick the sale by number or customer.
2. A new draft opens with every sale line at quantity 0.
    - Each line shows _Sotilgan_ (sold), _Qaytarilgan_ (already returned) and _Qaytarish mumkin_ (can return).
    - The user types Blok/Dona per line. Price is read-only, the sale price.
    - Default location is the sale's location, and it is changeable.
    - Error on a line: "Ko'pi bilan 2 blok + 0 dona qaytarish mumkin" (at most 2 blocks + 0 pieces can be returned).
3. The summary panel shows the refund split, computed: first _Qarzdan kamayadi_ (reduces the customer debt), then _Naqd qaytariladi_ (cash refunded), per decision Q50. The refund payment method is chosen if a cash part exists. Recommend defaulting to the method used on the original sale.
4. _(employee)_ **Saqlash**, then the draft is pending for the owner.
5. _(owner)_ **Bajarish**. Stock comes back at the stored line cost, debt is reduced, and the cash refund is recorded.

### 2.4 Supplier return (yetkazib beruvchiga qaytarish)

Competitors: Lightspeed corrects received POs with a "stock return" ([27]). MoySklad lists _Возврат_ (return) among the documents created from a document ([1]).

**Conflict in decisions:**

- Q17 says employees _draft_ supplier returns.
- Q51 says "Owner only".
- Employees cannot see purchase prices, which value this document.
- Recommend Q51: owner only, so employees do not see the menu item. See D10.

Recommended flow (owner):

1. Open the executed purchase and click **Qaytarish yaratish**.
2. Lines show _Xarid qilingan_ (purchased), _Qaytarilgan_ (already returned), _Qaytarish mumkin_ (can return) and the purchase price (read-only). Enter Blok/Dona.
3. Panel: _Qarzdan kamayadi_ (reduces supplier debt) first, then _Kassaga qaytadi_ (back into the cash register), with a method.
4. **Bajarish**.
    - Error if stock is short: "Joyda yetarli emas".
    - Error if quantity > returnable.

### 2.5 Transfer (ko'chirish)

Competitors:

- **BILLZ:** create → add → send → accept. Completion needs access to the destination store ([32]).
- **Odoo:** an internal transfer is validated with **Validate** ([8]).
- A two-step send/receive model is for transfers between stores. Ours are within one store, so one step is enough (inference).

Recommended flow (employee and owner):

1. **Ombor → Ko'chirishlar → Yangi ko'chirish**. Fields: **Qayerdan** and **Qayerga**. Error if they are equal: "Bir xil joy tanlangan".
2. Add lines (Blok/Dona), with the stock of the _Qayerdan_ location shown per line.
3. **Saqlash** or **Bajarish**.
    - Error on short stock.
    - The executed view has **Teskari ko'chirish** (reverse transfer), which pre-fills a swapped draft.

### 2.6 Inventory count (inventarizatsiya)

Competitors:

- **Columns:**
    - Odoo: _On Hand_, _Counted_ and _Difference_ (green/red); **Apply** with a confirmation ([33]).
    - MoySklad: _Расчетный остаток_ (calculated), _Фактический остаток_ (actual) and _Разница_ (difference) plus a money surplus/shortage. Only added positions are counted. Scanning adds one unit per scan ([34]).
- **Uncounted items:** Square full counts set uncounted items to zero, while cycle counts leave them unchanged ([35]). Our Q37 equals the "cycle/partial" model.
- **Who counts and who completes:**
    - Square: counters **Submit progress**, then a reviewer approves ([35]).
    - Poster: staff count on the POS, completion happens in the management console, and the count stays editable until then ([36]).
    - Lightspeed R-Series: separate permissions "Inventory - Counts" and "Counts - Reconcile" ([28]).
- **Blind counting:** No source documents an explicit blind mode. Lightspeed reports discrepancies only at reconciliation ([37]), while Loyverse shows _Expected Stock_ while counting ([38]).

Recommended flow:

1. _(employee or owner)_ **Ombor → Inventarizatsiyalar → Yangi**. Pick **Joy**.
2. Add products by scan (+1 dona per scan, merging into a line), by search, or **Joydagi barcha mahsulotlarni qo'shish** (add all products with stock here). Enter _Sanalgan_ (counted) as Blok + Dona.
3. Columns: _Kutilgan_ (expected) and _Farq_ (difference) are shown to the owner. For employees, recommend hiding _Kutilgan_ and _Farq_, a blind count; see D6.
4. _(employee)_ **Saqlash**, which can be repeated while counting.
5. _(owner)_ Opens the draft and sees expected, counted and the difference per line. Shortage and surplus totals are valued at average cost, labelled _Kamomad_ and _Ortiqcha_.
6. _(owner)_ **Bajarish**. The confirmation repeats the totals. Note: expected is recomputed at execution, because stock may have moved since counting (Odoo warns about this case [33]).

### 2.7 Opening balance (boshlang'ich qoldiq), owner only, one per store

Competitors: MoySklad enters opening stock by _Оприходование_ (receipt-of-goods document) or Excel import, which generates Оприходование documents of 500 lines each ([39]).

Recommended flow (owner):

1. Owner home shows the card **"Boshlang'ich qoldiqni kiriting"** (enter the opening balance) until it is executed. Also reachable via **Sozlamalar → Boshlang'ich qoldiq**.
2. One page with five tabs: **Tovarlar** (location, product, Blok/Dona, cost per piece), **Kassa** (cash, card, bank amounts), **Mijoz qarzlari** (customer debts), **Yetkazib beruvchi qarzlari** (supplier debts), **Qarzlar** (loans: given/received, principal, due date).
3. **Saqlash** as often as needed (large data entry). A totals summary per tab is shown.
4. **Bajarish**. The confirmation states it is a one-time action. After it, the menu item shows the read-only document.

### 2.8 Customer debt payment (qarz to'lovi)

Competitors: debt payment is started **from the customer**:

- MoySklad Касса: Покупатель → ⋯ → **Погасить долг** (repay debt), partial amount allowed ([5]).
- Odoo POS: Customer → ☰ → **Settle orders**, choose a method, **Validate** ([9]).

Recommended flow (employee and owner). One form with three entry points:

- the customer card or the debts list (**Qarzni to'lash**), with the customer pre-filled;
- **Pul → Kassa → + Kirim → Qarz to'lovi**;
- the sale screen when a customer with debt is selected (a link, inference).

Steps:

1. Form fields:
    - **Mijoz**, with the current debt shown;
    - **Summa**, with a **To'liq** button;
    - **To'lov usuli** (Naqd / Karta / Bank o'tkazmasi);
    - optional **Izoh**.
2. Validation: Summa > 0 and ≤ debt. Error: "Summa qarzdan oshmasligi kerak (qarz: 1 250 000)".
3. **Saqlash** opens a confirmation, then the cash operation is recorded immediately. Cash operations have no draft state (inference from the glossary: they are not documents).
4. Allocation to specific sales is not asked for. The debt is one balance per customer (inference; see D9 for FIFO display).

### 2.9 Supplier payment (yetkazib beruvchiga to'lov), owner only

Steps:

1. Entry: **Yetkazib beruvchi qarzlari → To'lash**, or **Kassa → + Chiqim → Yetkazib beruvchiga to'lov**.
2. Same form as 2.8.
3. Validation: amount ≤ supplier debt (Q54), and ≤ the method's register balance (D11).

### 2.10 Expense (xarajat), employee and owner

Competitors:

- Poster register: ≡ → **Add transaction** → Expense/Income/Safe drop, amount, comment and category. Only categories marked "Display on the POS" are offered, and register transactions tie to the shift ([40]).
- Loyverse: **Pay Out** inside the shift ([11]).
- MoySklad Касса: **Выплатить деньги** (pay out money) ([6]).

Recommended flow:

1. **Pul → Kassa → + Chiqim → Xarajat**.
2. Fields:
    - **Xarajat turi** (expense category), a fixed list. Employees see a restricted list; see D8.
    - **Summa**, **To'lov usuli** and **Izoh**. Izoh is required for _Boshqa_.
3. **Saqlash** → confirmation → recorded.

### 2.11 Loans and repayments (qarz / qarz qaytarish), owner only

Recommended flow (inference; competitors keep these in finance modules, see the earlier research):

1. **Pul → Qarzlar → Yangi qarz**. Fields:
    - **Turi**: _Berilgan_ (given) or _Olingan_ (received);
    - **Kontragent**, **Asosiy summa** (principal), **To'lov usuli**;
    - optional **Qaytarish muddati** (due date) and **Izoh**.
2. **Saqlash** → confirmation. This creates the loan and its _loan given_ or _loan received_ cash operation.
3. The list shows Kontragent, Turi, Asosiy, Qaytarilgan (repaid), Qoldiq (balance) and Muddat (due), with overdue in red.
4. The loan card has **Qaytarish** (repayment). Amount ≤ balance; error "Qoldiqdan oshmasin". Pick the method.
5. Interest is a separate action on the same card:
    - **Foiz to'lash** creates an expense of category _Qarz foizi_.
    - **Foiz olish** creates income of category _Olingan foiz_.
    - These keep principal out of P&L (ADR-0001).

### 2.12 Employee drafts → owner executes (handoff)

Competitors:

- **Dashboard cards:** Odoo's Inventory Overview shows a card per operation type with a "**# To Process**" button that opens the pending list ([8]).
- **Home task cards:** Lightspeed R-Series Home has **Tasks** cards (Sales, Special orders, Purchase orders) ([41]).
- **Approval states:** Square counts sit in review until a reviewer confirms ([35]). BITO has `debt_approve_by_boss` and `discount_approve_by_boss` flags ([21]).
- MoySklad marks drafts only by the missing _Проведено_ flag, with no dedicated inbox found ([3]).

Recommended (inference):

- **Owner home**, first widget: **"Bajarishni kutayotgan qoralamalar"** (drafts waiting for execution). It groups drafts of the owner-only kinds (Xarid, Qaytarish, Inventarizatsiya) with count, author, last change time and an **Ochish** (open) link per row.
- **Sidebar badge:** a number badge on Xaridlar, Qaytarishlar and Inventarizatsiyalar for the owner.
- **List filter:** every list has _Holat_ (status) tabs **Hammasi | Qoralama | Bajarilgan** and a **Muallif** (author) filter. The pending widget links to the list with `holat=qoralama`.
- The owner's executed view shows "Yaratdi: Ali (xodim) · Bajardi: Egasi" (created by / executed by) for audit.

### 2.13 Cash register view and shifts

Competitors:

- **Shifts:**
    - Odoo: mandatory session; the closing screen shows expected per method and Counted, with a **Payments Difference** override ([7]).
    - Lightspeed: daily register close; shortfall/overpayment is recorded ([16]).
    - Loyverse: optional; can hide expected cash from cashiers via "View shift report" ([11]).
    - Square: optional drawer sessions ([13]).
    - BITO: `cashbox-session` with expected/actual per method ([22]).
    - MoySklad Касса: auto-open on the first operation ([6]).
- **Without shifts:** the back-office document flows (MoySklad web, Odoo Sales/Inventory, Poster supplies) have none.

Recommended (v1, inference): **no shifts**. Instead:

1. **Pul → Kassa** has a balance strip per method plus a total: Naqd | Karta | Bank o'tkazmasi | Jami. Owner only (it is "Balans" data).
2. Below it, a list of cash operations with filters: date range, type, method, author, counterparty, plus **+ Kirim** (money in) and **+ Chiqim** (money out) buttons.
3. Employees see the list (D7) but not the balance strip.
4. A later "Kassa sanash" (cash count) feature can record a counted cash amount vs expected, without a full shift model.

## 3. Navigation

Competitors:

- One menu, with access by permission: Lightspeed Home needs the Reports permission, and profit/margin needs _Product Cost_ ([41]).
- MoySklad hides cost and profit via "Видеть себестоимость, цену закупки и прибыль" (see cost price, purchase price and profit) ([42]).

This matches our U2 (one sidebar, items hidden by permission).

**Recommended sidebar** (E = employee sees it, O = owner sees it):

| Group             | Item                                         | E                    | O               |
| ----------------- | -------------------------------------------- | -------------------- | --------------- |
| —                 | **Bosh sahifa**                              | ✓                    | ✓               |
| **Savdo**         | Sotuvlar (+ "Yangi sotuv" as the top button) | ✓                    | ✓               |
|                   | Qaytarishlar                                 | ✓ (draft)            | ✓ (badge)       |
| **Xarid**         | Xaridlar                                     | ✓ (draft, no prices) | ✓ (badge)       |
|                   | Yetkazib beruvchiga qaytarishlar             | — (D10)              | ✓               |
| **Ombor**         | Qoldiqlar (stock report)                     | ✓ (no value)         | ✓               |
|                   | Ko'chirishlar                                | ✓                    | ✓               |
|                   | Inventarizatsiyalar                          | ✓ (draft)            | ✓ (badge)       |
|                   | Mahsulotlar                                  | ✓ (view; D12)        | ✓               |
|                   | Omborlar                                     | —                    | ✓               |
| **Pul**           | Kassa                                        | ✓ (operations, D7)   | ✓ (+ balance)   |
|                   | Mijoz qarzlari                               | ✓                    | ✓ (+ write-off) |
|                   | Yetkazib beruvchi qarzlari                   | —                    | ✓               |
|                   | Qarzlar (loans)                              | —                    | ✓               |
| **Kontragentlar** | Kontragentlar                                | ✓                    | ✓               |
| **Hisobotlar**    | Sotuv hisoboti                               | D4                   | ✓               |
|                   | Foyda va zarar · Pul oqimi · Balans          | —                    | ✓               |
| **Sozlamalar**    | Xodimlar · Do'kon · Boshlang'ich qoldiq      | —                    | ✓               |

**Owner home (Bosh sahifa):**

1. The opening-balance setup card, until it is executed.
2. **Bajarishni kutayotgan qoralamalar** (2.12).
3. Today's figures: savdo (revenue), sotuvlar soni (number of sales), yalpi foyda (gross profit), kassaga tushum (cash received).
4. Cash balance per method.
5. Debts: customer debts total (overdue first), supplier debts total, loans outstanding.
6. Low-stock list (inference; needs a min-level field, so later).

Lightspeed's Home uses a similar layout: Quick Stats, Sales chart, Tasks and Activity ([41]).

**Employee home:**

1. Big buttons: **Yangi sotuv**, **Yangi ko'chirish**, **Qarz to'lovi**, **Xarajat**.
2. "Mening qoralamalarim" (my drafts), with status.
3. My sales today: count and revenue, no cost.

**List pages** (all documents):

- **Columns:** №, Sana (date: executed-at or created-at), Joy, Kontragent, Summa, To'langan / Qarz (paid / debt, sales and purchases), Holat badge, Muallif.
- **Filters:** Holat tabs, date range, Joy, Kontragent, Muallif.
- **Search:** by № or counterparty name.
- **Row behaviour:** click opens the document; a draft opens editable.

**Empty states:**

- One sentence plus the primary button, e.g. "Hali sotuv yo'q" + **Yangi sotuv**.
- Filtered empty: "Filtrga mos hujjat topilmadi" + **Filtrni tozalash**.

## 4. Open UX decisions (for the product owner)

| #   | Decision                                                  | Options                                                                                                                                                                | Recommendation · reason                                                                                                                                                                           |
| --- | --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | Sale screen style (U3)                                    | a) tile-grid POS checkout; b) plain document form; c) **fast document**: a document form with autofocus scan, keyboard shortcuts and an inline pay panel               | **c**. It has POS speed (scan, Enter, F9), but stays one document model with per-sale prices and block quantities, which a grid POS fits badly.                                                   |
| D2  | Cashier shifts                                            | a) **none in v1**; b) optional shift with opening/closing cash count; c) mandatory shifts                                                                              | **a**. One register, few employees and an author on every operation. Shifts are optional even in Loyverse, Square and BILLZ, and add a step before every sale. Revisit as b if cash goes missing. |
| D3  | How the owner sees pending drafts                         | a) list filter only; b) **home widget + sidebar badges + list filter**; c) b plus an explicit employee "Yuborish" (submit) flag, so half-finished drafts are not shown | **b**. Cheapest clear inbox (Odoo "# To Process", Lightspeed Tasks). Choose c only if owners execute half-finished drafts in practice.                                                            |
| D4  | Employees and the sales report                            | a) no access; b) **yes, without cost/profit columns**; c) yes, own sales only                                                                                          | **b**. Lightspeed does exactly this (Reports without Product Cost hides margins). Employees need daily totals.                                                                                    |
| D5  | Where debt payment lives                                  | a) only Kassa; b) only the customer card; c) **one form with entry points from the customer card / debts list and Kassa**                                              | **c**. Competitors start from the customer (MoySklad, Odoo), but cashiers think "money in" too.                                                                                                   |
| D6  | Blind count for employees                                 | a) employees see Kutilgan/Farq; b) **hide expected from employees; the owner sees it before Bajarish**                                                                 | **b**. Prevents copying the expected value. It fits the draft/execute split (Square review, Lightspeed reconcile).                                                                                |
| D7  | Employee access to Kassa                                  | a) none; b) **operations list (own, or all) without the balance strip**; c) full, with the balance                                                                     | **b**. They create debt payments and expenses and need to see them. The balance is "Balans" data, owner-only.                                                                                     |
| D8  | Expense categories for employees                          | a) all; b) **all except _Egasi olgan pul_ and _Qarz foizi_** (and _Umidsiz qarz_, which is not a cash expense)                                                         | **b**. Those are owner/loan concepts; Poster also limits which categories show at the register.                                                                                                   |
| D9  | Which sale a debt payment pays off                        | a) **one balance per customer, display FIFO by oldest sale**; b) the user picks sales (Odoo "Settle orders")                                                           | **a**. Simpler for cashiers. Per-sale paid/debt stays visible in the sales report via FIFO.                                                                                                       |
| D10 | Supplier return: employee draft (Q17) vs owner only (Q51) | a) **owner only**; b) employee drafts without prices                                                                                                                   | **a**. The document is valued at purchase prices that employees cannot see. Fix Q17 to match.                                                                                                     |
| D11 | Block cash-out above the register balance per method      | a) **block**; b) warn only                                                                                                                                             | **a**. Same logic as no negative stock. A negative cash balance usually means a missing operation.                                                                                                |
| D12 | Can employees create products and counterparties?         | a) both; b) **counterparties yes (quick-create in sale), products owner only**; c) neither                                                                             | **b**. A sale on debt needs an on-the-spot customer. Products affect stock and reports.                                                                                                           |
| D13 | Executing documents before the opening balance            | a) allowed; b) **block every Bajarish until the opening balance is executed or the owner clicks "Boshlang'ich qoldiq yo'q"**                                           | **b**. Without backdating, a late opening balance would distort average cost and debts.                                                                                                           |
| D14 | Correcting a mistaken cash operation                      | a) **immutable; correct with an opposite operation**; b) owner may delete                                                                                              | **a**. Same rule as documents (ADR-0006), and keeps Pul oqimi auditable.                                                                                                                          |
| D15 | Default quantity on scan                                  | a) **+1 dona**; b) +1 blok                                                                                                                                             | **a**. Pieces are the base unit, and the block field stays one Tab away. Reconsider if most sales are by the block.                                                                               |

## Sources

1. MoySklad, Отгрузка товаров: https://support.moysklad.ru/hc/ru/продажи/отгрузка_товаров
2. MoySklad, Продажа в кассе: https://support.moysklad.ru/hc/ru/касса%20и%20розница/работа%20в%20кассе/продажа_в_кассе
3. MoySklad, Документы (draft = no Проведено): https://support.moysklad.ru/hc/ru/знакомство%20с%20сервисом/интерфеис/документы
4. MoySklad, Розница: обзор возможностей (lists "Отложенные чеки в кассе"): https://support.moysklad.ru/hc/ru/articles/203325423
5. MoySklad, Продажа в долг (KZ/UZ): https://support.moysklad.ru/hc/ru/касса%20и%20розница/работа%20в%20кассе/продажа_в_долг_кз
6. MoySklad, Открытие и закрытие смены в кассе: https://support.moysklad.ru/hc/ru/articles/115004375987
7. Odoo 18, Point of Sale (sessions, payment, closing, cash in/out, refund): https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale.html
8. Odoo 17, Two-step receipt and delivery (Overview cards, "# To Process", Validate): https://www.odoo.com/documentation/17.0/applications/inventory_and_mrp/inventory/shipping_receiving/daily_operations/receipts_delivery_two_steps.html
9. Odoo 19, POS Customer account: https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/payment_methods/customer_credit.html
10. Loyverse, Open tickets: https://help.loyverse.com/help/open-tickets
11. Loyverse, Shift management: https://help.loyverse.com/help/shift-management-loyverse-pos and access rights: https://help.loyverse.com/help/how-manage-access-rights-employees
12. Square, Build your customer's cart: https://squareup.com/help/us/en/article/8238-build-your-customer-s-cart-in-the-square-retail-pos-app
13. Square, Cash drawer sessions: https://squareup.com/help/us/en/article/8344-start-and-end-a-cash-drawer-session
14. Lightspeed X, Continuing saved sales / Making sales: https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534210016539 , https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534025069723-Making-sales
15. Lightspeed X, On-Account vs layaway: https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534308715547
16. Lightspeed X, Opening and closing a register: https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534185400347
17. BILLZ, Sale lifecycle: https://docs.billz.io/concepts/sale-lifecycle/
18. BILLZ, Debt API: https://docs.billz.io/reference/api/operations/get_v1_debt/
19. BITO, Open ticket API: https://docs.bito.uz/api/open-ticket.html
20. BITO, Payment type: https://docs.bito.uz/api/payment-type.html
21. BITO, Sale settings: https://docs.bito.uz/api/sale-settings.html
22. BITO, Cashbox session: https://docs.bito.uz/api/cashbox-session.html
23. Poster, How to add a supply: https://knowledge-base.joinposter.com/en/how-to-add-a-supply
24. Poster, How to accept order payments: https://knowledge-base.joinposter.com/en/how-to-accept-order-payments
25. Poster, Point of Sale index and adjustment transactions: https://knowledge-base.joinposter.com/en/how-to-work-with-pos-register , https://knowledge-base.joinposter.com/en/what-is-the-an-adjustment-transaction
26. MoySklad, Приемка товаров: https://support.moysklad.ru/hc/ru/закупки/приемка_товаров
27. Lightspeed X, Receiving purchase orders: https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534168876187-Receiving-purchase-orders
28. Lightspeed R, Employee roles and access: https://retail-support.lightspeedhq.com/hc/en-us/articles/229129608-Setting-up-employee-roles-and-access
29. Odoo 19, Returns and refunds: https://www.odoo.com/documentation/19.0/applications/sales/sales/products_prices/returns.html
30. Loyverse, Issue a refund: https://help.loyverse.com/help/how-issue-refund-loyverse-pos
31. Square, Manage customer refunds: https://squareup.com/help/us/en/article/6116-process-refunds
32. BILLZ, Processes: https://docs.billz.io/concepts/processes/
33. Odoo 18, Count products: https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/warehouses_storage/inventory_management/count_products.html
34. MoySklad, Инвентаризация товаров: https://support.moysklad.ru/hc/ru/товары%20и%20склады/операции/инвентаризация_товаров
35. Square, Conduct, review and approve inventory counts: https://squareup.com/help/au/en/article/8249-conduct-full-inventory-counts-with-square-for-retail
36. Poster, Inventory check at the POS register: https://knowledge-base.joinposter.com/en/how-to-run-an-inventory-check-at-the-pos-register
37. Lightspeed R, Performing inventory counts: https://retail-support.lightspeedhq.com/hc/en-us/articles/229129948-Performing-inventory-counts-in-Retail-POS
38. Loyverse, Inventory count: https://help.loyverse.com/help/how-work-inventory-count
39. MoySklad, Импорт складских остатков: https://support.moysklad.ru/hc/ru/articles/202984766
40. Poster, Add a transaction on the register: https://knowledge-base.joinposter.com/en/how-to-add-a-transaction-on-the-register
41. Lightspeed R, Understanding Home: https://retail-support.lightspeedhq.com/hc/en-us/articles/360009800674-Understanding-Home
42. MoySklad, Разграничение доступа: https://support.moysklad.ru/hc/ru/управление%20аккаунтом/пользователи/разграничение_доступа_настроика_прав_роли
