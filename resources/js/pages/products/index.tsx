import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import InputError from '@/components/input-error';
import {
    EmptyState,
    FilteredEmptyState,
    ListPageHeader,
    ListPagination,
    ListSearch,
    ListTable,
    ListTabs,
    ListToolbar,
} from '@/components/list-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { index } from '@/routes/products';
import type { Paginated } from '@/types';

type Product = {
    id: number;
    name: string;
    barcode: string | null;
    pieces_per_block: number;
    archived: boolean;
};

type Props = {
    products: Paginated<Product>;
    filters: {
        search: string;
        archived: boolean;
    };
};

export default function ProductsIndex({ products, filters }: Props) {
    const { can } = usePermissions();
    const canManage = can('products.manage');
    const isFiltered = filters.search !== '' || filters.archived;

    return (
        <>
            <Head title="Mahsulotlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Mahsulotlar"
                    description="Do'kondagi mahsulotlar. Arxivlangan mahsulot yangi hujjatlarga qo'shilmaydi, tarixi saqlanadi."
                    action={canManage && <ProductDialog />}
                />

                <ListToolbar>
                    <ListSearch
                        value={filters.search}
                        placeholder="Nomi yoki shtrix-kod"
                    />
                    <ListTabs
                        tabs={[
                            {
                                label: 'Faol',
                                href: index.url({
                                    query: { search: filters.search || null },
                                }),
                                active: !filters.archived,
                            },
                            {
                                label: 'Arxivlangan',
                                href: index.url({
                                    query: {
                                        search: filters.search || null,
                                        archived: 1,
                                    },
                                }),
                                active: filters.archived,
                            },
                        ]}
                    />
                </ListToolbar>

                {products.data.length === 0 ? (
                    isFiltered ? (
                        <FilteredEmptyState
                            message="Mos mahsulot topilmadi"
                            clearHref={index.url()}
                        />
                    ) : (
                        <EmptyState
                            message="Hali mahsulot yo'q"
                            action={canManage && <ProductDialog />}
                        />
                    )
                ) : (
                    <>
                        <ListTable
                            columns={[
                                'Nomi',
                                'Shtrix-kod',
                                'Blokdagi dona',
                                canManage ? '' : null,
                            ].filter((column) => column !== null)}
                        >
                            {products.data.map((product) => (
                                <ProductRow
                                    key={product.id}
                                    product={product}
                                    canManage={canManage}
                                />
                            ))}
                        </ListTable>
                        <ListPagination paginator={products} />
                    </>
                )}
            </div>
        </>
    );
}

function ProductRow({
    product,
    canManage,
}: {
    product: Product;
    canManage: boolean;
}) {
    return (
        <tr className="border-t">
            <td className="px-4 py-3 font-medium">
                {product.name}
                {product.archived && (
                    <Badge variant="secondary" className="ml-2">
                        Arxivlangan
                    </Badge>
                )}
            </td>
            <td className="px-4 py-3 tabular-nums">
                {product.barcode ?? (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>
            <td className="px-4 py-3 tabular-nums">
                {product.pieces_per_block}
            </td>
            {canManage && (
                <td className="px-4 py-3">
                    <div className="flex justify-end gap-2">
                        <ProductDialog product={product} />
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={
                                    product.archived
                                        ? ProductController.unarchive(
                                              product.id,
                                          )
                                        : ProductController.archive(product.id)
                                }
                                as="button"
                                preserveScroll
                            >
                                {product.archived
                                    ? 'Arxivdan chiqarish'
                                    : 'Arxivlash'}
                            </Link>
                        </Button>
                    </div>
                </td>
            )}
        </tr>
    );
}

/**
 * Creates a product, or edits the given one.
 */
function ProductDialog({ product }: { product?: Product }) {
    const [open, setOpen] = useState(false);
    const idPrefix = product ? `product-${product.id}` : 'product-new';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {product ? (
                    <Button variant="outline" size="sm">
                        <Pencil />
                        Tahrirlash
                    </Button>
                ) : (
                    <Button>
                        <Plus />
                        Mahsulot qo'shish
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {product ? 'Mahsulotni tahrirlash' : "Mahsulot qo'shish"}
                </DialogTitle>
                <DialogDescription>
                    Qoldiq donada hisoblanadi; blok faqat kiritish va ko'rsatish
                    uchun.
                </DialogDescription>
                <Form
                    {...(product
                        ? ProductController.update.form(product.id)
                        : ProductController.store.form())}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={!product}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${idPrefix}-name`}>Nomi</Label>
                                <Input
                                    id={`${idPrefix}-name`}
                                    name="name"
                                    defaultValue={product?.name}
                                    required
                                    autoFocus
                                    placeholder="Coca-Cola 1.5 L"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${idPrefix}-barcode`}>
                                    Shtrix-kod (ixtiyoriy)
                                </Label>
                                <Input
                                    id={`${idPrefix}-barcode`}
                                    name="barcode"
                                    defaultValue={product?.barcode ?? ''}
                                    autoComplete="off"
                                />
                                <InputError message={errors.barcode} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${idPrefix}-pieces`}>
                                    Blokdagi dona soni
                                </Label>
                                <Input
                                    id={`${idPrefix}-pieces`}
                                    name="pieces_per_block"
                                    type="number"
                                    min={1}
                                    step={1}
                                    defaultValue={
                                        product?.pieces_per_block ?? 1
                                    }
                                    required
                                />
                                <InputError message={errors.pieces_per_block} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Bekor qilish
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Saqlash
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Mahsulotlar',
            href: index(),
        },
    ],
};
