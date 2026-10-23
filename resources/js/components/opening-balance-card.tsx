import { Link, router } from '@inertiajs/react';
import OpeningBalanceController from '@/actions/App/Http/Controllers/OpeningBalanceController';
import { ConfirmDialog } from '@/components/document-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show } from '@/routes/opening-balance';

/**
 * "Boshlang'ich qoldiq yo'q": start without an opening balance, which opens execution (D13).
 */
export function SkipOpeningBalance() {
    return (
        <ConfirmDialog
            trigger={
                <Button type="button" variant="ghost">
                    Boshlang'ich qoldiq yo'q
                </Button>
            }
            title="Boshlang'ich qoldiqsiz ishlash"
            description="Do'kon boshlang'ich qoldiqsiz ishlaydi va uni keyin kiritib bo'lmaydi. Saqlangan qoralama o'chiriladi. Davom etasizmi?"
            confirmLabel="Davom etish"
            onConfirm={() => router.post(OpeningBalanceController.skip.url())}
        />
    );
}

/** The owner's home card, shown until the opening balance is executed or skipped. */
export default function OpeningBalanceCard() {
    return (
        <Card className="max-w-xl border-amber-300 dark:border-amber-700">
            <CardHeader>
                <CardTitle>Boshlang'ich qoldiqni kiriting</CardTitle>
                <CardDescription>
                    Do'kondagi mavjud tovarlarni joyi va tannarxi bilan
                    kiriting. Shu ishsiz hech qanday hujjatni bajarib bo'lmaydi.
                </CardDescription>
            </CardHeader>
            <CardFooter className="gap-2">
                <Button asChild>
                    <Link href={show()}>Kiritish</Link>
                </Button>
                <SkipOpeningBalance />
            </CardFooter>
        </Card>
    );
}
