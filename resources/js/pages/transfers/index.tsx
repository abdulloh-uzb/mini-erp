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
import { create, index, show } from '@/routes/transfers';
import type { Paginated, PickerOption } from '@/types';

type Props = {
    transfers: Paginated<DocumentListRow>;
    filters: DocumentListFilterValues;
    locations: PickerOption[];
    authors: PickerOption[];
};

export default function TransfersIndex({
    transfers,
    filters,
    locations,
    authors,
}: Props) {
    const filtered = Object.values(filters).some(
        (value) => value !== null && value !== '',
    );
    const newTransfer = (
        <Button asChild>
            <Link href={create()}>
                <Plus />
                Yangi ko'chirish
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Transferlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Transferlar"
                    description="Tovarlarni bir joydan boshqasiga ko'chirish. Pul va tannarxga ta'sir qilmaydi."
                    action={newTransfer}
                />

                <DocumentListFilters
                    filters={filters}
                    locations={locations}
                    authors={authors}
                />

                {transfers.data.length === 0 ? (
                    filtered ? (
                        <FilteredEmptyState
                            message="Filtrga mos hujjat topilmadi"
                            clearHref={index.url()}
                        />
                    ) : (
                        <EmptyState
                            message="Hali ko'chirish yo'q"
                            action={newTransfer}
                        />
                    )
                ) : (
                    <>
                        <DocumentListTable
                            rows={transfers.data}
                            showCounterparty={false}
                            showTotal={false}
                            onOpen={(row) => router.visit(show(row.id))}
                        />
                        <ListPagination paginator={transfers} />
                    </>
                )}
            </div>
        </>
    );
}

TransfersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Transferlar',
            href: index(),
        },
    ],
};
