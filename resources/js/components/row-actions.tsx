import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { MoreHorizontal, type LucideIcon } from 'lucide-react';

export interface RowAction {
    label: string;
    icon?: LucideIcon;
    onSelect?: () => void;
    href?: string;
    danger?: boolean;
    disabled?: boolean;
}

interface RowActionsProps {
    items: RowAction[];
    label?: string;
}

export function RowActions({ items, label = 'Más acciones' }: RowActionsProps) {
    const visibleItems = items.filter(Boolean);

    if (visibleItems.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-foreground size-8 rounded-[9px]"
                    aria-label={label}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-64 max-w-[calc(100vw-2rem)] rounded-[12px] p-1.5">
                {visibleItems.map((item, index) => {
                    const Icon = item.icon;
                    const previous = visibleItems[index - 1];

                    return (
                        <div key={item.label}>
                            {item.danger && previous && !previous.danger && <DropdownMenuSeparator className="my-1" />}
                            <DropdownMenuItem
                                disabled={item.disabled}
                                onSelect={() => {
                                    if (item.href) {
                                        window.location.href = item.href;
                                        return;
                                    }

                                    item.onSelect?.();
                                }}
                                className={cn(
                                    'gap-2.5 rounded-[8px] px-2.5 py-2 text-[13px]',
                                    item.danger && 'text-destructive focus:bg-destructive/10 focus:text-destructive',
                                )}
                            >
                                {Icon && <Icon className="size-4 shrink-0" />}
                                <span className="min-w-0 whitespace-normal">{item.label}</span>
                            </DropdownMenuItem>
                        </div>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
