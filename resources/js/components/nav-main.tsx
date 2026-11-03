import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const page = usePage();
    const currentUrl = page.url.split('?')[0];

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-1.5">
                    <SidebarGroupLabel className="text-sidebar-foreground/55 mb-1 h-6 px-2 text-[10px] font-semibold tracking-[0.1em] uppercase">
                        {group.title}
                    </SidebarGroupLabel>

                    <SidebarMenu className="gap-0.5">
                        {group.items.map((item) => {
                            const isActive = item.url === currentUrl;

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive}
                                        className={cn(
                                            'h-9 rounded-[9px] px-2.5 text-[13px] font-medium transition-colors',
                                            isActive
                                                ? 'bg-sidebar-accent text-sidebar-accent-foreground hover:bg-sidebar-accent font-semibold'
                                                : 'text-sidebar-foreground/85 hover:text-sidebar-foreground',
                                            '[&>svg]:size-4',
                                            isActive && '[&>svg]:text-primary',
                                        )}
                                    >
                                        <Link href={item.url} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
