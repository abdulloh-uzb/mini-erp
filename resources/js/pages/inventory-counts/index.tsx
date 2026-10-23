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
import { create, index, show } from '@/routes/inventory-counts';
import type { Paginated, PickerOption } from '@/types';

type Props = {
    counts: Paginated<DocumentListRow>;
    filters: DocumentListFilterValues;
    locations: PickerOption[];
    authors: PickerOption[];
};

export default function InventoryCountsIndex({
    counts,
    filters,
    locations,
    authors,
}: Props) {
    const filtered = Object.values(filters).some(
        (value) => value !== null && value !== '',
    );
    const newCount = (
        <Button asChild>
            <Link href={create()}>
                <Plus />
                Yangi inventarizatsiya
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Inventarizatsiyalar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Inventarizatsiyalar"
                    description="Joydagi tovarlarni sanash. Xodim sanab saqlaydi, egasi kutilgan va sanalganni solishtirib bajaradi."
                    action={newCount}
                />

                <DocumentListFilters
                    filters={filters}
                    locations={locations}
                    authors={authors}
                />

                {counts.data.length === 0 ? (
                    filtered ? (
                        <FilteredEmptyState
                            message="Filtrga mos hujjat topilmadi"
                            clearHref={index.url()}
                        />
                    ) : (
                        <EmptyState
                            message="Hali inventarizatsiya yo'q"
                            action={newCount}
                        />
                    )
                ) : (
                    <>
                        <DocumentListTable
                            rows={counts.data}
                            showCounterparty={false}
                            showTotal={false}
                            onOpen={(row) => router.visit(show(row.id))}
                        />
                        <ListPagination paginator={counts} />
                    </>
                )}
            </div>
        </>
    );
}

InventoryCountsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Inventarizatsiyalar',
            href: index(),
        },
    ],
};
