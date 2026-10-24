import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

/**
 * The sidebar menu. Titled groups fold open and closed independently, starting with the
 * group of the current page open. When the sidebar is collapsed to icons every item
 * shows, since there are no group titles to click.
 */
export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { currentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { draftCounts } = usePage().props;
    const { state, isMobile } = useSidebar();
    const isIconOnly = state === 'collapsed' && !isMobile;

    const activeGroup =
        groups.find((group) =>
            group.items.some((item) => isCurrentOrParentUrl(item.href)),
        )?.title ?? null;
    const [openGroups, setOpenGroups] = useState<string[]>(
        activeGroup ? [activeGroup] : [],
    );
    const [openedForUrl, setOpenedForUrl] = useState(currentUrl);

    if (openedForUrl !== currentUrl) {
        setOpenedForUrl(currentUrl);

        if (activeGroup && !openGroups.includes(activeGroup)) {
            setOpenGroups([...openGroups, activeGroup]);
        }
    }

    const draftCount = (draftKind?: string): number =>
        draftKind ? (draftCounts[draftKind] ?? 0) : 0;

    const menu = (group: NavGroup) => (
        <SidebarMenu>
            {group.items.map((item) => (
                <SidebarMenuItem key={item.title}>
                    <SidebarMenuButton
                        asChild
                        isActive={isCurrentOrParentUrl(item.href)}
                        tooltip={{ children: item.title }}
                        className="h-9 text-[15px]"
                    >
                        <Link href={item.href} prefetch>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                    {draftCount(item.draftKind) > 0 && (
                        <SidebarMenuBadge>
                            {draftCount(item.draftKind)}
                        </SidebarMenuBadge>
                    )}
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );

    return (
        <>
            {groups.map((group, index) => {
                if (!group.title || isIconOnly) {
                    return (
                        <SidebarGroup
                            key={group.title ?? `group-${index}`}
                            className="px-2 py-0"
                        >
                            {menu(group)}
                        </SidebarGroup>
                    );
                }

                const title = group.title;
                const isOpen = openGroups.includes(title);
                const groupDrafts = group.items.reduce(
                    (sum, item) => sum + draftCount(item.draftKind),
                    0,
                );

                return (
                    <Collapsible
                        key={title}
                        open={isOpen}
                        onOpenChange={(open) =>
                            setOpenGroups(
                                open
                                    ? [...openGroups, title]
                                    : openGroups.filter(
                                          (openTitle) => openTitle !== title,
                                      ),
                            )
                        }
                        className="group/collapsible"
                    >
                        <SidebarGroup className="px-2 py-0">
                            <SidebarGroupLabel
                                asChild
                                className="h-10 cursor-pointer text-base font-semibold [&>svg]:size-5 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                            >
                                <CollapsibleTrigger>
                                    {title}
                                    {!isOpen && groupDrafts > 0 && (
                                        <span className="ml-2 rounded-md bg-sidebar-primary px-1.5 text-[10px] font-medium text-sidebar-primary-foreground tabular-nums">
                                            {groupDrafts}
                                        </span>
                                    )}
                                    <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                {menu(group)}
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                );
            })}
        </>
    );
}
