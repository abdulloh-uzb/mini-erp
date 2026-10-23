import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import LocationController from '@/actions/App/Http/Controllers/LocationController';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { EmptyState, ListPageHeader, ListTable } from '@/components/list-page';
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
import { index } from '@/routes/locations';

type Location = {
    id: number;
    name: string;
    archived: boolean;
};

type Warehouse = Location & {
    sub_warehouses: Location[];
};

type Props = {
    warehouses: Warehouse[];
};

export default function LocationsIndex({ warehouses }: Props) {
    const { errors } = usePage().props;

    return (
        <>
            <Head title="Omborlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Omborlar"
                    description="Omborlar va ularning ichki omborlari (bir daraja). Arxivlangan joy yangi hujjatlarda tanlanmaydi, tarixi saqlanadi."
                    action={<CreateLocationDialog />}
                />

                {errors.location && (
                    <AlertError
                        title="Amal bajarilmadi"
                        errors={[errors.location]}
                    />
                )}

                {warehouses.length === 0 ? (
                    <EmptyState
                        message="Hali ombor yo'q"
                        action={<CreateLocationDialog />}
                    />
                ) : (
                    <ListTable columns={['Nomi', '']}>
                        {warehouses.map((warehouse) => (
                            <WarehouseRows
                                key={warehouse.id}
                                warehouse={warehouse}
                            />
                        ))}
                    </ListTable>
                )}
            </div>
        </>
    );
}

function WarehouseRows({ warehouse }: { warehouse: Warehouse }) {
    return (
        <>
            <LocationRow location={warehouse}>
                {!warehouse.archived && (
                    <CreateLocationDialog parent={warehouse} />
                )}
            </LocationRow>
            {warehouse.sub_warehouses.map((subWarehouse) => (
                <LocationRow
                    key={subWarehouse.id}
                    location={subWarehouse}
                    isSubWarehouse
                />
            ))}
        </>
    );
}

function LocationRow({
    location,
    isSubWarehouse = false,
    children,
}: {
    location: Location;
    isSubWarehouse?: boolean;
    children?: ReactNode;
}) {
    return (
        <tr className="border-t">
            <td
                className={
                    isSubWarehouse
                        ? 'py-3 pr-4 pl-10 text-muted-foreground'
                        : 'px-4 py-3 font-medium'
                }
            >
                {isSubWarehouse && <span className="mr-2">└</span>}
                <span className={isSubWarehouse ? 'text-foreground' : ''}>
                    {location.name}
                </span>
                {location.archived && (
                    <Badge variant="secondary" className="ml-2">
                        Arxivlangan
                    </Badge>
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex justify-end gap-2">
                    {children}
                    <RenameLocationDialog location={location} />
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={
                                location.archived
                                    ? LocationController.unarchive(location.id)
                                    : LocationController.archive(location.id)
                            }
                            as="button"
                            preserveScroll
                        >
                            {location.archived
                                ? 'Arxivdan chiqarish'
                                : 'Arxivlash'}
                        </Link>
                    </Button>
                </div>
            </td>
        </tr>
    );
}

/**
 * Creates a warehouse, or a sub-warehouse inside the given warehouse.
 */
function CreateLocationDialog({ parent }: { parent?: Location }) {
    const [open, setOpen] = useState(false);
    const idPrefix = parent ? `location-in-${parent.id}` : 'location-new';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {parent ? (
                    <Button variant="outline" size="sm">
                        <Plus />
                        Ichki ombor
                    </Button>
                ) : (
                    <Button>
                        <Plus />
                        Ombor qo'shish
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {parent ? "Ichki ombor qo'shish" : "Ombor qo'shish"}
                </DialogTitle>
                <DialogDescription>
                    {parent
                        ? `${parent.name} ichidagi joy, masalan sovutgich yoki javon.`
                        : 'Mahsulot saqlanadigan yangi joy.'}
                </DialogDescription>
                <Form
                    {...LocationController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {parent && (
                                <input
                                    type="hidden"
                                    name="parent_id"
                                    value={parent.id}
                                />
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor={`${idPrefix}-name`}>Nomi</Label>
                                <Input
                                    id={`${idPrefix}-name`}
                                    name="name"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                                <InputError message={errors.parent_id} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Bekor qilish
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Qo'shish
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RenameLocationDialog({ location }: { location: Location }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil />
                    Nomini o'zgartirish
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Nomini o'zgartirish</DialogTitle>
                <DialogDescription>
                    Joyning yangi nomini kiriting.
                </DialogDescription>
                <Form
                    {...LocationController.update.form(location.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`location-${location.id}-name`}>
                                    Nomi
                                </Label>
                                <Input
                                    id={`location-${location.id}-name`}
                                    name="name"
                                    defaultValue={location.name}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
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

LocationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Omborlar',
            href: index(),
        },
    ],
};
