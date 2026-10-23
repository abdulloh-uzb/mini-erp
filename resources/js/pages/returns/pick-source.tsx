import { Head, setLayoutProps, router } from '@inertiajs/react';
import { DocumentListTable } from '@/components/document-list';
import type { DocumentListRow } from '@/components/document-list';
import {
    EmptyState,
    ListPageHeader,
    ListPagination,
    ListSearch,
} from '@/components/list-page';
import { returnKinds } from '@/lib/returns';
import type { ReturnKind } from '@/lib/returns';
import type { Paginated } from '@/types';

type Props = {
    kind: ReturnKind;
    sources: Paginated<DocumentListRow>;
    search: string;
};

/** "Yangi qaytarish" from the list: pick the executed sale or purchase to return from. */
export default function PickReturnSource({ kind, sources, search }: Props) {
    const config = returnKinds[kind];
    setLayoutProps({
        breadcrumbs: [{ title: config.title, href: config.controller.index() }],
    });

    return (
        <>
            <Head title="Yangi qaytarish" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Yangi qaytarish"
                    description={`Qaysi hujjatdan qaytarilishini tanlang: ${config.sources.toLowerCase()}.`}
                />
                <ListSearch
                    value={search}
                    placeholder="Raqam yoki kontragent bo'yicha qidirish"
                />
                {sources.data.length === 0 ? (
                    <EmptyState message="Mos hujjat topilmadi" />
                ) : (
                    <>
                        <DocumentListTable
                            rows={sources.data}
                            onOpen={(row) =>
                                router.visit(
                                    config.controller.create({
                                        query: { source: row.id },
                                    }),
                                )
                            }
                        />
                        <ListPagination paginator={sources} />
                    </>
                )}
            </div>
        </>
    );
}
