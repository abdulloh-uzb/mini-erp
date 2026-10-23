import { Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * The shared list-page pattern (spec: Frontend): a header with the main button,
 * a search box and filter tabs, a table, and the empty states
 * "Hali … yo'q" + main button and "… topilmadi" + "Filtrni tozalash".
 */
export function ListPageHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4">
            <Heading title={title} description={description} />
            {action}
        </div>
    );
}

/**
 * Reloads the current page with `?search=` as the user types, keeping other filters.
 */
export function ListSearch({
    value,
    placeholder = 'Qidirish',
}: {
    value: string;
    placeholder?: string;
}) {
    const { url } = usePage();
    const [search, setSearch] = useState(value);
    const lastSent = useRef(value);

    useEffect(() => {
        if (search === lastSent.current) {
            return;
        }

        const timeout = setTimeout(() => {
            lastSent.current = search;

            const target = new URL(url, window.location.origin);
            target.searchParams.delete('page');

            if (search.trim() === '') {
                target.searchParams.delete('search');
            } else {
                target.searchParams.set('search', search);
            }

            router.get(
                target.pathname + target.search,
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, url]);

    return (
        <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={placeholder}
                className="pl-9"
                aria-label={placeholder}
            />
        </div>
    );
}

export type ListTab = {
    label: string;
    href: string;
    active: boolean;
};

export function ListTabs({ tabs }: { tabs: ListTab[] }) {
    return (
        <div className="inline-flex rounded-lg bg-muted p-1">
            {tabs.map((tab) => (
                <Link
                    key={tab.label}
                    href={tab.href}
                    preserveScroll
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors',
                        tab.active && 'bg-background text-foreground shadow-xs',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}

export function ListToolbar({ children }: { children: ReactNode }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            {children}
        </div>
    );
}

/**
 * Shown when the store has nothing of this kind yet: "Hali … yo'q" plus the main button.
 */
export function EmptyState({
    message,
    action,
}: {
    message: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-10 text-center">
            <p className="text-muted-foreground">{message}</p>
            {action}
        </div>
    );
}

/**
 * Shown when rows exist but none match the search or filters.
 */
export function FilteredEmptyState({
    message = 'Filtrga mos natija topilmadi',
    clearHref,
}: {
    message?: string;
    clearHref: string;
}) {
    return (
        <EmptyState
            message={message}
            action={
                <Button variant="outline" asChild>
                    <Link href={clearHref}>Filtrni tozalash</Link>
                </Button>
            }
        />
    );
}

export function ListTable({
    columns,
    children,
}: {
    columns: ReactNode[];
    children: ReactNode;
}) {
    return (
        <div className="overflow-hidden rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        {columns.map((column, index) => (
                            <th key={index} className="px-4 py-3 font-medium">
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

export function ListPagination<T>({ paginator }: { paginator: Paginated<T> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
                {paginator.from}–{paginator.to} / {paginator.total}
            </span>
            <div className="flex gap-2">
                <PageLink href={paginator.prev_page_url} label="Oldingi">
                    <ChevronLeft />
                </PageLink>
                <PageLink href={paginator.next_page_url} label="Keyingi">
                    <ChevronRight />
                </PageLink>
            </div>
        </div>
    );
}

function PageLink({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: ReactNode;
}) {
    if (href === null) {
        return (
            <Button variant="outline" size="icon" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="icon" asChild>
            <Link href={href} preserveScroll aria-label={label}>
                {children}
            </Link>
        </Button>
    );
}
