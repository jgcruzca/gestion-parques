import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';

export interface SectionTab {
    id: string;
    label: string;
    icon?: LucideIcon;
    count?: number;
}

interface SectionTabsProps {
    items: SectionTab[];
    active: string;
    onChange: (id: string) => void;
    ariaLabel: string;
    className?: string;
}

export function SectionTabs({ items, active, onChange, ariaLabel, className }: SectionTabsProps) {
    return (
        <nav className={cn('flex gap-1 overflow-x-auto px-2', className)} aria-label={ariaLabel} role="tablist">
            {items.map((item) => {
                const Icon = item.icon;
                const selected = active === item.id;

                return (
                    <button
                        key={item.id}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        tabIndex={selected ? 0 : -1}
                        onKeyDown={(event) => {
                            const index = items.findIndex((tab) => tab.id === item.id);
                            const nextIndex =
                                event.key === 'ArrowRight'
                                    ? (index + 1) % items.length
                                    : event.key === 'ArrowLeft'
                                      ? (index - 1 + items.length) % items.length
                                      : event.key === 'Home'
                                        ? 0
                                        : event.key === 'End'
                                          ? items.length - 1
                                          : null;

                            if (nextIndex === null) return;
                            event.preventDefault();
                            const buttons = event.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>('[role="tab"]');
                            buttons?.[nextIndex]?.focus();
                            onChange(items[nextIndex].id);
                        }}
                        onClick={() => onChange(item.id)}
                        className={cn(
                            'relative flex items-center gap-2 px-3.5 py-3 text-[13px] font-medium whitespace-nowrap transition-colors',
                            'after:absolute after:inset-x-3 after:-bottom-px after:h-0.5 after:rounded-full',
                            selected ? 'text-primary after:bg-primary' : 'text-muted-foreground hover:text-foreground after:bg-transparent',
                        )}
                    >
                        {Icon && <Icon className="size-4 shrink-0" />}
                        {item.label}
                        {typeof item.count === 'number' && (
                            <span
                                className={cn(
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                                    selected ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {item.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </nav>
    );
}
