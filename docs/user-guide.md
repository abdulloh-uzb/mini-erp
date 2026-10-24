# User guide

This guide walks the **owner** and the **employee** of a drinks store through every task in the mini-ERP. The application itself is in Uzbek. Every button, menu item and message is quoted here **in bold or _italics_ exactly as it appears on screen**, with an English explanation next to it.

Words such as _document_, _draft_, _executed_, _piece_, _block_ and _location_ have exact meanings, defined in [`GLOSSARY.md`](../GLOSSARY.md). How and why the system works this way is in [`architecture.md`](architecture.md).

## Contents

0. [Before you start](#0-before-you-start)
1. [Sign-up and login](#1-sign-up-and-login)
2. [Employees](#2-employees)
3. [Products, warehouses and counterparties](#3-products-warehouses-and-counterparties)
4. [Opening balance](#4-opening-balance)
5. [Purchase](#5-purchase)
6. [Sale](#6-sale)
7. [Debts](#7-debts)
8. [Returns](#8-returns)
9. [Transfer](#9-transfer)
10. [Inventory count](#10-inventory-count)
11. [Cash register](#11-cash-register)
12. [Loans](#12-loans)
13. [Reports](#13-reports)
14. [Account settings and the super-admin](#14-account-settings-and-the-super-admin)

---

## 0. Before you start

### Who can do what

| | Owner (_Egasi_) | Employee (_Xodim_) |
| --- | --- | --- |
| Sell, transfer goods | yes | yes |
| Take customer debt payments, record expenses | yes | yes |
| Purchases, inventory counts, sales returns | create and execute | create drafts only; the owner executes them |
| Supplier returns, opening balance, loans, debt write-off, paying suppliers | yes | no |
| See purchase prices, average cost, profit, cash balance, finance reports | yes | no |
| Correct a cash operation (**Tuzatish**) | yes | no |
| Manage employees, products, warehouses | yes | view products only |
| Counterparties | yes | view, and create inside a document |

The left-hand menu shows each person only what they are allowed to open. A page that is not in your menu is refused even if you type its address.

### How quantities and money are shown

- Stock is counted in **pieces** (_dona_), such as one 1.5 L bottle. A **block** (_blok_) is a case of a fixed number of pieces, set per product. Quantities are typed as blocks + pieces and shown as **4 blok + 3 dona**.
- Money is in Uzbek so'm, shown as **1 250 000 so'm**.
- Payment methods (_To'lov usuli_): **Naqd** (cash), **Karta** (card: Uzcard/Humo), **Bank o'tkazmasi** (bank transfer). The cash register keeps a separate balance for each.

### Drafts and execution: the rule behind every document

Sales, purchases, returns, transfers, counts and the opening balance are all **documents**, and each one goes through two steps:

1. **Saqlash** (Save) stores it as a **Qoralama** (draft, grey badge). A draft changes nothing: no stock moves and no money moves. You can reopen it, edit it, or delete it with **O'chirish**.
2. **Bajarish** (Execute) applies it. Stock moves, money enters or leaves the register, and debts change. The badge turns into **Bajarilgan** (executed, green). You always confirm first: _"Bajarilgan hujjatni o'zgartirib yoki o'chirib bo'lmaydi. Davom etasizmi?"_ ("An executed document can't be changed or deleted. Continue?").

**An executed document is permanent.** It can't be edited, deleted or cancelled, and its date is the moment it was executed. You fix a mistake with an opposite document:

| Mistake in | Fix it with |
| --- | --- |
| a sale | a sales return (section 8) |
| a purchase | a supplier return (section 8) |
| a transfer | a reverse transfer (section 9) |
| an inventory count | a new count (section 10) |
| a cash operation | its opposite, **Tuzatish** (section 11) |

If execution is refused, nothing changes. The reason appears in red next to the line or field, for example _Joyda yetarli emas. Qoldiq: 2 blok + 4 dona._ ("Not enough at this location. On hand: 2 blocks + 4 pieces.").

### Document lists

Every document list (**Xaridlar**, **Vozvratlar**, **Transferlar**, **Inventarizatsiya**, …) has the same tools:

- tabs **Hammasi** (all), **Qoralama** (drafts) and **Bajarilgan** (executed);
- filters **Dan** / **Gacha** (from/to date), **Joy** (location), **Kontragent** (counterparty) and **Muallif** (author);
- search by document number or counterparty;
- **Filtrni tozalash** clears every filter.

An executed document shows who created it and who executed it, for example _Yaratdi: Ali · Bajardi: Egasi_.

---

## 1. Sign-up and login

### Owner: create your store

1. Open the site and click **Ro'yxatdan o'tish** (Register).
2. Fill in **Ismingiz** (your name), **Telefon raqami** (phone, typed into the mask `+998 __ ___ __ __`), **Parol** (password) twice, and **Do'kon nomi** (store name).
3. Submit. You are now the owner of a new store. It already has one warehouse, **Asosiy ombor** (main warehouse), and the fixed list of expense categories. You land on **Bosh sahifa** (home).

A phone number can belong to only one account. If yours is taken, the form says so.

### Everyone: log in

On **Kirish** (Log in), enter **Telefon raqami** and **Parol**. Tick **Meni eslab qol** (remember me) on your own computer. There is no email login. If you forget your password, ask the owner, or for an owner, the super-admin.

If you see **Do'kon bloklangan.** (store blocked), the super-admin has blocked your store. Your data is kept, and you can log in again once the store is unblocked.

### Home page

- **Owner home** shows the _Boshlang'ich qoldiqni kiriting_ card until the opening balance is done (section 4); **Bajarishni kutayotgan qoralamalar** (drafts waiting for you to execute) with **Ochish** (open) links; **Bugun** (today): **Tushum** (revenue), **Sotuvlar soni** (number of sales), **Yalpi foyda** (gross profit) and **Kelgan pul** (money received); **Kassa** (cash per method); and **Qarzlar** (debt totals): **Mijozlar qarzi**, **Yetkazib beruvchilarga qarz**, **Berilgan qarzlar**, **Olingan qarzlar**.
- **Employee home** shows big buttons **Yangi sotuv** (new sale), **Yangi ko'chirish** (new transfer), **Qarz to'lovi** (debt payment) and **Xarajat** (expense), plus **Mening qoralamalarim** (my drafts) and **Mening sotuvlarim bugun** (my sales today).

---

## 2. Employees

_Owner only. Menu: **Sozlamalar → Xodimlar**._

- **Add an employee:** click **Xodim qo'shish**, then enter **Ism** (name), **Telefon raqami**, **Parol** and **Parolni tasdiqlang** (confirm password), and click **Qo'shish**. Give the employee their phone and password; they log in straight away.
- **Reset a forgotten password:** on the employee's row click **Parolni tiklash**, then type the new password.
- **Someone left:** click **O'chirish** (deactivate). Their status becomes **O'chirilgan**, they are logged out and can't log in, and everything they did stays in the history. **Faollashtirish** lets them back in.

---

## 3. Products, warehouses and counterparties

### Products

_Menu: **Ombor → Mahsulotlar**. The owner manages products; employees can only view them._

Click **Mahsulot qo'shish** and enter **Nomi** (name, e.g. _Coca-Cola 1.5 L_), an optional **Shtrix-kod** (barcode) and **Blokdagi dona** (pieces per block, e.g. 6). Products have **no price**: each sale or purchase line fills in the price used last time for that product.

A product you no longer sell can be archived with **Arxivlash**. It disappears from new documents but stays in history and reports. **Arxivdan chiqarish** brings it back.

### Warehouses

_Owner only. Menu: **Ombor → Omborlar**._

Stock sits in **locations**: a warehouse (_ombor_) or a sub-warehouse (_ichki ombor_) inside one, one level deep. Add a warehouse with **Ombor qo'shish**, a sub-warehouse with **Ichki ombor qo'shish**, and rename one with **Nomini o'zgartirish**. A sub-warehouse's stock counts towards its warehouse in reports. A location no longer used can be archived.

### Counterparties

_Menu: **Kontragentlar**._

Customers, suppliers and people you lend to or borrow from share one list. You rarely need this page, because inside a sale, purchase or loan you can create one on the spot with **+ Yangi mijoz** / **Yangi yetkazib beruvchi** / **Yangi kontragent** by typing a name and an optional phone.

---

## 4. Opening balance

_Owner only. Menu: **Sozlamalar → Boshlang'ich qoldiq**._

The opening balance records what the store already had on the day it started using the system. **Nothing can be executed in the store** (no sale, purchase, transfer…) until you either execute the opening balance or declare there is none. The opening balance never counts as profit or loss.

1. On the home card _Boshlang'ich qoldiqni kiriting_ click **Kiritish** (enter).
2. Fill the tabs. You don't have to finish in one sitting: **Saqlash** keeps a draft you can come back to.
   - **Tovarlar** (goods): for each product, choose its **Joy** (location) and enter **Blok** / **Dona** and **Tannarx/dona** (cost per piece, what one piece cost you). This cost starts the product's average cost.
   - **Kassa**: how much money you have now in **Naqd**, **Karta** and **Bank o'tkazmasi**.
   - **Mijoz qarzlari** (customer debts): **Qarz qo'shish**, pick the customer and enter **Qarz summasi**.
   - **Yetkazib beruvchi qarzlari** (what you owe suppliers): the same, per supplier.
   - **Qarzlar** (loans already running): **Turi** (_Berilgan_ = you lent, _Olingan_ = you borrowed), **Kontragent**, **Asosiy summa** (principal still outstanding), optional **Muddat** (due date) and **Izoh** (note).
3. Click **Bajarish** and confirm. The opening balance can be executed only once.

**Starting from zero?** On the home card click **Boshlang'ich qoldiq yo'q** (no opening balance) and confirm with **Boshlang'ich qoldiqsiz ishlash**. You can't enter an opening balance after that, and a saved draft is deleted.

---

## 5. Purchase

_Menu: **Xarid → Xaridlar**. The employee records what arrived; the owner enters prices and executes._

### Employee: record a delivery

1. **Xaridlar → Yangi xarid**.
2. Choose **Yetkazib beruvchi** (supplier), using **Yangi** if they are new, and **Joy** (where the goods go).
3. Scan or search each product (**Mahsulot nomi yoki shtrix-kod**; Enter adds one piece), then set **Blok** / **Dona**. You don't see or enter prices.
4. Optional **Izoh**, such as the invoice number. Click **Saqlash**. The draft now waits for the owner.

### Owner: price, pay and execute

1. Open the draft from **Bajarishni kutayotgan qoralamalar** on the home page, or from **Xaridlar** (the menu item shows a count badge while drafts wait).
2. Check the lines. **Narx/dona** (price per piece) is pre-filled with the last purchase price of each product; correct it where needed. Every line needs a price, or execution stops with _Barcha narxlarni kiriting._
3. Enter how much you paid now in **Naqd**, **Karta** and/or **Bank o'tkazmasi**. Whatever is not paid shows as **Qarzga qoladi** and becomes debt to the supplier.
4. **Bajarish** and confirm.

What happens: the goods enter the location; each product's average cost is recalculated; the paid amounts leave the register; the unpaid part is added to **Yetkazib beruvchi qarzlari**. If a payment method doesn't hold enough money, execution is refused, for example _Kassada naqd yetarli emas_ (not enough cash in the register).

---

## 6. Sale

_Menu: **Savdo → Sotuv** (or **Yangi sotuv** on the home page). Owner and employee._

The sale screen is built for speed. Product tiles and a search box sit on the left, and the current sale (the cart) is on the right.

1. **Add products.** The cursor starts in the search box. Scan a barcode, or type a name and press **Enter**: each scan adds **+1 dona** and joins an existing line for the same product. Clicking a tile does the same. To sell whole blocks, press **Tab** to reach **Blok** on the line and type the number, or use the line's +/− buttons.
2. **Check prices.** **Narx/dona** is pre-filled with the last sale price of that product. A product never sold before shows _Narx yo'q_ and must be priced. Owners also see the average cost; when the price is below it the line warns _Narx o'rtacha tannarxdan past_.
3. **Check stock.** Each line shows what is left at the chosen location, for example _Qoldiq: 10 blok + 4 dona_. The **Joy** (location) is remembered from your last sale.
4. **Payment.** **Jami** is the total. Choose a method (**Naqd**, **Karta**, **Bank o'tkazmasi**) and type the amount. **Qolganini** puts the remaining amount on the chosen method, and **To'liq naqd** puts the whole total on cash. You can mix methods.
   - **Qarzga qoladi** shows live what will stay unpaid as the customer's debt.
   - **Mijoz** (customer) is optional while everything is paid. As soon as part of the sale stays unpaid it becomes **Mijoz (majburiy)** (required). Pick the customer, or create one with **+ Yangi mijoz**. A selected customer's current debt is shown so you can decide whether to sell more on debt.
   - Change helper: type **Mijoz berdi** (cash the customer handed you) to see **Qaytim** (change to give back). This is only a helper and is not saved.
   - Payments above the total are refused (_To'lov jami summadan oshib ketdi._).
5. **Finish.**
   - **Bajarish (F9)** executes the sale after confirmation. A receipt opens: **Chop etish** prints it, **Qaytarish yaratish** starts a return, and **Yangi sotuv** starts the next sale.
   - **Saqlash (F2)** parks the sale as a draft and clears the screen for the next customer. Parked sales appear as tabs above the cart (**Qoralamalar (N)**); click one to continue it. **O'chirish** deletes a parked sale.

If any line asks for more than the location holds, the sale is refused and that line shows what is actually there. Two people can't sell the same last bottle: the second sale is refused.

---

## 7. Debts

### Customer debts

_Menu: **Savdo → Mijoz qarzlari**. Owner and employee._

The list shows every customer who owes money and how much (**Qarz**). Click a customer to open their card. It shows **Qarz qanday yopiladi** (which sales are still unpaid; payments always pay off the oldest sale first) and **Tarix** (every sale, payment, return and write-off).

**Take a payment** (three ways, same form):

- **To'lov olish** on the customer's row in the list;
- **To'lov olish** on the customer's card;
- **Moliya → Kassa → Kirim**, choosing **Mijoz qarzi to'lovi** and the customer.

Enter **Summa** (or click **To'liq** for the full debt) and **To'lov usuli**. A payment can't exceed the debt (_Summa qarzdan oshmasligi kerak_).

**Write off a debt** (owner only): on the customer's card click **Qarzni hisobdan chiqarish**, enter the amount (or **To'liq**) and an optional note, then confirm with **Hisobdan chiqarish**. The debt goes down, no money moves, and the amount appears as an _Umidsiz qarz_ (bad debt) expense in **Foyda va zarar**.

### Supplier debts

_Owner only. Menu: **Xarid → Postavshik qarzlari**._

The list shows what you owe each supplier. Click **To'lash** (pay) on a row, or use **Kassa → Chiqim → Yetkazib beruvchiga to'lov**, then enter the amount and method. You can't pay more than you owe, or more than the register holds in that method.

---

## 8. Returns

### Sales return (customer brings goods back)

_Menu: **Savdo → Vozvratlar**. The employee drafts, the owner executes._

1. Start from the executed sale: **Qaytarish yaratish** on its receipt. Or go to **Vozvratlar → Yangi qaytarish** and pick the sale (search by number or customer).
2. Every line starts at 0, with **Sotilgan / Qaytarilgan / Qaytarish mumkin** (sold / already returned / can still be returned) shown. Enter **Blok** / **Dona** for what comes back. You can't return more than is returnable (_Ko'pi bilan 2 blok + 0 dona qaytarish mumkin_).
3. Choose **Joy** (where the goods go back) and, if money is to be handed back, **To'lov usuli**.
4. The panel shows how much **Qarzdan kamaydi** (comes off the customer's debt) and how much is paid back in money. The return always reduces the customer's debt first, and only the rest is refunded.
5. **Saqlash**. The owner opens the draft and clicks **Bajarish**.

The goods come back at the cost they were sold at, and revenue and profit for that sale are reversed.

### Supplier return (you send goods back)

_Owner only. Menu: **Xarid → Postavshikka vozvrat**._

1. **Qaytarish yaratish** on an executed purchase, or **Yangi qaytarish** and pick the purchase.
2. Enter what goes back, up to what was bought minus what was already returned. The location must still hold those pieces.
3. **Bajarish**. Your debt to the supplier goes down first; whatever is left comes back into the register in the chosen method.

---

## 9. Transfer

_Menu: **Ombor → Transferlar**. Owner and employee._

Moves goods from one location to another, for example from the main warehouse to the shop floor. Money and costs are not affected.

1. **Yangi ko'chirish**.
2. **Qayerdan** (from) and **Qayerga** (to). They must differ (_Bir xil joy tanlangan_).
3. Add products and **Blok** / **Dona**.
4. **Saqlash** to keep a draft, or **Bajarish** to move the goods now. If the source doesn't hold enough, the line says how much it does hold.

**Made a mistake?** Open the executed transfer and click **Teskari ko'chirish** (reverse transfer). A new draft opens with the same lines and the two locations swapped; execute it.

---

## 10. Inventory count

_Menu: **Ombor → Inventarizatsiya**. The employee counts, the owner reviews and executes._

### Employee: count

1. **Yangi inventarizatsiya**, then choose the **Joy** you are counting.
2. Add the products you count: scan or search them one by one (a partial count), or click **Joydagi barcha mahsulotlarni qo'shish** to list everything at that location (a full count).
3. Type what you physically see as **Blok** / **Dona** in **Sanalgan** (counted). You don't see what the system expects, so count honestly.
4. **Saqlash** as often as you like during a long count.

### Owner: review and execute

1. Open the draft (home inbox or the **Inventarizatsiya** badge).
2. Each line shows **Kutilgan** (expected), **Sanalgan** (counted), **Farq** (difference) and **Farq summasi** (difference valued at average cost), marked as **Kamomad** (shortage) or **Ortiqcha** (surplus).
3. **Bajarish**. The stock of each listed product is set to the counted quantity. Products not in the count keep their stock.

"Expected" is recalculated at the moment you execute, so sales made while the employee was counting don't show up as false shortages. Shortages appear as a loss and surpluses as a gain in **Foyda va zarar**.

---

## 11. Cash register

_Menu: **Moliya → Kassa**._

The cash register is the list of every money movement in the store. Sales, purchases, returns, debt payments and loans add their operations automatically. Here you record the rest.

- **Owner** sees the balance strip at the top, **Naqd | Karta | Bank o'tkazmasi | Jami**, and the full list. **Employees** see the list without the balances.
- Filter by **Dan** / **Gacha**, **Turi** (type), **To'lov usuli**, **Muallif** (who recorded it) and **Kontragent**.

**Record money in: Kirim.** Pick **Kirim turi**: _Mijoz qarzi to'lovi_ (a customer pays their debt, see section 7) or, for the owner, the income category _Olingan foiz_ (interest received). Then enter **Summa**, **To'lov usuli** and an optional **Izoh**, and click **Saqlash**.

**Record money out: Chiqim.** Pick **Xarajat turi** (expense category):

| Xarajat turi | Meaning | Counts in net profit | Employee may use |
| --- | --- | --- | --- |
| Transport xarajati | delivery, fuel | yes | yes |
| Ijara | rent | yes | yes |
| Ish haqi | salaries | yes | yes |
| Kommunal | utilities | yes | yes |
| Soliq | taxes | yes | yes |
| Boshqa | other; **a note is required** | yes | yes |
| Qarz foizi | interest you paid on a loan | yes | no |
| Egasi olgan pul | money the owner takes for themselves | **no** | no |
| _Yetkazib beruvchiga to'lov_ | not a category: paying a supplier's debt (section 7) | no | no |

Then enter **Summa**, **To'lov usuli** and **Izoh**. You can't take out more than the register holds in that method.

Saving asks for confirmation, because **cash operations are permanent**. To correct one, the owner clicks **Tuzatish** on its row. A **Teskari operatsiya** (opposite operation) with the same amount is recorded, cancelling it out. Each operation can be corrected once.

---

## 12. Loans

_Owner only. Menu: **Moliya → Qarzlar**._

Use loans for money lent or borrowed outside sales and purchases, for example a loan from a relative or to a neighbouring shop.

**Create a loan:** **Yangi qarz**, then **Turi** (**Berilgan** = you lent, **Olingan** = you borrowed), **Kontragent**, **Asosiy summa** (principal), **To'lov usuli**, optional **Qaytarish muddati** (due date) and note. On saving, the principal leaves the register (lent) or enters it (borrowed).

The list shows **Asosiy** (principal), **Qaytarilgan** (repaid), **Qoldiq** (outstanding) and **Muddat**. An overdue loan is shown in red. Click a loan to open its card, where you can:

- **Qaytarish**: record a repayment, partial or full, up to the outstanding balance. Money comes back into the register for a loan you gave, and leaves it for a loan you took.
- **Foiz to'lash** / **Foiz olish**: record interest paid or received. It is booked as _Qarz foizi_ (expense) or _Olingan foiz_ (income).

The loan amount itself is never profit or loss; only interest is. Loans appear next to net profit in **Foyda va zarar** and in **Balans**.

---

## 13. Reports

_Menu: **Hisobotlar** (and **Ombor → Qoldiqlar** for stock)._

### Qoldiqlar (stock), owner and employee

Current stock of each product in blocks + pieces. Filter by **Joy** (choosing a warehouse includes its sub-warehouses) or search a product. The owner also sees **O'rtacha tannarx** (average cost) and **Qiymati** (stock value).

### Sotuvlar hisoboti (sales report), owner and employee

For a date range: **Tushum** (revenue, with returns subtracted), **Sotuvlar soni** (number of sales), sales by product, **Xodimlar bo'yicha** (by employee), and each sale with **To'langan** (paid) vs **Qarz** (on debt). The owner also sees **Tannarx** (cost) and **Yalpi foyda** (gross profit); employees see the report without them.

### Foyda va zarar (profit and loss), owner

For a date range, line by line:

```
  Tushum                         revenue
− Sotilgan tovar tannarxi        cost of goods sold (at average cost)
= Yalpi foyda                    gross profit
− Xarajatlar                     each expense category that counts in profit, incl. Umidsiz qarz
− Kamomad                        inventory shortages
+ Ortiqcha                       inventory surpluses
− Qarz foizi / + Olingan foiz    loan interest
= Sof foyda                      net profit
```

Not included: payments to suppliers, debt payments, loan principal and repayments, _Egasi olgan pul_ and the opening balance. These are money movements, not profit or loss. Next to net profit the report shows **Kassada hozir** (cash now) and loans **Berilgan** / **Olingan**, so you see net profit and position together.

### Pul oqimi (cash flow), owner

Every cash operation in a period, totalled as **Kirim** (in), **Chiqim** (out) and **Qoldiq** (net), grouped **Turi bo'yicha** (by type and category) and **Kunlar bo'yicha** (by day). Click a day to open the Kassa list filtered to that day.

### Balans, owner

The store's position right now: cash per payment method and in total; **Berilgan qarzlar** / **Olingan qarzlar** (loans outstanding); **Mijozlar qarzi** (what customers owe you); **Yetkazib beruvchilarga qarz** (what you owe suppliers).

---

## 14. Account settings and the super-admin

### Your account

Click your name at the bottom of the menu, then go to the settings:

- **Profil**: your name and phone.
- **Xavfsizlik**: change your password (**Parolni yangilash**) and, optionally, turn on two-factor authentication or add a passkey.
- **Ko'rinish**: light or dark theme.

### Super-admin

The super-admin runs the platform, not a store. They are created only from the server's command line:

```bash
php artisan app:create-super-admin --name="Admin" --phone="+998901234567" --password="secret-password"
```

After logging in they see **Platforma → Do'konlar**, a list of every store with its owner, phone and status (**Faol** / **Bloklangan**). From there they can:

- **Bloklash** / **Blokdan chiqarish**: block or unblock a store. The store's users are logged out and can't log in while it is blocked, and nothing is deleted.
- **Egasining parolini tiklash**: set a new password for a store owner who is locked out.

The super-admin can't open any store's products, documents, cash or reports.
