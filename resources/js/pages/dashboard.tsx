import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Bosh sahifa" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title={`Xush kelibsiz, ${auth.user.name}`}
                    description={auth.store?.name}
                />
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Bosh sahifa',
            href: dashboard(),
        },
    ],
};
