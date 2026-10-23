import { Head, router } from '@inertiajs/react';
import { Minus, Plus, Undo2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import CashOperationController from '@/actions/App/Http/Controllers/CashOperationController';
import SupplierDebtController from '@/actions/App/Http/Controllers/SupplierDebtController';
import {
    CashOperationDialog,
    selectClassName,
    SignedAmount,
} from '@/components/cash-form';
import { ConfirmDialog } from '@/components/document-form';
import {
    FilterField,
    FilterSelect,
    useFilterUrl,
} from '@/components/document-list';
import InputError from '@/components/input-error';
import {
    EmptyState,
    FilteredEmptyState,
    ListPageHeader,
    ListPagination,
    ListTable,
} from '@/components/list-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDateTime, formatMoney } from '@/lib/format';
import { index } from '@/routes/cash';
import type { EnumOption, Paginated, PickerOption } from '@/types';

/** Balance per payment method value, plus `total`. */
type Balances = Record<string, string> & { total: string };

type Operation = {
    id: number;
    date: string | null;
    type: string;
    direction: 'in' | 'out';
    payment_method: string;
    amount: string;
    category: string | null;
    counterparty: string | null;
    note: string | null;
    author: string;
    is_reversal: boolean;
    correctable: boolean;
};

type Category = {
    id: number;
    name: string;
    requires_note: boolean;
};

/** A supplier the store owes, offered for "Yetkazib beruvchiga to'lov" (owner only). */
type OwedSupplier = {
    id: number;
    name: string;
    balance: string;
};

type Filters = {
    from: string | null;
    to: string | null;
    type: string | null;
    payment_method: string | null;
    author_id: number | null;
    counterparty_id: number | null;
};

type FilterKey = keyof Filters;

type Props = {
    balances?: Balances;
    operations: Paginated<Operation>;
    filters: Filters;
    types: EnumOption[];
    methods: EnumOption[];
    authors: PickerOption[];
    counterparties: PickerOption[];
    categories: { expense: Category[]; income: Category[] };
    suppliers?: OwedSupplier[];
};

export default function CashIndex({
    balances,
    operations,
    filters,
    types,
    methods,
    authors,
    counterparties,
    categories,
    suppliers,
}: Props) {
    const { can } = usePermissions();
    const canRecord = can('expense.create');
    const canCorrect = can('cash-operation.reverse');
    const filtered = Object.values(filters).some((value) => value !== null);

    const actions = canRecord && (
        <div className="flex gap-2">
            {categories.income.length > 0 && (
                <CategoryOperationDialog
                    title="Kirim"
                    categoryLabel="Kirim turi"
                    description="Kassaga tushgan pul."
                    categories={categories.income}
                    methods={methods}
                    trigger={
                        <Button variant="outline">
                            <Plus />
                            Kirim
                        </Button>
                    }
                />
            )}
            <CategoryOperationDialog
                title="Chiqim"
                categoryLabel="Xarajat turi"
                description="Kassadan chiqqan pul: xarajat, egasi olgan pul yoki yetkazib beruvchiga to'lov."
                categories={categories.expense}
                suppliers={suppliers}
                methods={methods}
                trigger={
                    <Button>
                        <Minus />
                        Chiqim
                    </Button>
                }
            />
        </div>
    );

    return (
        <>
            <Head title="Kassa" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Kassa"
                    description="Kassadagi har bir pul harakati. Operatsiyalar o'zgarmaydi, xato teskari operatsiya bilan tuzatiladi."
                    action={actions}
                />

                {balances && (
                    <BalanceStrip balances={balances} methods={methods} />
                )}

                <CashFilters
                    filters={filters}
                    types={types}
                    methods={methods}
                    authors={authors}
                    counterparties={counterparties}
                />

                {operations.data.length === 0 ? (
                    filtered ? (
                        <FilteredEmptyState
                            message="Mos operatsiya topilmadi"
                            clearHref={index.url()}
                        />
                    ) : (
                        <EmptyState
                            message="Hali kassa operatsiyasi yo'q"
                            action={actions}
                        />
                    )
                ) : (
                    <>
                        <ListTable
                            columns={[
                                'Sana',
                                'Turi',
                                "To'lov usuli",
                                'Summa',
                                'Kontragent',
                                'Izoh',
                                'Muallif',
                                ...(canCorrect ? [''] : []),
                            ]}
                        >
                            {operations.data.map((operation) => (
                                <tr key={operation.id} className="border-t">
                                    <td className="px-4 py-3 tabular-nums">
                                        {formatDateTime(operation.date)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {operation.category ??
                                                operation.type}
                                        </div>
                                        {(operation.category ||
                                            operation.is_reversal) && (
                                            <div className="text-xs text-muted-foreground">
                                                {operation.type}
                                                {operation.is_reversal &&
                                                    ' · teskari operatsiya'}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {operation.payment_method}
                                    </td>
                                    <td className="px-4 py-3">
                                        <SignedAmount
                                            direction={operation.direction}
                                        >
                                            {formatMoney(operation.amount)}
                                        </SignedAmount>
                                    </td>
                                    <td className="px-4 py-3">
                                        {operation.counterparty ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {operation.note}
                                    </td>
                                    <td className="px-4 py-3">
                                        {operation.author}
                                    </td>
                                    {canCorrect && (
                                        <td className="px-4 py-3">
                                            {operation.correctable && (
                                                <CorrectOperation
                                                    operation={operation}
                                                />
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </ListTable>
                        <ListPagination paginator={operations} />
                    </>
                )}
            </div>
        </>
    );
}

/** Naqd | Karta | Bank o'tkazmasi | Jami — owner only (D7); employees never receive it. */
function BalanceStrip({
    balances,
    methods,
}: {
    balances: Balances;
    methods: EnumOption[];
}) {
    const cells = [
        ...methods.map((method) => ({
            label: method.name,
            amount: balances[method.id] ?? '0',
            total: false,
        })),
        { label: 'Jami', amount: balances.total, total: true },
    ];

    return (
        <div
            className="grid overflow-hidden rounded-xl border"
            style={{
                gridTemplateColumns: `repeat(${cells.length}, minmax(0, 1fr))`,
            }}
        >
            {cells.map((cell) => (
                <div
                    key={cell.label}
                    className={
                        cell.total
                            ? 'bg-muted/50 p-4'
                            : 'border-r p-4 last:border-r-0'
                    }
                >
                    <div className="text-sm text-muted-foreground">
                        {cell.label}
                    </div>
                    <div className="mt-1 text-xl font-semibold tabular-nums">
                        {formatMoney(cell.amount)}
                    </div>
                </div>
            ))}
        </div>
    );
}

function CashFilters({
    filters,
    types,
    methods,
    authors,
    counterparties,
}: {
    filters: Filters;
    types: EnumOption[];
    methods: EnumOption[];
    authors: PickerOption[];
    counterparties: PickerOption[];
}) {
    const filterUrl = useFilterUrl<FilterKey>();
    const apply = (changes: Partial<Record<FilterKey, string | null>>) =>
        router.get(
            filterUrl(changes),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <div className="flex flex-wrap items-center gap-3">
            <FilterField label="Dan">
                <Input
                    type="date"
                    value={filters.from ?? ''}
                    onChange={(event) =>
                        apply({ from: event.target.value || null })
                    }
                    className="w-40"
                />
            </FilterField>
            <FilterField label="Gacha">
                <Input
                    type="date"
                    value={filters.to ?? ''}
                    onChange={(event) =>
                        apply({ to: event.target.value || null })
                    }
                    className="w-40"
                />
            </FilterField>
            <FilterSelect
                label="Turi"
                allLabel="Barcha turlar"
                value={filters.type}
                options={types}
                onChange={(value) => apply({ type: value })}
            />
            <FilterSelect
                label="To'lov usuli"
                allLabel="Barcha usullar"
                value={filters.payment_method}
                options={methods}
                onChange={(value) => apply({ payment_method: value })}
            />
            <FilterSelect
                label="Muallif"
                allLabel="Barcha mualliflar"
                value={filters.author_id}
                options={authors}
                onChange={(value) => apply({ author_id: value })}
            />
            <FilterSelect
                label="Kontragent"
                allLabel="Barcha kontragentlar"
                value={filters.counterparty_id}
                options={counterparties}
                onChange={(value) => apply({ counterparty_id: value })}
            />
        </div>
    );
}

/**
 * + Kirim / + Chiqim: a category from the user's allowed list, amount, method, note. When
 * suppliers are given, Chiqim also offers "Yetkazib beruvchiga to'lov", which pays a supplier
 * through the supplier-debt form instead.
 */
function CategoryOperationDialog({
    title,
    categoryLabel,
    description,
    categories,
    suppliers = [],
    methods,
    trigger,
}: {
    title: string;
    categoryLabel: string;
    description: string;
    categories: Category[];
    suppliers?: OwedSupplier[];
    methods: EnumOption[];
    trigger: ReactNode;
}) {
    const [choice, setChoice] = useState<number | 'supplier' | null>(
        categories[0]?.id ?? null,
    );
    const [supplierId, setSupplierId] = useState(suppliers[0]?.id ?? null);
    const paysSupplier = choice === 'supplier';
    const category = categories.find((item) => item.id === choice);
    const supplier = suppliers.find((item) => item.id === supplierId);
    const id = `cash-${title.toLowerCase()}-category`;

    return (
        <CashOperationDialog
            title={title}
            description={description}
            trigger={trigger}
            form={
                paysSupplier
                    ? SupplierDebtController.pay.form()
                    : CashOperationController.store.form()
            }
            methods={methods}
            noteRequired={category?.requires_note ?? false}
        >
            {(errors) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={id}>{categoryLabel}</Label>
                        <select
                            id={id}
                            name={
                                paysSupplier ? undefined : 'expense_category_id'
                            }
                            value={choice ?? ''}
                            onChange={(event) =>
                                setChoice(
                                    event.target.value === 'supplier'
                                        ? 'supplier'
                                        : Number(event.target.value),
                                )
                            }
                            className={selectClassName}
                        >
                            {categories.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                            {suppliers.length > 0 && (
                                <option value="supplier">
                                    Yetkazib beruvchiga to'lov
                                </option>
                            )}
                        </select>
                        <InputError message={errors.expense_category_id} />
                    </div>
                    {paysSupplier && (
                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-supplier`}>
                                Yetkazib beruvchi
                            </Label>
                            <select
                                id={`${id}-supplier`}
                                name="counterparty_id"
                                value={supplierId ?? ''}
                                onChange={(event) =>
                                    setSupplierId(Number(event.target.value))
                                }
                                className={selectClassName}
                            >
                                {suppliers.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                            {supplier && (
                                <p className="text-sm text-muted-foreground">
                                    Qarz: {formatMoney(supplier.balance)}
                                </p>
                            )}
                            <InputError message={errors.counterparty_id} />
                        </div>
                    )}
                </>
            )}
        </CashOperationDialog>
    );
}

function CorrectOperation({ operation }: { operation: Operation }) {
    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline" size="sm">
                    <Undo2 />
                    Tuzatish
                </Button>
            }
            title="Teskari operatsiya"
            description={`${operation.category ?? operation.type}, ${formatMoney(operation.amount)} teskarisi bilan tuzatiladi: pul qarama-qarshi tomonga yoziladi. Davom etasizmi?`}
            confirmLabel="Tuzatish"
            onConfirm={() =>
                router.post(
                    CashOperationController.reverse.url(operation.id),
                    {},
                    {
                        preserveScroll: true,
                        onError: (errors) =>
                            Object.values(errors).forEach((message) =>
                                toast.error(message),
                            ),
                    },
                )
            }
        />
    );
}

CashIndex.layout = {
    breadcrumbs: [
        {
            title: 'Kassa',
            href: index(),
        },
    ],
};
