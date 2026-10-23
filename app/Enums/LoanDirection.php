<?php

namespace App\Enums;

/**
 * Whether the store lent the money (given) or borrowed it (received).
 */
enum LoanDirection: string
{
    case Given = 'given';
    case Received = 'received';

    /**
     * Get the Uzbek display label for the direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::Given => 'Berilgan',
            self::Received => 'Olingan',
        };
    }

    /**
     * Get every direction as a picker option.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $direction) => ['id' => $direction->value, 'name' => $direction->label()], self::cases());
    }

    /**
     * Get the cash operation that pays the principal out (given) or in (received).
     */
    public function principalType(): CashOperationType
    {
        return $this === self::Given ? CashOperationType::LoanGiven : CashOperationType::LoanReceived;
    }

    /**
     * Get the cash operation that repays the loan: money coming back (given) or going out (received).
     */
    public function repaymentType(): CashOperationType
    {
        return $this === self::Given ? CashOperationType::RepaymentReceived : CashOperationType::RepaymentMade;
    }

    /**
     * Get the code of the category that records interest: received on a loan given (Olingan
     * foiz), paid on a loan received (Qarz foizi).
     */
    public function interestCategoryCode(): string
    {
        return $this === self::Given ? 'interest-received' : 'loan-interest';
    }
}
