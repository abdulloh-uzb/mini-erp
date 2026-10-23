/** A PHP enum case as a picker option (`PaymentMethod::options()`, `LoanDirection::options()`). */
export type EnumOption = {
    id: string;
    name: string;
};

/** A loan as the loans list and the loan card show it (`LoanController::loanRow`). */
export type LoanRow = {
    id: number;
    counterparty: string;
    direction: string;
    principal: string;
    repaid: string;
    balance: string;
    due_date: string | null;
    overdue: boolean;
    date: string | null;
};
