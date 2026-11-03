import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    icon?: LucideIcon;
    actions?: ReactNode;
    meta?: ReactNode;
}

export function PageHeader({ title, description, icon: Icon, actions, meta }: PageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="flex min-w-0 items-start gap-3">
                {Icon && (
                    <span className="bg-brand-soft text-primary ring-primary/10 mt-0.5 grid size-11 shrink-0 place-items-center rounded-[12px] ring-1 ring-inset">
                        <Icon className="size-5" />
                    </span>
                )}

                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="page-title">{title}</h1>
                        {meta}
                    </div>

                    {description && <p className="page-description">{description}</p>}
                </div>
            </div>

            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </header>
    );
}
