import CustomerDebtController from '@/actions/App/Http/Controllers/CustomerDebtController';
import { CashOperationDialog } from '@/components/cash-form';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import type { EnumOption } from '@/types';

/**
 * The one customer debt payment form (D5), opened from the customer card, the debts list
 * and Kassa: the customer is fixed, the amount runs up to the debt ("To'liq" fills it in).
 */
export function CustomerPaymentDialog({
    customer,
    methods,
    trigger,
}: {
    customer: { id: number; name: string; balance: string };
    methods: EnumOption[];
    trigger?: React.ReactNode;
}) {
    return (
        <CashOperationDialog
            title={`${customer.name}: qarz to'lovi`}
            description={`Qarz: ${formatMoney(customer.balance)}. Summa qarzdan oshmasligi kerak. Pul kassaga tushadi.`}
            trigger={
                trigger ?? (
                    <Button variant="outline" size="sm">
                        To'lov olish
                    </Button>
                )
            }
            form={CustomerDebtController.pay.form()}
            methods={methods}
            fullAmount={customer.balance}
        >
            {() => (
                <input
                    type="hidden"
                    name="counterparty_id"
                    value={customer.id}
                />
            )}
        </CashOperationDialog>
    );
}
