# 03: Document core, opening stock, stock report

**What to build:** The draft → executed document lifecycle, the shared document form and document list, and the first document that uses them: the opening balance's stock section (stock per location with cost per piece). Executing it puts stock in place and sets each product's first average cost. Until the opening balance is executed or the owner clicks "Boshlang'ich qoldiq yo'q", no document can be executed. A stock report shows current balances. Spec stories 17–18, 22–24, 79–83, 103–104, 115; decisions Q5, Q6, Q7, Q28, Q41, Q47, Q49, Q52, D13; ADR-0002, ADR-0006.

**Blocked by:** 02

**Status:** ready-for-agent

- [x] Documents with kind, status, per-store per-kind sequential number, location(s), optional counterparty/source document, note, author, executor, `executed_at`; lines in pieces with pieces-per-block snapshot, price, stored unit cost.
- [x] Document execution module: one entry point "execute this document as this user"; one transaction; checks permission, draft status, opening-balance gate; locks stock balance and product cost rows; validates per kind; applies effects; stamps executor and `executed_at`. Failures change nothing and return Uzbek errors per line/field.
- [x] Stock ledger: balance per (store, location, product) in pieces plus append-only movements; never negative. Average cost per (store, product) with the blending rule from the spec.
- [x] Drafts editable/deletable; executed documents read-only; Bajarish always confirms with the spec's warning text; Qoralama/Bajarilgan badges; created-by / executed-by shown.
- [x] Shared document form (header, lines with blok + dona inputs, product search/scan adding +1 dona, totals panel, Saqlash / Bajarish / O'chirish / Bekor qilish) and shared document list (Hammasi / Qoralama / Bajarilgan tabs, date range, location, counterparty, author filters, search by number/counterparty).
- [x] Opening balance page (owner only, one per store) with the **Tovarlar** tab working (location, product, blok/dona, cost per piece); save many times; execute once; owner home card "Boshlang'ich qoldiqni kiriting" until done; "Boshlang'ich qoldiq yo'q" unlocks execution without one. Other tabs are added by tickets 04, 05, 08.
- [x] Stock report: current balances as "N blok + M dona", filter by location (warehouse includes sub-warehouses) and product; value at average cost only for users with the view-costs permission (omitted server-side otherwise).
- [x] Domain tests on the execution module: gate, no negative stock, average cost on opening stock, re-execution refused, failure leaves no partial effects. HTTP tests: permissions, read-only executed documents, stock report roll-up and cost hiding.
