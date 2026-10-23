import { Head, setLayoutProps, Link, router } from '@inertiajs/react';
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
import { returnKinds } from '@/lib/returns';
import type { ReturnKind } from '@/lib/returns';
import type { Paginated, PickerOption } from '@/types';

type Props = {
    kind: ReturnKind;
    returns: Paginated<DocumentListRow>;
    filters: DocumentListFilterValues;
    locations: PickerOption[];
    counterparties: PickerOption[];
    authors: PickerOption[];
};

/** The list of sales returns or supplier returns. */
export default function ReturnsIndex({
    kind,
    returns,
    filters,
    locations,
    counterparties,
    authors,
}: Props) {
    const config = returnKinds[kind];
    setLayoutProps({
        breadcrumbs: [{ title: config.title, href: config.controller.index() }],
    });
    const filtered = Object.values(filters).some(
        (value) => value !== null && value !== '',
    );
    const newReturn = (
        <Button asChild>
            <Link href={config.controller.create()}>
                <Plus />
                Yangi qaytarish
            </Link>
        </Button>
    );

    return (
        <>
            <Head title={config.title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title={config.title}
                    description={config.description}
                    action={newReturn}
                />

                <DocumentListFilters
                    filters={filters}
                    locations={locations}
                    counterparties={counterparties}
                    authors={authors}
                />

                {returns.data.length === 0 ? (
                    filtered ? (
                        <FilteredEmptyState
                            message="Filtrga mos hujjat topilmadi"
                            clearHref={config.controller.index.url()}
                        />
                    ) : (
                        <EmptyState
                            message="Hali qaytarish yo'q"
                            action={newReturn}
                        />
                    )
                ) : (
                    <>
                        <DocumentListTable
                            rows={returns.data}
                            onOpen={(row) =>
                                router.visit(config.controller.show(row.id))
                            }
                        />
                        <ListPagination paginator={returns} />
                    </>
                )}
            </div>
        </>
    );
}
