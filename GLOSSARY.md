# Mini-ERP for a beverage store

Stock, documents and cash for a shop that sells drinks: what is on hand, where it sits, and what the owner earns.

## Stores and people

**Store** (_do'kon_):
One drinks business using the system; every product, location, document and cash operation belongs to exactly one store.
_Avoid_: Tenant, team, company, shop (in UI copy)

**Super-admin**:
The platform operator. Manages stores but sees none of a store's business data.
_Avoid_: Admin, root

**Owner** (_egasi_):
The person who runs a store and may do everything in it, including managing employees and seeing costs and profit.

**Employee** (_xodim_):
A person working in one store who sells, counts and receives goods but cannot see costs, profit or loans.
_Avoid_: Seller, cashier, staff, worker

## Stock

**Piece** (_dona_):
The smallest unit of a product that is counted and sold, e.g. one 1.5 L bottle. All stock quantities are held in pieces.
_Avoid_: Unit, item

**Block** (_blok_):
A case of a product holding a fixed number of pieces. A display and entry unit only: blocks are always converted to pieces.
_Avoid_: Case, box, pack

**Pieces per block**:
The fixed number of pieces in one block of a given product. Each product has exactly one.

**Warehouse** (_ombor_):
A top-level place where stock is kept. It may hold stock directly and may contain sub-warehouses.
_Avoid_: Store, depot

**Sub-warehouse** (_ichki ombor_):
A place inside a warehouse, one level deep only. Its stock counts towards its warehouse's totals.
_Avoid_: Shelf, section, bin

**Location**:
Either a warehouse or a sub-warehouse: anywhere stock can sit.

## Documents

**Document**:
A sale, sales return, purchase, supplier return, transfer or inventory count. Every document moves from draft to executed, and an executed document is permanent: mistakes are corrected by a new, opposite document.

**Draft** (_qoralama_):
A document that has been saved but has no effect on stock or cash. It can be edited or deleted.

**Executed** (_bajarilgan_):
A document whose effects on stock and cash have been applied. It can never be edited, deleted or cancelled; its date is the moment it was executed.
_Avoid_: Posted, confirmed, completed, cancelled

**Sale** (_sotuv_):
A document that takes pieces out of a location for a customer.

**Sales return** (_qaytarish_):
A document that brings pieces from an executed sale back into a location, refunding the customer or reducing their customer debt.
_Avoid_: Refund, cancellation

**Purchase** (_xarid_):
A document that brings pieces into a location from a supplier.

**Supplier return** (_yetkazib beruvchiga qaytarish_):
A document that sends purchased pieces back to the supplier, getting money back or reducing the supplier debt.

**Opening balance** (_boshlang'ich qoldiq_):
A document recording what a store already had when it started using the system: stock with its cost per piece, cash per payment method, customer and supplier debts, and open loans. It never affects net profit.
_Avoid_: Initial stock, import

**Transfer** (_ko'chirish_):
A document that moves pieces from one location to another within a store, with no effect on money.
_Avoid_: Movement, relocation

**Inventory count** (_inventarizatsiya_):
A document recording the pieces physically counted for some products in one location; only the counted products are corrected.
_Avoid_: Stocktake, audit, revision

**Shortage** (_kamomad_):
Pieces the system expected that an inventory count did not find. A loss in P&L.
_Avoid_: Write-off, loss

**Surplus** (_ortiqcha_):
Pieces an inventory count found beyond what the system expected. A gain in P&L.

**Previous price**:
The price used for the same product on the store's last executed document of the same kind; it pre-fills a new line.
_Avoid_: Default price, list price

## Money

**Cash register** (_kassa_):
The store's record of money coming in and going out, made of cash operations.
_Avoid_: Till, cashbox

**Cash operation** (_kassa operatsiyasi_):
One movement of money in or out of the cash register, of a fixed type (sale payment, debt payment, expense, loan, repayment, …).
_Avoid_: Transaction, entry

**Payment method** (_to'lov usuli_):
How money in a cash operation moved: cash, card (Uzcard/Humo) or bank transfer. The cash register keeps a balance per payment method.

**Counterparty** (_kontragent_):
A person or business the store deals with: a customer, a supplier, or a loan party. One counterparty can play several of these roles.
_Avoid_: Contact, client, partner

**Customer debt** (_mijoz qarzi_):
What a counterparty still owes the store for sales not fully paid.
_Avoid_: Receivable, nasiya (in code)

**Debt write-off** (_qarzni hisobdan chiqarish_):
The owner declaring part or all of a customer debt uncollectable. It reduces the debt and counts as a bad-debt expense in net profit; no money moves.
_Avoid_: Forgiveness, cancellation

**Supplier debt** (_yetkazib beruvchiga qarz_):
What the store still owes a counterparty for purchases not fully paid.
_Avoid_: Payable

**Loan** (_qarz_):
Money lent to or borrowed from a counterparty, separate from any sale or purchase. Its principal never enters net profit; only interest does.
_Avoid_: Credit

**Repayment** (_qarz qaytarish_):
A cash operation that reduces a loan's outstanding balance, in either direction.

**Expense category** (_xarajat turi_):
A fixed kind of expense (e.g. Transport) that classifies an expense cash operation and says whether it counts in net profit.
_Avoid_: Expense type, expense item

**Average cost** (_o'rtacha tannarx_):
A product's cost per piece, averaged over everything bought so far; it is what a sold piece costs the store.
_Avoid_: Cost price, purchase price

**Net profit** (_sof foyda_):
Sales revenue minus the average cost of pieces sold, minus counted expenses, minus shortages, plus surpluses, plus or minus loan interest.
_Avoid_: Profit, income, margin
