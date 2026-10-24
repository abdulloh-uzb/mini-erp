import { Head, router } from '@inertiajs/react';
import {
    EmptyState,
    FilteredEmptyState,
    ListPageHeader,
    ListSearch,
    ListTable,
    ListTabs,
} from '@/components/list-page';
import { formatMoney, splitQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/stock';
import type { PickerOption } from '@/types';

type StockRow = {
    location_id: number;
    location_name: string;
    product_id: number;
    product_name: string;
    pieces_per_block: number;
    quantity: number;
    level: StockLevel;
    average_cost?: string;
    value?: string;
};

type Props = {
    rows: StockRow[];
    /** How many rows each level has, whichever level tab is open. */
    level_counts: Record<StockLevel, number>;
    /** Present only for users allowed to see costs. */
    total_value?: string;
    filters: {
        search: string;
        location_id: number | null;
        level: StockLevel | null;
    };
    locations: PickerOption[];
};

export default function StockIndex({
    rows,
    level_counts,
    total_value,
    filters,
    locations,
}: Props) {
    const showCosts = total_value !== undefined;
    const isFiltered =
        filters.search !== '' ||
        filters.location_id !== null ||
        filters.level !== null;
    const allCount = Object.values(level_counts).reduce(
        (sum, count) => sum + count,
        0,
    );
    const levelHref = (level: StockLevel | null) =>
        index.url({
            query: {
                search: filters.search || null,
                location_id: filters.location_id,
                level,
            },
        });

    return (
        <>
            <Head title="Qoldiqlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Qoldiqlar"
                    description="Hozirgi qoldiqlar. Ichki omborlar o'z omboriga qo'shib ko'rsatiladi."
                />

                <div className="flex flex-wrap items-center gap-3">
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
                                        level: filters.level,
                                    },
                                }),
                                {},
                                { preserveScroll: true, replace: true },
                            )
                        }
                        aria-label="Ombor"
                        className="h-9 w-56 rounded-md border border-input bg-transparent px-2 text-sm"
                    >
                        <option value="">Barcha omborlar</option>
                        {locations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.name}
                            </option>
                        ))}
                    </select>
                    <ListTabs
                        tabs={[
                            {
                                label: `Barchasi (${allCount})`,
                                href: levelHref(null),
                                active: filters.level === null,
                            },
                            ...(['low', 'medium', 'out'] as const).map(
                                (level) => ({
                                    label: `${stockLevels[level].tab} (${level_counts[level]})`,
                                    href: levelHref(level),
                                    active: filters.level === level,
                                }),
                            ),
                        ]}
                    />
                </div>

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
                    <ListTable
                        columns={[
                            'Mahsulot',
                            'Ombor',
                            <div className="text-right">Qoldiq</div>,
                            ...(showCosts
                                ? [
                                      <div className="text-right">
                                          O'rtacha tannarx
                                      </div>,
                                      <div className="text-right">
                                          Qoldiq qiymati
                                      </div>,
                                  ]
                                : []),
                        ]}
                        footer={
                            showCosts && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-4 text-right"
                                    >
                                        <span className="mr-3 text-base text-muted-foreground">
                                            Jami qoldiq qiymati:
                                        </span>
                                        <span className="text-2xl font-semibold tabular-nums">
                                            {formatMoney(total_value)}
                                        </span>
                                    </td>
                                </tr>
                            )
                        }
                    >
                        {rows.map((row) => (
                            <tr
                                key={`${row.location_id}-${row.product_id}`}
                                className="border-t"
                            >
                                <td className="px-4 py-3 font-medium">
                                    {row.product_name}
                                </td>
                                <td className="px-4 py-3">
                                    {row.location_name}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    <div className="flex items-start justify-end gap-2">
                                        <StockLevelBadge level={row.level} />
                                        <div>
                                            <StockQuantity
                                                pieces={row.quantity}
                                                piecesPerBlock={
                                                    row.pieces_per_block
                                                }
                                            />
                                        </div>
                                    </div>
                                </td>
                                {showCosts && (
                                    <>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatMoney(row.average_cost)}
                                            <span className="text-muted-foreground">
                                                {' '}
                                                / dona
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatMoney(row.value)}
                                        </td>
                                    </>
                                )}
                            </tr>
                        ))}
                    </ListTable>
                )}
            </div>
        </>
    );
}

type StockLevel = 'out' | 'low' | 'medium' | 'enough';

/** Each stock level's tab label, badge text and colours (thresholds: app/Enums/StockLevel.php). */
const stockLevels: Record<
    StockLevel,
    { tab: string; badge: string; className: string }
> = {
    out: {
        tab: 'Tugagan',
        badge: 'Tugagan',
        className: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    },
    low: {
        tab: 'Kam qolgan',
        badge: 'Kam',
        className:
            'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
    },
    medium: {
        tab: "O'rtacha",
        badge: "O'rtacha",
        className:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
    },
    enough: {
        tab: 'Yetarli',
        badge: 'Yetarli',
        className:
            'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    },
};

function StockLevelBadge({ level }: { level: StockLevel }) {
    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                stockLevels[level].className,
            )}
        >
            {stockLevels[level].badge}
        </span>
    );
}

/**
 * The balance in pieces, with its blocks underneath in small grey text for products sold
 * by the block.
 */
function StockQuantity({
    pieces,
    piecesPerBlock,
}: {
    pieces: number;
    piecesPerBlock: number;
}) {
    const split = splitQuantity(pieces, piecesPerBlock);

    return (
        <>
            <div className="font-medium">{pieces} dona</div>
            {piecesPerBlock > 1 && split.blocks > 0 && (
                <div className="text-xs text-muted-foreground">
                    {split.blocks} blok
                    {split.pieces > 0 && ` + ${split.pieces} dona`}
                </div>
            )}
        </>
    );
}

StockIndex.layout = {
    breadcrumbs: [],
};
