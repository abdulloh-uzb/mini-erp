import { Form, Head, Link } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PhoneInput from '@/components/phone-input';
import ResetPasswordDialog from '@/components/reset-password-dialog';
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
import { formatPhone } from '@/lib/phone';
import { index } from '@/routes/employees';

type Employee = {
    id: number;
    name: string;
    phone: string;
    is_active: boolean;
};

type Props = {
    employees: Employee[];
};

export default function EmployeesIndex({ employees }: Props) {
    return (
        <>
            <Head title="Xodimlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Xodimlar"
                        description="Do'koningizda ishlaydigan xodimlar. O'chirilgan xodim tizimga kira olmaydi, tarixi saqlanadi."
                    />
                    <CreateEmployeeDialog />
                </div>

                {employees.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-10 text-center">
                        <p className="text-muted-foreground">Hali xodim yo'q</p>
                        <CreateEmployeeDialog />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Ism
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
                                {employees.map((employee) => (
                                    <EmployeeRow
                                        key={employee.id}
                                        employee={employee}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

function EmployeeRow({ employee }: { employee: Employee }) {
    return (
        <tr className="border-t">
            <td className="px-4 py-3 font-medium">{employee.name}</td>
            <td className="px-4 py-3 tabular-nums">
                {formatPhone(employee.phone)}
            </td>
            <td className="px-4 py-3">
                {employee.is_active ? (
                    <Badge>Faol</Badge>
                ) : (
                    <Badge variant="secondary">O'chirilgan</Badge>
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex justify-end gap-2">
                    <ResetPasswordDialog
                        form={EmployeeController.updatePassword.form(
                            employee.id,
                        )}
                        title="Xodim parolini tiklash"
                        description={`${employee.name} uchun yangi parol kiriting.`}
                    />
                    {employee.is_active ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={EmployeeController.deactivate(
                                    employee.id,
                                )}
                                as="button"
                                preserveScroll
                            >
                                O'chirish
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={EmployeeController.activate(employee.id)}
                                as="button"
                                preserveScroll
                            >
                                Faollashtirish
                            </Link>
                        </Button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function CreateEmployeeDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus />
                    Xodim qo'shish
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Xodim qo'shish</DialogTitle>
                <DialogDescription>
                    Xodim shu telefon raqami va parol bilan kiradi.
                </DialogDescription>
                <Form
                    {...EmployeeController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="employee-name">Ism</Label>
                                <Input
                                    id="employee-name"
                                    name="name"
                                    required
                                    autoFocus
                                    placeholder="Ism va familiya"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="employee-phone">
                                    Telefon raqami
                                </Label>
                                <PhoneInput
                                    id="employee-phone"
                                    name="phone"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="employee-password">Parol</Label>
                                <PasswordInput
                                    id="employee-password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="employee-password-confirmation">
                                    Parolni tasdiqlang
                                </Label>
                                <PasswordInput
                                    id="employee-password-confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                />
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

EmployeesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Xodimlar',
            href: index(),
        },
    ],
};
