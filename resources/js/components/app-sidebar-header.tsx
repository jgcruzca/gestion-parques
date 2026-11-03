import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, CheckCircle2 } from 'lucide-react';

interface HeaderNotification {
    id: number;
    title: string;
    description: string;
    meta: string;
    severity: 'critical' | 'warning';
    href: string;
}

interface HeaderPageProps extends SharedData {
    notifications?: HeaderNotification[];
}

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { notifications = [] } = usePage<HeaderPageProps>().props;

    return (
        <header className="border-border/70 bg-background/85 supports-[backdrop-filter]:bg-background/70 sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between gap-3 border-b px-4 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
            <div className="flex min-w-0 items-center gap-2.5">
                <SidebarTrigger className="-ml-1 size-8 rounded-lg" />
                <span className="bg-border hidden h-4 w-px sm:block" aria-hidden="true" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="relative size-9 rounded-[10px]" aria-label="Notificaciones de mantenimiento">
                        <Bell className="size-[18px]" />
                        {notifications.length > 0 && (
                            <span className="ring-background bg-destructive absolute -top-0.5 -right-0.5 grid min-w-4 place-items-center rounded-full px-1 text-[9px] leading-4 font-bold text-white ring-2">
                                {notifications.length > 9 ? '9+' : notifications.length}
                            </span>
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-[min(92vw,380px)] rounded-[14px] p-0 shadow-xl">
                    <div className="flex items-center justify-between border-b px-4 py-3.5">
                        <div>
                            <p className="text-[13px] font-semibold">Alertas operativas</p>
                            <p className="text-muted-foreground mt-0.5 text-[11px]">Mantenimientos que requieren atención</p>
                        </div>
                        {notifications.length > 0 && (
                            <span className="bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                                {notifications.length} activas
                            </span>
                        )}
                    </div>

                    {notifications.length > 0 ? (
                        <div className="max-h-80 overflow-y-auto p-1.5">
                            {notifications.map((notification) => (
                                <DropdownMenuItem key={notification.id} asChild className="focus:bg-muted rounded-[10px] p-0">
                                    <Link href={notification.href} className="flex items-start gap-3 px-3 py-3">
                                        <span
                                            className={`mt-0.5 grid size-8 shrink-0 place-items-center rounded-[8px] ${notification.severity === 'critical' ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/50' : 'bg-amber-50 text-amber-600 dark:bg-amber-950/50'}`}
                                        >
                                            <AlertTriangle className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-[12px] font-semibold">{notification.title}</span>
                                            <span className="text-muted-foreground mt-0.5 block truncate text-[11px]">
                                                {notification.description}
                                            </span>
                                            <span
                                                className={`mt-1 block text-[11px] font-semibold ${notification.severity === 'critical' ? 'text-rose-600' : 'text-amber-600'}`}
                                            >
                                                {notification.meta}
                                            </span>
                                        </span>
                                    </Link>
                                </DropdownMenuItem>
                            ))}
                        </div>
                    ) : (
                        <div className="px-5 py-9 text-center">
                            <CheckCircle2 className="text-primary mx-auto size-8" />
                            <p className="mt-2 text-[13px] font-semibold">Todo está al día</p>
                            <p className="text-muted-foreground mt-1 text-[11px]">No hay alertas urgentes por atender.</p>
                        </div>
                    )}

                    <div className="border-t p-2">
                        <Link
                            href="/mantenimientos"
                            className="text-primary hover:bg-accent flex h-9 items-center justify-center rounded-[8px] text-[11px] font-semibold transition"
                        >
                            Abrir trabajos pendientes
                        </Link>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
}
