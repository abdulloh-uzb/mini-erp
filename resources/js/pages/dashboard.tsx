import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import OpeningBalanceCard from '@/components/opening-balance-card';
import { dashboard } from '@/routes';

type Props = {
    openingBalancePending: boolean;
};

export default function Dashboard({ openingBalancePending }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Bosh sahifa" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title={`Xush kelibsiz, ${auth.user.name}`}
                    description={auth.store?.name}
                />
                {openingBalancePending && <OpeningBalanceCard />}
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
