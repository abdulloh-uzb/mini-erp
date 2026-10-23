import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

/**
 * Building blocks shared by the report pages: a headline figure and the date range form.
 */
export function ReportStat({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
                {value}
            </CardContent>
        </Card>
    );
}

export function ReportPeriodForm({
    filters,
    url,
}: {
    filters: { from: string; to: string };
    url: (query: { from: string; to: string }) => string;
}) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(url({ from, to }), {}, { replace: true });
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            <label className="grid gap-1 text-sm">
                Dan
                <Input
                    type="date"
                    value={from}
                    onChange={(event) => setFrom(event.target.value)}
                    className="w-44"
                />
            </label>
            <label className="grid gap-1 text-sm">
                Gacha
                <Input
                    type="date"
                    value={to}
                    onChange={(event) => setTo(event.target.value)}
                    className="w-44"
                />
            </label>
            <Button type="submit">Ko'rsatish</Button>
        </form>
    );
}
