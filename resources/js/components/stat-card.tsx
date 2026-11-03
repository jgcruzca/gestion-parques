import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

const toneStyles: Record<Tone, { icon: string; value: string }> = {
    neutral: { icon: 'bg-muted text-muted-foreground', value: 'text-foreground' },
    success: { icon: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300', value: 'text-foreground' },
    warning: { icon: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300', value: 'text-foreground' },
    danger: { icon: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300', value: 'text-rose-600 dark:text-rose-400' },
    info: { icon: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300', value: 'text-foreground' },
};

interface StatCardProps {
    label: string;
    value: React.ReactNode;
    hint?: React.ReactNode;
    icon?: LucideIcon;
    tone?: Tone;
    attention?: boolean;
    className?: string;
}

export function StatCard({ label, value, hint, icon: Icon, tone = 'neutral', attention = false, className }: StatCardProps) {
    return (
        <article
            className={cn(
                'surface flex min-w-0 flex-col justify-between gap-3 p-4',
                attention && 'border-rose-300/80 dark:border-rose-900',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="stat-label">{label}</p>
                {Icon && (
                    <span className={cn('grid size-8 shrink-0 place-items-center rounded-[9px]', toneStyles[tone].icon)}>
                        {<Icon className="size-4" />}
                    </span>
                )}
            </div>

            <div className="min-w-0">
                <p className={cn('stat-value', toneStyles[tone].value)}>{value}</p>
                {hint && <p className="stat-hint mt-1.5">{hint}</p>}
            </div>
        </article>
    );
}
