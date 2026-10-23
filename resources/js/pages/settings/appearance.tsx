import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Ko'rinish sozlamalari" />

            <h1 className="sr-only">Ko'rinish sozlamalari</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Ko'rinish sozlamalari"
                    description="Ilova ko'rinishini o'zingizga moslang"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: "Ko'rinish sozlamalari",
            href: editAppearance(),
        },
    ],
};
