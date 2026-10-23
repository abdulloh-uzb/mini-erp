import { Head, Link } from '@inertiajs/react';
import { Printer, Undo2 } from 'lucide-react';
import SalesReturnController from '@/actions/App/Http/Controllers/SalesReturnController';
import SaleReceipt from '@/components/sale-receipt';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { create } from '@/routes/sales';
import type { SaleReceipt as SaleReceiptData } from '@/types';

/** An executed sale: its receipt, ready to print, and the way to return from it. */
export default function SaleShow({ receipt }: { receipt: SaleReceiptData }) {
    const { can } = usePermissions();

    return (
        <>
            <Head title={`Sotuv №${receipt.number}`} />
            <div className="flex flex-1 flex-col items-center gap-6 p-4">
                <div className="w-full max-w-sm rounded-xl border p-6">
                    <SaleReceipt receipt={receipt} />
                </div>
                <div className="flex gap-2">
                    <Button type="button" onClick={() => window.print()}>
                        <Printer /> Chop etish
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={create()}>Yangi sotuv</Link>
                    </Button>
                    {can('sales-return.draft') && (
                        <Button variant="outline" asChild>
                            <Link
                                href={SalesReturnController.create({
                                    query: { source: receipt.id },
                                })}
                            >
                                <Undo2 /> Qaytarish yaratish
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

SaleShow.layout = {
    breadcrumbs: [{ title: 'Sotuv', href: create() }],
};
