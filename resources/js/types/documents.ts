export type DocumentStatus = 'draft' | 'executed';

export type DocumentLineData = {
    id: number;
    product_id: number;
    product_name: string;
    location_id: number | null;
    location_name: string | null;
    quantity: number;
    pieces_per_block: number;
    price: string | null;
};

/** A document as the shared document form receives it (DocumentResource). */
export type DocumentData = {
    id: number;
    kind: string;
    number: number;
    status: DocumentStatus;
    note: string | null;
    author: string;
    executor: string | null;
    created_at: string | null;
    executed_at: string | null;
    lines: DocumentLineData[];
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
