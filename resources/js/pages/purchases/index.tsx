import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    DocumentListFilters,
    DocumentListTable,
} from '@/components/document-list';
import type {
    DocumentListFilterValues,
    DocumentListRow,
} from '@/components/document-list';
import {
    EmptyState,
    FilteredEmptyState,
    ListPageHeader,
    ListPagination,
} from '@/components/list-page';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { create, index, show } from '@/routes/purchases';
import type { Paginated, PickerOption } from '@/types';

type Props = {
    purchases: Paginated<DocumentListRow>;
    filters: DocumentListFilterValues;
    locations: PickerOption[];
    counterparties: PickerOption[];
    authors: PickerOption[];
};

export default function PurchasesIndex({
    purchases,
    filters,
    locations,
    counterparties,
    authors,
}: Props) {
    const { can } = usePermissions();
    const filtered = Object.values(filters).some(
        (value) => value !== null && value !== '',
    );
    const newPurchase = (
        <Button asChild>
            <Link href={create()}>
                <Plus />
                Yangi xarid
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Xaridlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Xaridlar"
                    description="Yetkazib beruvchidan kelgan tovarlar. Xodim qoralama saqlaydi, egasi narx va to'lovni kiritib bajaradi."
                    action={newPurchase}
                />

                <DocumentListFilters
                    filters={filters}
                    locations={locations}
                    counterparties={counterparties}
                    authors={authors}
                />

                {purchases.data.length === 0 ? (
                    filtered ? (
                        <FilteredEmptyState
                            message="Filtrga mos hujjat topilmadi"
                            clearHref={index.url()}
                        />
                    ) : (
                        <EmptyState
                            message="Hali xarid yo'q"
                            action={newPurchase}
                        />
                    )
                ) : (
                    <>
                        <DocumentListTable
                            rows={purchases.data}
                            showTotal={can('costs.view')}
                            onOpen={(row) => router.visit(show(row.id))}
                        />
                        <ListPagination paginator={purchases} />
                    </>
                )}
            </div>
        </>
    );
}

PurchasesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Xaridlar',
            href: index(),
        },
    ],
};
