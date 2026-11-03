import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';

import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';

import { type NavGroup } from '@/types';

import { Link } from '@inertiajs/react';

import { ClipboardList, FileBarChart, LayoutDashboard, Settings2, Trees, Users } from 'lucide-react';

// Módulos del sistema agrupados por uso real: operación diaria y administración.
const navGroups: NavGroup[] = [
    {
        title: 'Operación',
        items: [
            { title: 'Dashboard', url: '/dashboard', icon: LayoutDashboard },
            { title: 'Mantenimientos', url: '/mantenimientos', icon: ClipboardList },
            { title: 'Parques y áreas verdes', url: '/parques', icon: Trees },
            { title: 'Reportes', url: '/reportes', icon: FileBarChart },
        ],
    },
    {
        title: 'Administración',
        items: [
            { title: 'Trabajadores', url: '/trabajadores', icon: Users },
            { title: 'Configuración', url: '/configuracion', icon: Settings2 },
        ],
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="p-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-sidebar-accent/60 h-auto rounded-[10px] p-1.5">
                            <Link href="/dashboard" prefetch>
                                <span className="grid size-9 shrink-0 place-items-center rounded-[8px] bg-white ring-1 ring-black/5">
                                    <img
                                        src="/images/logo-sistema-verde.svg"
                                        alt="Sistema Verde"
                                        className="h-7 w-7 object-contain"
                                    />
                                </span>

                                <div className="ml-1 grid min-w-0 flex-1 text-left group-data-[collapsible=icon]:hidden">
                                    <span className="truncate text-[12.5px] leading-tight font-semibold">Sistema Verde</span>

                                    <span className="text-sidebar-foreground/60 truncate text-[10.5px] leading-tight">Parques y áreas verdes</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="p-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
