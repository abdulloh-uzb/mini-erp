# Expense categories are fixed per release, not user-editable

The software targets one kind of business, a drinks shop, so stores pick from a fixed list of expense categories seeded by us rather than managing their own (as MoySklad allows). This keeps every store's P&L comparable and the UI uncluttered. The list and each category's "include in P&L" flag live in the database and change through seeders/migrations, not the UI.
