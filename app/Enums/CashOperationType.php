<?php

namespace App\Enums;

/**
 * The fixed kinds of cash operation. Each kind moves money one way; only a reversing
 * operation (a correction, D14) moves it the other way.
 */
enum CashOperationType: string
{
    case SalePayment = 'sale_payment';
    case PurchasePayment = 'purchase_payment';
    case SalesReturnRefund = 'sales_return_refund';
    case SupplierReturnRefund = 'supplier_return_refund';
    case CustomerDebtPayment = 'customer_debt_payment';
    case SupplierPayment = 'supplier_payment';
    case Expense = 'expense';
    case Income = 'income';
    case LoanGiven = 'loan_given';
    case LoanReceived = 'loan_received';
    case RepaymentReceived = 'repayment_received';
    case RepaymentMade = 'repayment_made';
    case OpeningBalance = 'opening_balance';

    /**
     * Get the Uzbek display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SalePayment => "Sotuv to'lovi",
            self::PurchasePayment => "Xarid to'lovi",
            self::SalesReturnRefund => 'Mijozga qaytarilgan pul',
            self::SupplierReturnRefund => 'Yetkazib beruvchidan qaytgan pul',
            self::CustomerDebtPayment => "Qarz to'lovi",
            self::SupplierPayment => "Yetkazib beruvchiga to'lov",
            self::Expense => 'Xarajat',
            self::Income => 'Kirim',
            self::LoanGiven => 'Qarz berildi',
            self::LoanReceived => 'Qarz olindi',
            self::RepaymentReceived => 'Qarz qaytarib olindi',
            self::RepaymentMade => 'Qarz qaytarib berildi',
            self::OpeningBalance => "Boshlang'ich qoldiq",
        };
    }

    /**
     * Get the way money moves for an operation of this type.
     */
    public function direction(): CashDirection
    {
        return match ($this) {
            self::SalePayment, self::SupplierReturnRefund, self::CustomerDebtPayment, self::Income,
            self::LoanReceived, self::RepaymentReceived, self::OpeningBalance => CashDirection::In,
            self::PurchasePayment, self::SalesReturnRefund, self::SupplierPayment, self::Expense,
            self::LoanGiven, self::RepaymentMade => CashDirection::Out,
        };
    }

    /**
     * Get every type as a picker option.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['id' => $type->value, 'name' => $type->label()], self::cases());
    }
}
