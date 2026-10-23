export type DocumentStatus = 'draft' | 'executed';

export type DocumentLineData = {
    id: number;
    product_id: number;
    product_name: string;
    location_id: number | null;
    location_name: string | null;
    quantity: number;
    pieces_per_block: number;
    /** Absent where prices are costs (purchases) and the user may not view costs. */
    price?: string | null;
    /** Inventory count: pieces on hand at execution; only for users who may execute counts. */
    expected_quantity?: number | null;
    /** Inventory count: average cost at execution; also needs costs.view. */
    unit_cost?: string | null;
    /** Inventory count: difference valued at average cost (negative: shortage); also needs costs.view. */
    difference_value?: string | null;
};

/** A document as the shared document form receives it (DocumentResource). */
export type DocumentData = {
    id: number;
    kind: string;
    number: number;
    status: DocumentStatus;
    location_id: number | null;
    location_name?: string | null;
    /** Transfer: the location goods move to. */
    destination_location_id: number | null;
    destination_location_name?: string | null;
    counterparty_id: number | null;
    counterparty_name?: string | null;
    note: string | null;
    author: string;
    executor: string | null;
    created_at: string | null;
    executed_at: string | null;
    lines: DocumentLineData[];
    /** Amount per payment method (the opening balance's Kassa tab), when loaded. */
    payments?: Partial<Record<string, string>>;
    /** The opening balance's open loans (Qarzlar tab), when loaded. */
    loans?: OpeningLoanData[];
    /** The opening balance's customer debts (Mijoz qarzlari tab), when loaded. */
    customer_debts?: OpeningDebtData[];
    /** The opening balance's supplier debts (Yetkazib beruvchi qarzlari tab), when loaded. */
    supplier_debts?: OpeningDebtData[];
};

export type OpeningDebtData = {
    id: number;
    counterparty_id: number;
    counterparty_name: string;
    amount: string;
};

export type OpeningLoanData = {
    id: number;
    direction: 'given' | 'received';
    counterparty_id: number;
    counterparty_name: string;
    principal: string;
    due_date: string | null;
    note: string | null;
};

export type PickerProduct = {
    id: number;
    name: string;
    barcode: string | null;
    pieces_per_block: number;
};

export type PickerOption = {
    id: number;
    name: string;
};
