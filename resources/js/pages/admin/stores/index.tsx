import { Head, Link } from '@inertiajs/react';
import StoreController from '@/actions/App/Http/Controllers/Admin/StoreController';
import Heading from '@/components/heading';
import ResetPasswordDialog from '@/components/reset-password-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPhone } from '@/lib/phone';
import { index } from '@/routes/admin/stores';

type StoreRow = {
    id: number;
    name: string;
    owner_name: string | null;
    owner_phone: string | null;
    is_blocked: boolean;
    created_at: string | null;
};

type Props = {
    stores: StoreRow[];
};

export default function AdminStoresIndex({ stores }: Props) {
    return (
        <>
            <Head title="Do'konlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Do'konlar"
                    description="Platformadagi barcha do'konlar. Bloklangan do'kon foydalanuvchilari tizimga kira olmaydi, ma'lumotlari saqlanadi."
                />

                {stores.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        Hali do'kon yo'q
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Do'kon
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Egasi
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Telefon
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Holat
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {stores.map((store) => (
                                    <tr key={store.id} className="border-t">
                                        <td className="px-4 py-3 font-medium">
                                            {store.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {store.owner_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {store.owner_phone
                                                ? formatPhone(store.owner_phone)
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {store.is_blocked ? (
                                                <Badge variant="destructive">
                                                    Bloklangan
                                                </Badge>
                                            ) : (
                                                <Badge>Faol</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                {store.owner_name && (
                                                    <ResetPasswordDialog
                                                        form={StoreController.updateOwnerPassword.form(
                                                            store.id,
                                                        )}
                                                        title="Egasining parolini tiklash"
                                                        description={`${store.name} do'koni egasi uchun yangi parol kiriting.`}
                                                    />
                                                )}
                                                <Button
                                                    variant={
                                                        store.is_blocked
                                                            ? 'outline'
                                                            : 'destructive'
                                                    }
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            store.is_blocked
                                                                ? StoreController.unblock(
                                                                      store.id,
                                                                  )
                                                                : StoreController.block(
                                                                      store.id,
                                                                  )
                                                        }
                                                        as="button"
                                                        preserveScroll
                                                    >
                                                        {store.is_blocked
                                                            ? 'Blokdan chiqarish'
                                                            : 'Bloklash'}
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

AdminStoresIndex.layout = {
    breadcrumbs: [
        {
            title: "Do'konlar",
            href: index(),
        },
    ],
};
