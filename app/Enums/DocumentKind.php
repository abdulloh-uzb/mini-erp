<?php

namespace App\Enums;

/**
 * Every kind of document that moves goods. Numbers run per store and kind ("Sotuv №123").
 */
enum DocumentKind: string
{
    case Sale = 'sale';
    case SalesReturn = 'sales_return';
    case Purchase = 'purchase';
    case SupplierReturn = 'supplier_return';
    case Transfer = 'transfer';
    case InventoryCount = 'inventory_count';
    case OpeningBalance = 'opening_balance';

    /**
     * Get the Uzbek display label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sotuv',
            self::SalesReturn => 'Qaytarish',
            self::Purchase => 'Xarid',
            self::SupplierReturn => 'Yetkazib beruvchiga qaytarish',
            self::Transfer => "Ko'chirish",
            self::InventoryCount => 'Inventarizatsiya',
            self::OpeningBalance => "Boshlang'ich qoldiq",
        };
    }

    /**
     * Determine if the kind's prices are purchase prices or costs, which only users who may view
     * costs receive. Sale prices are not.
     */
    public function pricesAreCosts(): bool
    {
        return in_array($this, [self::Purchase, self::SupplierReturn, self::OpeningBalance], true);
    }

    /**
     * Get the permission needed to create, edit and delete drafts of this kind.
     */
    public function draftPermission(): StorePermission
    {
        return match ($this) {
            self::Sale => StorePermission::SaleDraft,
            self::SalesReturn => StorePermission::SalesReturnDraft,
            self::Purchase => StorePermission::PurchaseDraft,
            self::SupplierReturn => StorePermission::SupplierReturnManage,
            self::Transfer => StorePermission::TransferDraft,
            self::InventoryCount => StorePermission::InventoryCountDraft,
            self::OpeningBalance => StorePermission::OpeningBalanceManage,
        };
    }

    /**
     * Get the permission needed to execute documents of this kind.
     */
    public function executePermission(): StorePermission
    {
        return match ($this) {
            self::Sale => StorePermission::SaleExecute,
            self::SalesReturn => StorePermission::SalesReturnExecute,
            self::Purchase => StorePermission::PurchaseExecute,
            self::SupplierReturn => StorePermission::SupplierReturnManage,
            self::Transfer => StorePermission::TransferExecute,
            self::InventoryCount => StorePermission::InventoryCountExecute,
            self::OpeningBalance => StorePermission::OpeningBalanceManage,
        };
    }
}
