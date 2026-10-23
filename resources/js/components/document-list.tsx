import { router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { DocumentStatusBadge } from '@/components/document-form';
import { ListSearch, ListTable, ListTabs } from '@/components/list-page';
import { Input } from '@/components/ui/input';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { DocumentStatus, PickerOption } from '@/types';

/**
 * The shared document list (story 79): Hammasi / Qoralama / Bajarilgan tabs, a date range,
 * location, counterparty and author filters, and a search by number or counterparty. The
 * server applies the same filters with `Document::filtered()`.
 */
export type DocumentListFilterValues = {
    status: DocumentStatus | null;
    from: string | null;
    to: string | null;
    location_id: number | null;
    counterparty_id: number | null;
    author_id: number | null;
    search: string;
};

export type DocumentListRow = {
    id: number;
    number: number;
    status: DocumentStatus;
    date: string | null;
    location: string | null;
    counterparty: string | null;
    total: string | null;
    author: string;
};

type FilterKey = Exclude<keyof DocumentListFilterValues, 'search'>;

function useFilterUrl() {
    const { url } = usePage();

    return (changes: Partial<Record<FilterKey, string | number | null>>) => {
        const target = new URL(url, window.location.origin);
        target.searchParams.delete('page');

        for (const [key, value] of Object.entries(changes)) {
            if (value === null || value === '') {
                target.searchParams.delete(key);
            } else {
                target.searchParams.set(key, String(value));
            }
        }

        return target.pathname + target.search;
    };
}

export function DocumentListFilters({
    filters,
    locations,
    counterparties,
    authors,
}: {
    filters: DocumentListFilterValues;
    locations: PickerOption[];
    counterparties?: PickerOption[];
    authors: PickerOption[];
}) {
    const filterUrl = useFilterUrl();
    const apply = (changes: Partial<Record<FilterKey, string | null>>) =>
        router.get(
            filterUrl(changes),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <ListSearch
                    value={filters.search}
                    placeholder="№ yoki kontragent"
                />
                <ListTabs
                    tabs={[
                        { label: 'Hammasi', status: null },
                        { label: 'Qoralama', status: 'draft' },
                        { label: 'Bajarilgan', status: 'executed' },
                    ].map((tab) => ({
                        label: tab.label,
                        href: filterUrl({ status: tab.status }),
                        active: filters.status === tab.status,
                    }))}
                />
            </div>
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
                    label="Joy"
                    allLabel="Barcha joylar"
                    value={filters.location_id}
                    options={locations}
                    onChange={(value) => apply({ location_id: value })}
                />
                {counterparties && (
                    <FilterSelect
                        label="Kontragent"
                        allLabel="Barcha kontragentlar"
                        value={filters.counterparty_id}
                        options={counterparties}
                        onChange={(value) => apply({ counterparty_id: value })}
                    />
                )}
                <FilterSelect
                    label="Muallif"
                    allLabel="Barcha mualliflar"
                    value={filters.author_id}
                    options={authors}
                    onChange={(value) => apply({ author_id: value })}
                />
            </div>
        </div>
    );
}

function FilterField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="flex items-center gap-2 text-sm text-muted-foreground">
            {label}
            {children}
        </label>
    );
}

function FilterSelect({
    label,
    allLabel,
    value,
    options,
    onChange,
}: {
    label: string;
    allLabel: string;
    value: number | null;
    options: PickerOption[];
    onChange: (value: string | null) => void;
}) {
    return (
        <select
            value={value ?? ''}
            onChange={(event) => onChange(event.target.value || null)}
            aria-label={label}
            className="h-9 w-48 rounded-md border border-input bg-transparent px-2 text-sm"
        >
            <option value="">{allLabel}</option>
            {options.map((option) => (
                <option key={option.id} value={option.id}>
                    {option.name}
                </option>
            ))}
        </select>
    );
}

/**
 * Columns: №, Sana (executed-at, or created-at for drafts), Joy, Kontragent, Summa, Holat,
 * Muallif. A row click opens the document.
 */
export function DocumentListTable({
    rows,
    onOpen,
    showTotal = true,
}: {
    rows: DocumentListRow[];
    onOpen: (row: DocumentListRow) => void;
    showTotal?: boolean;
}) {
    return (
        <ListTable
            columns={[
                '№',
                'Sana',
                'Joy',
                'Kontragent',
                ...(showTotal ? ['Summa'] : []),
                'Holat',
                'Muallif',
            ]}
        >
            {rows.map((row) => (
                <tr
                    key={row.id}
                    className="cursor-pointer border-t hover:bg-muted/40"
                    onClick={() => onOpen(row)}
                >
                    <td className="px-4 py-3 font-medium tabular-nums">
                        {row.number}
                    </td>
                    <td className="px-4 py-3 tabular-nums">
                        {formatDateTime(row.date)}
                    </td>
                    <td className="px-4 py-3">{row.location ?? '—'}</td>
                    <td className="px-4 py-3">{row.counterparty ?? '—'}</td>
                    {showTotal && (
                        <td className="px-4 py-3 tabular-nums">
                            {row.total === null ? '—' : formatMoney(row.total)}
                        </td>
                    )}
                    <td className="px-4 py-3">
                        <DocumentStatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3">{row.author}</td>
                </tr>
            ))}
        </ListTable>
    );
}
