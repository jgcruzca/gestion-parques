import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Activity, AlertTriangle, ArrowRight, CheckCircle2, Droplets, Leaf, Settings2, Target, Trees, TrendingUp, Wrench } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type MaintenanceStatus = 'vencido' | 'pendiente' | 'en_proceso' | 'proximo';

interface Props {
    institution: {
        organization_name: string;
        unit_name: string;
    };
    indicators: {
        parks_total: number;
        parks_active: number;
        total_green_area: number;
        water_coverage: number;
        maintenances_overdue: number;
        maintenances_in_progress: number;
        maintenances_completed_month: number;
        maintenances_scheduled_month: number;
    };
    monthlyPerformance: {
        month: string;
        month_label: string;
        completed: number;
        scheduled: number;
        completion_rate: number;
        on_time_rate: number;
        park_coverage_rate: number;
    };
    monthlyTarget: {
        maintenance_goal: number;
        overdue_limit: number;
        park_coverage_goal: number;
    };
    statusDistribution: Array<{
        key: string;
        label: string;
        value: number;
        color: string;
    }>;
    monthlyTrend: Array<{
        month: string;
        scheduled: number;
        completed: number;
    }>;
    priorityMaintenances: Array<{
        id: number;
        maintenance_number: string;
        park_name: string;
        maintenance_type: string;
        next_date: string | null;
        remaining_days: number | null;
        remaining_label: string;
        status: MaintenanceStatus;
        is_in_progress: boolean;
    }>;
}

const statusMeta: Record<MaintenanceStatus, { label: string; className: string; dot: string }> = {
    vencido: {
        label: 'Vencido',
        className: 'bg-rose-50 text-rose-700 ring-rose-200/80 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-900',
        dot: 'bg-rose-500',
    },
    pendiente: {
        label: 'Para hoy',
        className: 'bg-amber-50 text-amber-700 ring-amber-200/80 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
        dot: 'bg-amber-500',
    },
    en_proceso: {
        label: 'En proceso',
        className: 'bg-sky-50 text-sky-700 ring-sky-200/80 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900',
        dot: 'bg-sky-500',
    },
    proximo: {
        label: 'Próximo',
        className: 'bg-violet-50 text-violet-700 ring-violet-200/80 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900',
        dot: 'bg-violet-500',
    },
};

function ProgressBar({ value, tone = 'bg-primary' }: { value: number; tone?: string }) {
    return (
        <div className="bg-muted h-1.5 overflow-hidden rounded-full">
            <div className={`h-full rounded-full transition-all ${tone}`} style={{ width: `${Math.max(0, Math.min(value, 100))}%` }} />
        </div>
    );
}

function GoalRow({
    label,
    value,
    target,
    suffix = '',
    inverse = false,
}: {
    label: string;
    value: number;
    target: number;
    suffix?: string;
    inverse?: boolean;
}) {
    const achieved = inverse ? value <= target : value >= target;
    const progress = inverse
        ? value <= target
            ? 100
            : Math.max(8, target === 0 ? 100 : (target / Math.max(value, 1)) * 100)
        : (value / Math.max(target, 1)) * 100;

    return (
        <div>
            <div className="mb-2 flex items-center justify-between gap-3">
                <span className="text-foreground text-[13px] font-medium">{label}</span>
                <span className={`text-[13px] font-semibold tabular-nums ${achieved ? 'text-primary' : 'text-rose-600 dark:text-rose-400'}`}>
                    {value}
                    {suffix}{' '}
                    <span className="text-muted-foreground text-[11px] font-normal">{inverse ? `· límite ${target}` : `/ ${target}${suffix}`}</span>
                </span>
            </div>
            <ProgressBar value={progress} tone={achieved ? 'bg-primary' : 'bg-amber-500'} />
        </div>
    );
}

function TrendChart({ data }: { data: Props['monthlyTrend'] }) {
    const maxValue = Math.max(1, ...data.flatMap((item) => [item.scheduled, item.completed]));
    const axisMax = Math.ceil(maxValue / 4) * 4;

    return (
        <div>
            <div className="relative">
                {/* Guías de lectura: escala compartida, sin decoración. */}
                <div className="pointer-events-none absolute inset-x-0 top-0 flex h-44 flex-col justify-between" aria-hidden="true">
                    {[0, 1, 2, 3].map((line) => (
                        <div key={line} className="border-border/60 border-t border-dashed" />
                    ))}
                </div>

                <div className="relative flex h-44 items-end gap-3 sm:gap-6">
                    {data.map((item) => (
                        <div key={item.month} className="flex h-full min-w-0 flex-1 flex-col justify-end">
                            <div className="flex h-full items-end justify-center gap-1.5 sm:gap-2">
                                <div
                                    className="bg-muted-foreground/25 dark:bg-muted-foreground/35 w-full max-w-6 rounded-t-[3px] transition-all"
                                    style={{ height: `${(item.scheduled / axisMax) * 100}%` }}
                                    title={`${item.scheduled} programados`}
                                />
                                <div
                                    className="bg-primary w-full max-w-6 rounded-t-[3px] transition-all"
                                    style={{ height: `${(item.completed / axisMax) * 100}%` }}
                                    title={`${item.completed} ejecutados`}
                                />
                            </div>
                            <p className="text-muted-foreground mt-2.5 truncate text-center text-[11px] font-medium">{item.month}</p>
                        </div>
                    ))}
                </div>
            </div>

            <div className="text-muted-foreground mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 border-t pt-3 text-[11px]">
                <span className="flex items-center gap-2">
                    <i className="bg-muted-foreground/25 dark:bg-muted-foreground/35 size-2.5 rounded-[2px]" /> Programados
                </span>
                <span className="flex items-center gap-2">
                    <i className="bg-primary size-2.5 rounded-[2px]" /> Ejecutados
                </span>
                <span className="ml-auto tabular-nums">Máximo del periodo: {maxValue}</span>
            </div>
        </div>
    );
}

function PriorityPanel({ items }: { items: Props['priorityMaintenances'] }) {
    return (
        <section className="surface min-w-0 overflow-hidden">
            <div className="flex items-start justify-between gap-3 border-b px-5 py-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="section-title">Prioridades de atención</h2>
                        {items.length > 0 && (
                            <span className="pill bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">{items.length} por revisar</span>
                        )}
                    </div>
                    <p className="section-description">Lo urgente aparece primero. Atiende sin salir del resumen.</p>
                </div>
                <Link
                    href="/mantenimientos"
                    className="text-primary hover:text-primary/80 flex shrink-0 items-center gap-1 text-[12px] font-semibold"
                >
                    Ver todo <ArrowRight className="size-3.5" />
                </Link>
            </div>

            {items.length > 0 ? (
                <div className="divide-border divide-y">
                    {items.map((maintenance) => {
                        const meta = statusMeta[maintenance.status];
                        return (
                            <div
                                key={maintenance.id}
                                className="hover:bg-muted/40 flex flex-col gap-3 px-5 py-3.5 transition sm:flex-row sm:items-center"
                            >
                                <span className={`hidden size-2 shrink-0 rounded-full sm:block ${meta.dot}`} />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-foreground truncate text-[13px] font-semibold">{maintenance.park_name}</p>
                                        <span className={`pill ring-1 ring-inset ${meta.className}`}>{meta.label}</span>
                                    </div>
                                    <p className="text-muted-foreground mt-1 truncate text-[11.5px]">
                                        {maintenance.maintenance_type} · {maintenance.maintenance_number}
                                    </p>
                                </div>
                                <div className="flex items-center justify-between gap-4 sm:justify-end">
                                    <div className="text-right">
                                        <p
                                            className={`text-[12px] font-semibold tabular-nums ${maintenance.status === 'vencido' ? 'text-rose-600 dark:text-rose-400' : ''}`}
                                        >
                                            {maintenance.remaining_label}
                                        </p>
                                        <p className="text-muted-foreground text-[11px]">{maintenance.next_date ?? 'Sin fecha'}</p>
                                    </div>
                                    <Button asChild variant="outline" size="sm" className="h-8 rounded-[8px] px-2.5 text-[11px]">
                                        <Link href={`/mantenimientos?${maintenance.is_in_progress ? 'finalizar' : 'atender'}=${maintenance.id}`}>
                                            {maintenance.is_in_progress ? 'Finalizar' : 'Atender'}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="grid min-h-64 place-items-center p-8 text-center">
                    <div>
                        <CheckCircle2 className="text-primary mx-auto size-8" />
                        <p className="mt-3 text-[13px] font-semibold">Operación al día</p>
                        <p className="text-muted-foreground mt-1 text-[12px]">No hay mantenimientos que requieran atención inmediata.</p>
                    </div>
                </div>
            )}
        </section>
    );
}

export default function Dashboard({
    institution,
    indicators,
    monthlyPerformance,
    monthlyTarget,
    statusDistribution,
    monthlyTrend,
    priorityMaintenances,
}: Props) {
    const [targetsOpen, setTargetsOpen] = useState(false);
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        month: monthlyPerformance.month,
        maintenance_goal: monthlyTarget.maintenance_goal,
        overdue_limit: monthlyTarget.overdue_limit,
        park_coverage_goal: monthlyTarget.park_coverage_goal,
    });

    const distributionTotal = statusDistribution.reduce((total, item) => total + item.value, 0);
    const donutBackground = useMemo(() => {
        if (distributionTotal === 0) return 'conic-gradient(var(--muted) 0 100%)';
        let start = 0;
        const segments = statusDistribution.map((item) => {
            const end = start + (item.value / distributionTotal) * 100;
            const segment = `${item.color} ${start}% ${end}%`;
            start = end;
            return segment;
        });
        return `conic-gradient(${segments.join(', ')})`;
    }, [distributionTotal, statusDistribution]);

    const submitTargets = (event: FormEvent) => {
        event.preventDefault();
        post('/dashboard/metas', {
            preserveScroll: true,
            onSuccess: () => setTargetsOpen(false),
        });
    };

    const areaLabel =
        indicators.total_green_area >= 10_000
            ? `${(indicators.total_green_area / 10_000).toLocaleString('es-PE', { maximumFractionDigits: 1 })} ha`
            : `${indicators.total_green_area.toLocaleString('es-PE')} m²`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard | Gestión de Áreas Verdes" />

            <div className="page-shell">
                <div className="page-container">
                    <section className="surface p-4 md:p-5">
                        <PageHeader
                            title="Resumen operativo"
                            description={`${institution.organization_name} · ${institution.unit_name}`}
                            icon={Leaf}
                            meta={
                                <span className="bg-brand-soft text-primary rounded-full px-2.5 py-1 text-[11px] font-semibold">
                                    {monthlyPerformance.month_label}
                                </span>
                            }
                            actions={
                                <>
                                    <Button variant="outline" size="sm" onClick={() => setTargetsOpen(true)} className="h-9 rounded-[9px]">
                                        <Target className="size-4" /> Definir metas
                                    </Button>
                                    <Button asChild size="sm" className="h-9 rounded-[9px]">
                                        <Link href="/mantenimientos?accion=extraordinario">
                                            <Wrench className="size-4" /> Nuevo mantenimiento
                                        </Link>
                                    </Button>
                                </>
                            }
                        />
                    </section>

                    <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                        <PriorityPanel items={priorityMaintenances} />

                        <section aria-label="Indicadores principales" className="flex min-w-0 flex-col gap-3">
                            <div className="flex items-center justify-between gap-3 px-1">
                                <div>
                                    <h2 className="section-title">Estado de los mantenimientos</h2>
                                    <p className="section-description">Datos esenciales para decidir ahora.</p>
                                </div>
                                <Activity className="text-primary size-4" />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                <StatCard
                                    label="Mantenimientos vencidos"
                                    value={indicators.maintenances_overdue}
                                    hint={
                                        indicators.maintenances_overdue > monthlyTarget.overdue_limit ? 'Atención prioritaria' : 'Dentro del límite'
                                    }
                                    icon={AlertTriangle}
                                    tone="danger"
                                    attention={indicators.maintenances_overdue > monthlyTarget.overdue_limit}
                                />
                                <StatCard
                                    label="Ejecutados este mes"
                                    value={indicators.maintenances_completed_month}
                                    hint={`de ${indicators.maintenances_scheduled_month} programados`}
                                    icon={CheckCircle2}
                                    tone="info"
                                />
                                <StatCard
                                    label="Parques operativos"
                                    value={`${indicators.parks_active}/${indicators.parks_total}`}
                                    hint={areaLabel}
                                    icon={Trees}
                                    tone="success"
                                />
                                <StatCard
                                    label="Cobertura de agua"
                                    value={`${indicators.water_coverage}%`}
                                    hint={`${indicators.maintenances_in_progress} en proceso`}
                                    icon={Droplets}
                                    tone="info"
                                />
                            </div>
                        </section>
                    </div>

                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
                        <section className="surface min-w-0 p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="section-title">Desempeño del mes</h2>
                                    <p className="section-description">Avance real frente a las metas operativas definidas.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setTargetsOpen(true)}
                                    className="text-muted-foreground hover:bg-muted hover:text-foreground rounded-[8px] p-2 transition"
                                    aria-label="Configurar metas mensuales"
                                >
                                    <Settings2 className="size-4" />
                                </button>
                            </div>

                            <div className="mt-6 grid gap-6 md:grid-cols-[180px_1fr] md:items-center">
                                <div
                                    className="relative mx-auto grid size-40 place-items-center rounded-full"
                                    style={{ background: `conic-gradient(var(--brand) ${monthlyPerformance.completion_rate}%, var(--muted) 0)` }}
                                >
                                    <div className="bg-card grid size-28 place-items-center rounded-full text-center">
                                        <div>
                                            <p className="text-[30px] leading-none font-semibold tracking-[-0.02em] tabular-nums">
                                                {monthlyPerformance.completion_rate}%
                                            </p>
                                            <p className="text-muted-foreground mt-1.5 text-[10.5px]">avance mensual</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-5">
                                    <GoalRow
                                        label="Mantenimientos ejecutados"
                                        value={monthlyPerformance.completed}
                                        target={monthlyTarget.maintenance_goal}
                                    />
                                    <GoalRow
                                        label="Cobertura de parques"
                                        value={monthlyPerformance.park_coverage_rate}
                                        target={monthlyTarget.park_coverage_goal}
                                        suffix="%"
                                    />
                                    <GoalRow
                                        label="Límite de vencidos"
                                        value={indicators.maintenances_overdue}
                                        target={monthlyTarget.overdue_limit}
                                        inverse
                                    />
                                </div>
                            </div>

                            <div className="divide-border bg-muted/50 mt-6 grid grid-cols-3 divide-x rounded-[10px] py-3">
                                <div className="px-3 text-center">
                                    <p className="text-[17px] font-semibold tabular-nums">{monthlyPerformance.completed}</p>
                                    <p className="text-muted-foreground mt-0.5 text-[11px]">Ejecutados</p>
                                </div>
                                <div className="px-3 text-center">
                                    <p className="text-[17px] font-semibold tabular-nums">{monthlyPerformance.on_time_rate}%</p>
                                    <p className="text-muted-foreground mt-0.5 text-[11px]">A tiempo</p>
                                </div>
                                <div className="px-3 text-center">
                                    <p className="text-[17px] font-semibold tabular-nums">{monthlyPerformance.park_coverage_rate}%</p>
                                    <p className="text-muted-foreground mt-0.5 text-[11px]">Cobertura</p>
                                </div>
                            </div>
                        </section>

                        <section className="surface min-w-0 p-5">
                            <div>
                                <h2 className="section-title">Estado operativo</h2>
                                <p className="section-description">Distribución de la carga de mantenimiento.</p>
                            </div>

                            <div className="mt-6 flex flex-col items-center gap-6 sm:flex-row xl:flex-col 2xl:flex-row">
                                <div className="relative size-36 shrink-0 rounded-full" style={{ background: donutBackground }}>
                                    <div className="bg-card absolute inset-5 grid place-items-center rounded-full text-center">
                                        <div>
                                            <p className="text-[24px] leading-none font-semibold tabular-nums">{distributionTotal}</p>
                                            <p className="text-muted-foreground mt-1 text-[9.5px] tracking-[0.1em] uppercase">activos</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="w-full space-y-2.5">
                                    {statusDistribution.map((item) => (
                                        <div key={item.key} className="flex items-center gap-2.5 text-[12.5px]">
                                            <span className="size-2.5 rounded-full" style={{ backgroundColor: item.color }} />
                                            <span className="text-muted-foreground flex-1">{item.label}</span>
                                            <span className="font-semibold tabular-nums">{item.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>
                    </div>

                    <section className="surface min-w-0 p-5">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="section-title">Tendencia semestral</h2>
                                <p className="section-description">Programación frente a ejecución.</p>
                            </div>
                            <Link href="/reportes" className="text-primary hover:text-primary/80 flex items-center gap-1 text-[12px] font-semibold">
                                Ver reportes <TrendingUp className="size-3.5" />
                            </Link>
                        </div>
                        <div className="mt-6">
                            <TrendChart data={monthlyTrend} />
                        </div>
                    </section>

                    <footer className="text-muted-foreground flex flex-col justify-between gap-1 border-t pt-4 pb-1 text-[11px] sm:flex-row">
                        <p>Indicadores actualizados con la información operativa registrada.</p>
                        <p>{institution.organization_name}</p>
                    </footer>
                </div>
            </div>

            <Dialog open={targetsOpen} onOpenChange={setTargetsOpen}>
                <DialogContent className="max-w-md rounded-[16px]">
                    <form onSubmit={submitTargets}>
                        <DialogHeader>
                            <div className="bg-brand-soft text-primary mb-2 grid size-10 place-items-center rounded-[10px]">
                                <Target className="size-5" />
                            </div>
                            <DialogTitle>Metas de {monthlyPerformance.month_label}</DialogTitle>
                            <DialogDescription>Define los umbrales que el dashboard usará para medir el desempeño del mes.</DialogDescription>
                        </DialogHeader>

                        <div className="my-6 space-y-4">
                            <label className="block">
                                <span className="text-[12px] font-semibold">Meta de mantenimientos ejecutados</span>
                                <Input
                                    className="mt-2 h-10 rounded-[10px]"
                                    type="number"
                                    min={1}
                                    max={9999}
                                    value={data.maintenance_goal}
                                    onChange={(event) => setData('maintenance_goal', Number(event.target.value))}
                                />
                                {errors.maintenance_goal && <p className="mt-1 text-[11px] text-rose-600">{errors.maintenance_goal}</p>}
                            </label>
                            <label className="block">
                                <span className="text-[12px] font-semibold">Máximo de vencidos tolerable</span>
                                <Input
                                    className="mt-2 h-10 rounded-[10px]"
                                    type="number"
                                    min={0}
                                    max={9999}
                                    value={data.overdue_limit}
                                    onChange={(event) => setData('overdue_limit', Number(event.target.value))}
                                />
                                {errors.overdue_limit && <p className="mt-1 text-[11px] text-rose-600">{errors.overdue_limit}</p>}
                            </label>
                            <label className="block">
                                <span className="text-[12px] font-semibold">Cobertura objetivo de parques (%)</span>
                                <Input
                                    className="mt-2 h-10 rounded-[10px]"
                                    type="number"
                                    min={1}
                                    max={100}
                                    value={data.park_coverage_goal}
                                    onChange={(event) => setData('park_coverage_goal', Number(event.target.value))}
                                />
                                {errors.park_coverage_goal && <p className="mt-1 text-[11px] text-rose-600">{errors.park_coverage_goal}</p>}
                            </label>
                        </div>

                        <DialogFooter className="gap-2 sm:space-x-0">
                            <Button type="button" variant="outline" onClick={() => setTargetsOpen(false)} className="h-9 rounded-[9px]">
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing} className="h-9 rounded-[9px]">
                                {processing ? 'Guardando…' : recentlySuccessful ? 'Guardado' : 'Guardar metas'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
