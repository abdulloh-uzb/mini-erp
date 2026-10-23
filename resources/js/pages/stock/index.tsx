import { Head, router } from '@inertiajs/react';
import {
    EmptyState,
    FilteredEmptyState,
    ListPageHeader,
    ListSearch,
    ListTable,
    ListToolbar,
} from '@/components/list-page';
import { formatMoney, formatQuantity } from '@/lib/format';
import { index } from '@/routes/stock';
import type { PickerOption } from '@/types';

type StockRow = {
    location_id: number;
    location_name: string;
    product_id: number;
    product_name: string;
    pieces_per_block: number;
    quantity: number;
    average_cost?: string;
    value?: string;
};

type Props = {
    rows: StockRow[];
    /** Present only for users allowed to see costs. */
    total_value?: string;
    filters: {
        search: string;
        location_id: number | null;
    };
    locations: PickerOption[];
};

export default function StockIndex({
    rows,
    total_value,
    filters,
    locations,
}: Props) {
    const showCosts = total_value !== undefined;
    const isFiltered = filters.search !== '' || filters.location_id !== null;

    return (
        <>
            <Head title="Qoldiqlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Qoldiqlar"
                    description="Hozirgi qoldiqlar. Ichki omborlar o'z omboriga qo'shib ko'rsatiladi."
                />

                <ListToolbar>
                    <ListSearch
                        value={filters.search}
                        placeholder="Mahsulot nomi yoki shtrix-kod"
                    />
                    <select
                        value={filters.location_id ?? ''}
                        onChange={(event) =>
                            router.get(
                                index.url({
                                    query: {
                                        search: filters.search || null,
                                        location_id: event.target.value || null,
                                    },
                                }),
                                {},
                                { preserveScroll: true, replace: true },
                            )
                        }
                        aria-label="Joy"
                        className="h-9 w-56 rounded-md border border-input bg-transparent px-2 text-sm"
                    >
                        <option value="">Barcha joylar</option>
                        {locations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.name}
                            </option>
                        ))}
                    </select>
                </ListToolbar>

                {rows.length === 0 ? (
                    isFiltered ? (
                        <FilteredEmptyState
                            message="Mos qoldiq topilmadi"
                            clearHref={index.url()}
                        />
                    ) : (
                        <EmptyState message="Hali qoldiq yo'q" />
                    )
                ) : (
                    <>
                        <ListTable
                            columns={[
                                'Joy',
                                'Mahsulot',
                                'Qoldiq',
                                ...(showCosts
                                    ? ["O'rtacha tannarx", 'Qiymati']
                                    : []),
                            ]}
                        >
                            {rows.map((row) => (
                                <tr
                                    key={`${row.location_id}-${row.product_id}`}
                                    className="border-t"
                                >
                                    <td className="px-4 py-3">
                                        {row.location_name}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {row.product_name}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {formatQuantity(
                                            row.quantity,
                                            row.pieces_per_block,
                                        )}
                                    </td>
                                    {showCosts && (
                                        <>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatMoney(row.average_cost)}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatMoney(row.value)}
                                            </td>
                                        </>
                                    )}
                                </tr>
                            ))}
                        </ListTable>
                        {showCosts && (
                            <p className="text-right text-sm">
                                Jami qiymat:{' '}
                                <span className="font-semibold">
                                    {formatMoney(total_value)}
                                </span>
                            </p>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

StockIndex.layout = {
    breadcrumbs: [
        {
            title: 'Qoldiqlar',
            href: index(),
        },
    ],
};
