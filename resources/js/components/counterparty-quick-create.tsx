import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import CounterpartyController from '@/actions/App/Http/Controllers/CounterpartyController';
import InputError from '@/components/input-error';
import PhoneInput from '@/components/phone-input';
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

export type CreatedCounterparty = {
    id: number;
    name: string;
    phone: string | null;
};

type Props = {
    /** Called with the new counterparty so the calling screen can pick it. */
    onCreated?: (counterparty: CreatedCounterparty) => void;
    /** The button that opens the modal; defaults to "Kontragent qo'shish". */
    trigger?: ReactNode;
    title?: string;
    /** Also ask for a note (the full form on the counterparty list). */
    withNote?: boolean;
};

/**
 * Quick-create a counterparty (name, phone) without leaving the current screen,
 * e.g. "+ Yangi mijoz" inside a sale. The server redirects back and flashes the
 * new counterparty, which is handed to `onCreated`.
 */
export default function CounterpartyQuickCreate({
    onCreated,
    trigger,
    title = "Kontragent qo'shish",
    withNote = false,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button>
                        <Plus />
                        Kontragent qo'shish
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>
                    Mijoz, yetkazib beruvchi yoki qarz bo'yicha tomon — hammasi
                    bitta ro'yxatda.
                </DialogDescription>
                <Form
                    {...CounterpartyController.store.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    resetOnSuccess
                    onSuccess={(page) => {
                        setOpen(false);

                        const counterparty = page.flash?.counterparty as
                            | CreatedCounterparty
                            | undefined;

                        if (counterparty) {
                            onCreated?.(counterparty);
                        }
                    }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="counterparty-new-name">
                                    Nomi
                                </Label>
                                <Input
                                    id="counterparty-new-name"
                                    name="name"
                                    required
                                    autoFocus
                                    placeholder="Ism yoki do'kon nomi"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="counterparty-new-phone">
                                    Telefon raqami (ixtiyoriy)
                                </Label>
                                <PhoneInput
                                    id="counterparty-new-phone"
                                    name="phone"
                                    autoComplete="off"
                                />
                                <InputError message={errors.phone} />
                            </div>
                            {withNote && (
                                <div className="grid gap-2">
                                    <Label htmlFor="counterparty-new-note">
                                        Izoh (ixtiyoriy)
                                    </Label>
                                    <Input
                                        id="counterparty-new-note"
                                        name="note"
                                    />
                                    <InputError message={errors.note} />
                                </div>
                            )}
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
