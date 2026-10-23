<?php

namespace App\Enums;

/**
 * Fine-grained permissions checked by policies, route middleware and the sidebar.
 */
enum StorePermission: string
{
    case SaleDraft = 'sale.draft';
    case SaleExecute = 'sale.execute';
    case SalesReturnDraft = 'sales-return.draft';
    case SalesReturnExecute = 'sales-return.execute';
    case PurchaseDraft = 'purchase.draft';
    case PurchaseExecute = 'purchase.execute';
    case SupplierReturnManage = 'supplier-return.manage';
    case TransferDraft = 'transfer.draft';
    case TransferExecute = 'transfer.execute';
    case InventoryCountDraft = 'inventory-count.draft';
    case InventoryCountExecute = 'inventory-count.execute';
    case OpeningBalanceManage = 'opening-balance.manage';

    case CostsView = 'costs.view';
    case StockReportView = 'stock-report.view';
    case SalesReportView = 'sales-report.view';
    case FinanceReportsView = 'finance-reports.view';

    case CashOperationsView = 'cash-operations.view';
    case CashBalanceView = 'cash-balance.view';
    case CustomerDebtsView = 'customer-debts.view';
    case CustomerDebtPaymentCreate = 'customer-debt-payment.create';
    case DebtWriteOff = 'debt.write-off';
    case SupplierDebtsManage = 'supplier-debts.manage';
    case LoansManage = 'loans.manage';
    case ExpenseCreate = 'expense.create';
    case OwnerExpenseCategoriesUse = 'expense.owner-categories';

    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case LocationsManage = 'locations.manage';
    case CounterpartiesView = 'counterparties.view';
    case CounterpartiesCreate = 'counterparties.create';
    case CounterpartiesManage = 'counterparties.manage';

    case EmployeesManage = 'employees.manage';
    case StoreManage = 'store.manage';
}
