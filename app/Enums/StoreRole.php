<?php

namespace App\Enums;

/**
 * Fixed roles: owner and employee are assigned per store, super-admin is global.
 */
enum StoreRole: string
{
    case Owner = 'owner';
    case Employee = 'employee';
    case SuperAdmin = 'super-admin';

    /**
     * Get the Uzbek display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Egasi',
            self::Employee => 'Xodim',
            self::SuperAdmin => 'Super-admin',
        };
    }

    /**
     * Get all the permissions granted to this role.
     *
     * @return array<int, StorePermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => StorePermission::cases(),
            self::Employee => [
                StorePermission::SaleDraft,
                StorePermission::SaleExecute,
                StorePermission::SalesReturnDraft,
                StorePermission::PurchaseDraft,
                StorePermission::TransferDraft,
                StorePermission::TransferExecute,
                StorePermission::InventoryCountDraft,
                StorePermission::StockReportView,
                StorePermission::SalesReportView,
                StorePermission::CashOperationsView,
                StorePermission::CustomerDebtsView,
                StorePermission::CustomerDebtPaymentCreate,
                StorePermission::ExpenseCreate,
                StorePermission::ProductsView,
                StorePermission::CounterpartiesView,
                StorePermission::CounterpartiesCreate,
            ],
            self::SuperAdmin => [],
        };
    }
}
