import { formatDateTime, formatMoney, formatQuantity } from '@/lib/format';
import type { SaleReceipt as SaleReceiptData } from '@/types';

/**
 * The paper receipt of an executed sale (story 48). Marked `receipt-print`, so printing
 * the page prints only the receipt.
 */
export default function SaleReceipt({ receipt }: { receipt: SaleReceiptData }) {
    return (
        <div className="receipt-print mx-auto w-full max-w-xs font-mono text-xs">
            <div className="text-center">
                <div className="text-sm font-bold">Sotuv №{receipt.number}</div>
                {receipt.location && <div>{receipt.location}</div>}
                <div>{formatDateTime(receipt.executed_at)}</div>
                <div>Sotuvchi: {receipt.seller}</div>
                {receipt.customer && <div>Mijoz: {receipt.customer}</div>}
            </div>
            <hr className="my-2 border-dashed border-current" />
            {receipt.lines.map((line, index) => (
                <div key={index} className="mb-1">
                    <div>{line.product_name}</div>
                    <div className="flex justify-between gap-2">
                        <span>
                            {formatQuantity(
                                line.quantity,
                                line.pieces_per_block,
                            )}{' '}
                            × {formatMoney(line.price)}
                        </span>
                        <span>
                            {formatMoney(
                                line.quantity * Number(line.price ?? 0),
                            )}
                        </span>
                    </div>
                </div>
            ))}
            <hr className="my-2 border-dashed border-current" />
            <div className="flex justify-between text-sm font-bold">
                <span>JAMI</span>
                <span>{formatMoney(receipt.total)}</span>
            </div>
            {receipt.payments.map((payment) => (
                <div key={payment.method} className="flex justify-between">
                    <span>{payment.method}</span>
                    <span>{formatMoney(payment.amount)}</span>
                </div>
            ))}
            {Number(receipt.debt) > 0 && (
                <div className="flex justify-between font-bold">
                    <span>Qarzga</span>
                    <span>{formatMoney(receipt.debt)}</span>
                </div>
            )}
            <div className="mt-3 text-center">Xaridingiz uchun rahmat!</div>
        </div>
    );
}
