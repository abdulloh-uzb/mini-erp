/** Pieces of a product at a location, as the sale screen receives them. */
export type StockRow = {
    location_id: number;
    product_id: number;
    quantity: number;
};

export type SaleCustomer = {
    id: number;
    name: string;
    phone: string | null;
};

/** A parked sale shown as a tab on the sale screen (Qoralamalar). */
export type ParkedSale = {
    id: number;
    number: number;
    counterparty: string | null;
    total: string;
};

/** What the printed receipt of an executed sale shows (`SaleController::receipt`). */
export type SaleReceipt = {
    id: number;
    number: number;
    executed_at: string | null;
    location: string | null;
    customer: string | null;
    seller: string;
    lines: {
        product_name: string;
        quantity: number;
        pieces_per_block: number;
        price: string | null;
    }[];
    payments: { method: string; amount: string }[];
    total: string;
    debt: string;
};
