<?php

namespace App\Enums;

/**
 * Which way a debt runs: a customer owes the store, or the store owes a supplier. One
 * counterparty can have a balance on each side.
 */
enum DebtSide: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';

    /**
     * Get the cash operation that pays the debt off: money coming in from a customer, going
     * out to a supplier.
     */
    public function paymentType(): CashOperationType
    {
        return $this === self::Customer ? CashOperationType::CustomerDebtPayment : CashOperationType::SupplierPayment;
    }
}
