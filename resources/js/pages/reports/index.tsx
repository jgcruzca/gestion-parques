import { PageHeader } from '@/components/page-header';
import { SectionTabs } from '@/components/section-tabs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart3,
    BellRing,
    CalendarDays,
    CalendarRange,
    CheckCircle2,
    ChevronDown,
    Clock,
    Download,
    Droplets,
    FileSpreadsheet,
    FileText,
    History,
    MapPin,
    PlayCircle,
    Printer,
    RotateCcw,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Trees,
    Users,
    Wrench,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';

type ReportTab = 'general' | 'maintenances' | 'history' | 'audit' | 'workers' | 'alerts' | 'water';

type Filters = {
    month: string;
    date_from: string;
    date_to: string;
    park_id: string;
    maintenance_type: string;
    worker_id: string;
    status: string;
    date_basis: 'operational' | 'scheduled' | 'executed';
    maintenance_origin: string;
    assignment: string;
    duration_min: string;
    duration_max: string;
};

interface ParkOption {
    id: number;
    code: string | null;
    name: string;
}

interface WorkerOption {
    id: number;
    full_name: string;
}

interface ParkReportItem {
    id: number;
    code: string;
    name: string;
    green_area_type: string;
    location: string;
    public_use_area: number | null;
    green_area: number | null;
    has_water_point: boolean;
    water_point_status: string | null;
    conservation_status: string;
    is_active: boolean;
}

interface MaintenanceReportItem {
    id: number;
    maintenance_number: string;
    scheduled_date: string | null;
    execution_date: string | null;
    park_name: string;
    park_code: string;
    maintenance_type: string;
    worker_name: string;
    duration_minutes: number | null;
    duration_label: string;
    status: string;
    status_label: string;
}

interface HistoryRecord {
    id: number;
    maintenance_number: string;
    maintenance_type: string;
    scheduled_date: string | null;
    execution_date: string | null;
    worker_name: string;
    schedule: string;
    observations: string;
    document_name: string | null;
    document_url: string | null;
    status: string;
    status_label: string;
}

interface ParkHistory {
    id: number;
    code: string;
    name: string;
    maintenance_count: number;
    maintenance_types: string[];
    records: HistoryRecord[];
}

interface AuditReportItem {
    id: number;
    recorded_at: string | null;
    maintenance_number: string;
    park_name: string;
    park_code: string;
    maintenance_type: string;
    scheduled_date: string | null;
    overdue_days: number;
    reason: string;
    document_name: string | null;
    document_url: string | null;
    recorded_by: string;
    action: string;
    current_status: string;
    current_status_label: string;
}

interface WorkerReportItem {
    id: number;
    code: string;
    full_name: string;
    position: string;
    is_active: boolean;
    maintenance_count: number;
    parks: string[];
    work_types: string[];
    hours: number;
    completed_count: number;
    cancelled_count: number;
}

interface AlertItem {
    id: number;
    maintenance_number: string;
    park_name: string;
    maintenance_type: string;
    scheduled_date: string | null;
}

interface AlertsReport {
    counts: {
        up_to_date: number;
        upcoming: number;
        due_today: number;
        overdue: number;
        in_progress: number;
    };
    up_to_date_parks: Array<{ id: number; code: string | null; name: string }>;
    upcoming: AlertItem[];
    due_today: AlertItem[];
    overdue: AlertItem[];
    in_progress: AlertItem[];
}

interface WaterParkItem {
    id: number;
    code: string;
    name: string;
    has_water_point: boolean;
    water_supply_type: string | null;
    water_point_status: string | null;
    observations: string | null;
}

interface WaterReport {
    counts: {
        with_water: number;
        without_water: number;
        operational: number;
        with_observations: number;
        non_operational: number;
    };
    parks: WaterParkItem[];
}

interface Props {
    institutionName: string;
    activeReport: string;
    filters: Filters;
    filterOptions: {
        parks: ParkOption[];
        workers: WorkerOption[];
        maintenanceTypes: string[];
    };
    parksReport: ParkReportItem[];
    maintenanceReport: MaintenanceReportItem[];
    parkHistories: ParkHistory[];
    auditReport: AuditReportItem[];
    workerReport: WorkerReportItem[];
    alertsReport: AlertsReport;
    waterReport: WaterReport;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reportes', href: '/reportes' },
];

const tabs: Array<{ id: ReportTab; label: string; shortLabel: string; icon: typeof Trees }> = [
    { id: 'general', label: 'Reporte general de parques', shortLabel: 'Parques', icon: Trees },
    { id: 'maintenances', label: 'Mantenimientos por periodo', shortLabel: 'Mantenimientos', icon: Wrench },
    { id: 'history', label: 'Historial por parque', shortLabel: 'Historial', icon: History },
    { id: 'audit', label: 'Motivos de retraso', shortLabel: 'Retrasos', icon: ShieldCheck },
    { id: 'workers', label: 'Reporte por trabajador', shortLabel: 'Trabajadores', icon: Users },
    { id: 'alerts', label: 'Reporte de alertas', shortLabel: 'Alertas', icon: BellRing },
    { id: 'water', label: 'Reporte de puntos de agua', shortLabel: 'Puntos de agua', icon: Droplets },
];

const exportType: Record<ReportTab, string> = {
    general: 'parks',
    maintenances: 'maintenances',
    history: 'history',
    audit: 'audit',
    workers: 'workers',
    alerts: 'alerts',
    water: 'water',
};

const statusStyles: Record<string, string> = {
    proximo: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    vencido: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    en_proceso: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    finalizado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    cancelado: 'bg-slate-200 text-slate-700 dark:bg-neutral-800 dark:text-neutral-300',
};

function StatusBadge({ status, label }: { status: string; label: string }) {
    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${statusStyles[status] ?? statusStyles.cancelado}`}>
            {label}
        </span>
    );
}

function EmptyState({ icon, title, description }: { icon: ReactNode; title: string; description: string }) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div className="dark:bg-muted mb-3 rounded-2xl bg-slate-100 p-3 text-slate-400">{icon}</div>
            <p className="text-foreground font-semibold">{title}</p>
            <p className="text-muted-foreground mt-1 max-w-sm text-xs">{description}</p>
        </div>
    );
}

function SummaryCard({ label, value, detail, icon, tone }: { label: string; value: number | string; detail: string; icon: ReactNode; tone: string }) {
    return (
        <div className="bg-card rounded-2xl border p-4 shadow-xs">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-muted-foreground text-xs font-medium">{label}</p>
                    <p className="text-foreground mt-1 text-2xl font-bold">{value}</p>
                    <p className="text-muted-foreground mt-0.5 text-[11px]">{detail}</p>
                </div>
                <div className={`rounded-xl p-3 ${tone}`}>{icon}</div>
            </div>
        </div>
    );
}

export default function ReportsIndex({
    institutionName,
    activeReport,
    filters,
    filterOptions,
    parksReport,
    maintenanceReport,
    parkHistories,
    auditReport,
    workerReport,
    alertsReport,
    waterReport,
}: Props) {
    const hasInitialMaintenanceFilters = Object.entries(filters).some(([key, value]) =>
        key === 'date_basis' ? value !== 'operational' : Boolean(value),
    );
    const requestedTab = (Object.entries(exportType).find(([, type]) => type === activeReport)?.[0] as ReportTab | undefined) ?? null;
    const [activeTab, setActiveTab] = useState<ReportTab>(requestedTab ?? (hasInitialMaintenanceFilters ? 'maintenances' : 'general'));
    const [filterData, setFilterData] = useState<Filters>(filters);
    const initialPark = filterOptions.parks.find((park) => String(park.id) === filters.park_id);
    const [parkSearch, setParkSearch] = useState(initialPark ? `${initialPark.code ? `${initialPark.code} · ` : ''}${initialPark.name}` : '');
    const [parkSuggestionsOpen, setParkSuggestionsOpen] = useState(false);
    const [parkSearchError, setParkSearchError] = useState('');
    const [advancedFiltersOpen, setAdvancedFiltersOpen] = useState(
        Boolean(
            filters.date_from ||
                filters.date_to ||
                filters.park_id ||
                filters.worker_id ||
                filters.date_basis !== 'operational' ||
                filters.maintenance_origin ||
                filters.assignment ||
                filters.duration_min ||
                filters.duration_max,
        ),
    );
    const [historyParkId, setHistoryParkId] = useState<string>(parkHistories[0]?.id.toString() ?? '');
    const [historyParkSearch, setHistoryParkSearch] = useState(parkHistories[0] ? `${parkHistories[0].code} · ${parkHistories[0].name}` : '');
    const [historySuggestionsOpen, setHistorySuggestionsOpen] = useState(false);
    const [alertCategory, setAlertCategory] = useState<'upcoming' | 'due_today' | 'overdue' | 'in_progress' | 'up_to_date'>('overdue');

    const selectedHistory = parkHistories.find((park) => String(park.id) === historyParkId) ?? parkHistories[0];
    const filteredResultCount =
        activeTab === 'history'
            ? parkHistories.reduce((total, park) => total + park.records.length, 0)
            : activeTab === 'audit'
              ? auditReport.length
              : maintenanceReport.length;

    useEffect(() => {
        if (parkHistories.some((park) => String(park.id) === historyParkId)) return;

        const firstPark = parkHistories[0];
        setHistoryParkId(firstPark ? String(firstPark.id) : '');
        setHistoryParkSearch(firstPark ? `${firstPark.code} · ${firstPark.name}` : '');
    }, [historyParkId, parkHistories]);

    const exportQuery = useMemo(() => {
        const parameters = new URLSearchParams({ type: exportType[activeTab] });
        Object.entries(filters).forEach(([key, value]) => value && parameters.set(key, value));
        return parameters.toString();
    }, [activeTab, filters]);

    const parkSuggestions = useMemo(() => {
        const search = parkSearch.trim().toLocaleLowerCase('es');

        return filterOptions.parks
            .filter((park) => {
                if (!search) return true;
                return park.name.toLocaleLowerCase('es').includes(search) || (park.code ?? '').toLocaleLowerCase('es').includes(search);
            })
            .slice(0, 4);
    }, [filterOptions.parks, parkSearch]);

    const historyParkSuggestions = useMemo(() => {
        const search = historyParkSearch.trim().toLocaleLowerCase('es');
        const selectedLabel = selectedHistory ? `${selectedHistory.code} · ${selectedHistory.name}`.toLocaleLowerCase('es') : '';

        return parkHistories
            .filter((park) => {
                if (!search || search === selectedLabel) return true;
                return park.name.toLocaleLowerCase('es').includes(search) || park.code.toLocaleLowerCase('es').includes(search);
            })
            .slice(0, 4);
    }, [historyParkSearch, parkHistories, selectedHistory]);

    const selectPark = (park: ParkOption) => {
        setFilterData((current) => ({ ...current, park_id: String(park.id) }));
        setParkSearch(`${park.code ? `${park.code} · ` : ''}${park.name}`);
        setParkSearchError('');
        setParkSuggestionsOpen(false);
    };

    const selectHistoryPark = (park: ParkHistory) => {
        setHistoryParkId(String(park.id));
        setHistoryParkSearch(`${park.code} · ${park.name}`);
        setHistorySuggestionsOpen(false);
    };

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        let filtersToApply = filterData;

        if (parkSearch.trim() && !filterData.park_id) {
            const normalizedSearch = parkSearch.trim().toLocaleLowerCase('es');
            const match =
                filterOptions.parks.find(
                    (park) =>
                        park.name.toLocaleLowerCase('es') === normalizedSearch || (park.code ?? '').toLocaleLowerCase('es') === normalizedSearch,
                ) ?? (parkSuggestions.length === 1 ? parkSuggestions[0] : undefined);

            if (!match) {
                setParkSearchError('Selecciona una coincidencia para aplicar el parque.');
                setParkSuggestionsOpen(true);
                return;
            }

            filtersToApply = { ...filterData, park_id: String(match.id) };
            setFilterData(filtersToApply);
            setParkSearch(`${match.code ? `${match.code} · ` : ''}${match.name}`);
        }

        setParkSearchError('');
        router.get('/reportes', { ...filtersToApply, type: exportType[activeTab] }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const clearFilters = () => {
        const empty: Filters = {
            month: '',
            date_from: '',
            date_to: '',
            park_id: '',
            maintenance_type: '',
            worker_id: '',
            status: '',
            date_basis: 'operational',
            maintenance_origin: '',
            assignment: '',
            duration_min: '',
            duration_max: '',
        };
        setFilterData(empty);
        setParkSearch('');
        setParkSearchError('');
        setParkSuggestionsOpen(false);
        router.get('/reportes', { type: exportType[activeTab] }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const advancedFilterCount = [
        filterData.date_from,
        filterData.date_to,
        filterData.park_id,
        filterData.worker_id,
        filterData.date_basis !== 'operational' ? filterData.date_basis : '',
        filterData.maintenance_origin,
        filterData.assignment,
        filterData.duration_min,
        filterData.duration_max,
    ].filter(Boolean).length;

    const activeFilterCount = Object.entries(filterData).filter(([key, value]) =>
        key === 'date_basis' ? value !== 'operational' : Boolean(value),
    ).length;

    const formatLocalDate = (date: Date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const applyPeriodPreset = (preset: 'current_month' | 'previous_month' | 'current_year' | 'all') => {
        const now = new Date();
        const next: Filters = { ...filterData, month: '', date_from: '', date_to: '' };

        if (preset === 'current_month') {
            next.month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        } else if (preset === 'previous_month') {
            const previous = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            next.month = `${previous.getFullYear()}-${String(previous.getMonth() + 1).padStart(2, '0')}`;
        } else if (preset === 'current_year') {
            next.date_from = `${now.getFullYear()}-01-01`;
            next.date_to = formatLocalDate(new Date(now.getFullYear(), 11, 31));
        }

        setFilterData(next);
        router.get('/reportes', { ...next, type: exportType[activeTab] }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const alertItems = alertCategory === 'up_to_date' ? [] : alertsReport[alertCategory];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reportes - Sistema de Áreas Verdes" />

            <div className="page-shell">
                <div className="page-container">
                    <PageHeader
                        title="Centro de reportes"
                        description="Consulta, analiza y exporta la información operativa registrada en el sistema."
                        icon={BarChart3}
                        meta={<span className="text-muted-foreground text-[11.5px]">{institutionName}</span>}
                        actions={
                            <>
                                <Button asChild variant="outline" className="h-9 rounded-[9px]">
                                    <a href={`/reportes/exportar/csv?${exportQuery}`}>
                                        <FileSpreadsheet className="size-4" /> Excel (CSV)
                                    </a>
                                </Button>
                                <Button asChild variant="outline" className="h-9 rounded-[9px]">
                                    <a href={`/reportes/imprimir?${exportQuery}`} target="_blank" rel="noreferrer">
                                        <Printer className="size-4" /> Imprimir
                                    </a>
                                </Button>
                                <Button asChild className="h-9 rounded-[9px]">
                                    <a href={`/reportes/exportar/pdf?${exportQuery}`}>
                                        <Download className="size-4" /> Exportar PDF
                                    </a>
                                </Button>
                            </>
                        }
                    />

                    <SectionTabs
                        ariaLabel="Tipos de reporte"
                        className="surface px-2"
                        active={activeTab}
                        onChange={(id) => setActiveTab(id as ReportTab)}
                        items={tabs.map((tab) => ({ id: tab.id, label: tab.shortLabel, icon: tab.icon }))}
                    />

                    {activeTab === 'general' && (
                        <section className="surface min-w-0 overflow-hidden">
                            <div className="flex items-center justify-between border-b p-5">
                                <div>
                                    <h2 className="text-foreground font-bold">Inventario general de parques</h2>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Información consolidada de las áreas verdes registradas.</p>
                                </div>
                                <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    {parksReport.length} registros
                                </span>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="data-table min-w-[1100px]">
                                    <thead>
                                        <tr>
                                            <th className="px-4 py-3">Código</th>
                                            <th className="px-4 py-3">Denominación</th>
                                            <th className="px-4 py-3">Tipo</th>
                                            <th className="px-4 py-3">Ubicación</th>
                                            <th className="px-4 py-3 text-right">Área pública</th>
                                            <th className="px-4 py-3 text-right">Área verde</th>
                                            <th className="px-4 py-3">Punto de agua</th>
                                            <th className="px-4 py-3 text-center">Conservación</th>
                                            <th className="px-4 py-3 text-center">Registro</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border divide-y">
                                        {parksReport.map((park) => (
                                            <tr key={park.id} className="hover:bg-muted/25">
                                                <td className="px-4 py-3 font-mono font-semibold text-emerald-700 dark:text-emerald-400">
                                                    {park.code}
                                                </td>
                                                <td className="text-foreground px-4 py-3 font-semibold">{park.name}</td>
                                                <td className="px-4 py-3">{park.green_area_type}</td>
                                                <td className="px-4 py-3">
                                                    <span className="flex items-center gap-1.5">
                                                        <MapPin className="text-muted-foreground h-3.5 w-3.5" />
                                                        {park.location}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {park.public_use_area !== null ? `${park.public_use_area} m²` : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {park.green_area !== null ? `${park.green_area} m²` : '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {park.has_water_point ? `Sí · ${park.water_point_status ?? 'Sin estado'}` : 'No'}
                                                </td>
                                                <td className="px-4 py-3 text-center capitalize">{park.conservation_status}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`rounded-full px-2 py-1 font-semibold ${park.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'}`}
                                                    >
                                                        {park.is_active ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    {(activeTab === 'maintenances' || activeTab === 'history' || activeTab === 'audit') && (
                        <form onSubmit={applyFilters} className="bg-card rounded-2xl border p-5 shadow-xs">
                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div className="flex items-start gap-2.5">
                                    <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                        <Search className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-foreground text-sm font-bold">
                                                {activeTab === 'history'
                                                    ? 'Filtrar historial'
                                                    : activeTab === 'audit'
                                                      ? 'Filtrar auditoría'
                                                      : 'Explorar mantenimientos'}
                                            </h2>
                                            {activeFilterCount > 0 && (
                                                <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                    {activeFilterCount} {activeFilterCount === 1 ? 'filtro activo' : 'filtros activos'}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                            {activeTab === 'history'
                                                ? 'Combina criterios para reconstruir la trazabilidad que necesitas.'
                                                : activeTab === 'audit'
                                                  ? 'Consulta los motivos de retraso por periodo, parque, tipo, responsable o estado.'
                                                  : 'Combina periodo, tipo y estado; amplía solo cuando lo necesites.'}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setAdvancedFiltersOpen((open) => !open)}
                                    aria-expanded={advancedFiltersOpen}
                                    className="hover:bg-muted inline-flex h-9 items-center justify-center gap-2 rounded-xl border px-3 text-[11px] font-semibold transition"
                                >
                                    <SlidersHorizontal className="size-3.5" /> Filtros avanzados
                                    {advancedFilterCount > 0 && (
                                        <span className="grid size-4 place-items-center rounded-full bg-emerald-600 text-[9px] text-white">
                                            {advancedFilterCount}
                                        </span>
                                    )}
                                    <ChevronDown className={`size-3.5 transition ${advancedFiltersOpen ? 'rotate-180' : ''}`} />
                                </button>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-2 dark:bg-neutral-900">
                                <span className="text-muted-foreground flex items-center gap-1.5 px-1 text-[10px] font-semibold">
                                    <CalendarRange className="size-3.5" /> Periodos rápidos
                                </span>
                                {[
                                    ['current_month', 'Este mes'],
                                    ['previous_month', 'Mes anterior'],
                                    ['current_year', 'Este año'],
                                    ['all', 'Todo el histórico'],
                                ].map(([preset, label]) => (
                                    <button
                                        key={preset}
                                        type="button"
                                        onClick={() => applyPeriodPreset(preset as 'current_month' | 'previous_month' | 'current_year' | 'all')}
                                        className="bg-background rounded-lg border px-2.5 py-1.5 text-[10px] font-semibold transition hover:border-emerald-300 hover:text-emerald-700"
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>

                            <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                <label className="text-xs font-semibold">
                                    Mes de análisis
                                    <input
                                        type="month"
                                        value={filterData.month}
                                        onChange={(event) => setFilterData({ ...filterData, month: event.target.value, date_from: '', date_to: '' })}
                                        className="field"
                                    />
                                </label>
                                <label className="text-xs font-semibold">
                                    Tipo de mantenimiento
                                    <select
                                        value={filterData.maintenance_type}
                                        onChange={(event) => setFilterData({ ...filterData, maintenance_type: event.target.value })}
                                        className="field"
                                    >
                                        <option value="">Todos los tipos</option>
                                        {filterOptions.maintenanceTypes.map((type) => (
                                            <option key={type} value={type}>
                                                {type}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="text-xs font-semibold">
                                    Estado operativo
                                    <select
                                        value={filterData.status}
                                        onChange={(event) => setFilterData({ ...filterData, status: event.target.value })}
                                        className="field"
                                    >
                                        <option value="">Todos los estados</option>
                                        <option value="proximo">Próximo</option>
                                        <option value="pendiente">Pendiente hoy</option>
                                        <option value="vencido">Vencido</option>
                                        <option value="en_proceso">En proceso</option>
                                        <option value="finalizado">Finalizado</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                </label>
                            </div>

                            {advancedFiltersOpen && (
                                <div className="mt-5 border-t pt-5">
                                    <div className="mb-3">
                                        <p className="text-xs font-bold">Criterios avanzados</p>
                                        <p className="text-muted-foreground mt-0.5 text-[10px]">
                                            Los criterios se aplican conjuntamente para segmentar el resultado.
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <label className="text-xs font-semibold">
                                            Base de fecha
                                            <select
                                                value={filterData.date_basis}
                                                onChange={(event) =>
                                                    setFilterData({ ...filterData, date_basis: event.target.value as Filters['date_basis'] })
                                                }
                                                className="field"
                                            >
                                                <option value="operational">Realizada; si falta, programada</option>
                                                <option value="scheduled">Solo fecha programada</option>
                                                <option value="executed">Solo fecha realizada</option>
                                            </select>
                                        </label>
                                        <label className="text-xs font-semibold">
                                            Desde
                                            <input
                                                type="date"
                                                value={filterData.date_from}
                                                onChange={(event) => setFilterData({ ...filterData, month: '', date_from: event.target.value })}
                                                className="field"
                                            />
                                        </label>
                                        <label className="text-xs font-semibold">
                                            Hasta
                                            <input
                                                type="date"
                                                value={filterData.date_to}
                                                onChange={(event) => setFilterData({ ...filterData, month: '', date_to: event.target.value })}
                                                className="field"
                                            />
                                        </label>
                                        <div className="relative text-xs font-semibold">
                                            <label htmlFor="park-filter">Parque</label>
                                            <div className="relative">
                                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                                <input
                                                    id="park-filter"
                                                    type="search"
                                                    role="combobox"
                                                    autoComplete="off"
                                                    aria-expanded={parkSuggestionsOpen}
                                                    aria-controls="park-filter-options"
                                                    placeholder="Escribe código o nombre⬦"
                                                    value={parkSearch}
                                                    onFocus={() => setParkSuggestionsOpen(true)}
                                                    onBlur={() => window.setTimeout(() => setParkSuggestionsOpen(false), 120)}
                                                    onChange={(event) => {
                                                        const value = event.target.value;
                                                        const normalized = value.trim().toLocaleLowerCase('es');
                                                        const exact = filterOptions.parks.find(
                                                            (park) =>
                                                                park.name.toLocaleLowerCase('es') === normalized ||
                                                                (park.code ?? '').toLocaleLowerCase('es') === normalized,
                                                        );
                                                        setParkSearch(value);
                                                        setFilterData({ ...filterData, park_id: exact ? String(exact.id) : '' });
                                                        setParkSearchError('');
                                                        setParkSuggestionsOpen(true);
                                                    }}
                                                    className={`field pr-9 pl-9 ${parkSearchError ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : ''}`}
                                                />
                                                {parkSearch && (
                                                    <button
                                                        type="button"
                                                        onMouseDown={(event) => event.preventDefault()}
                                                        onClick={() => {
                                                            setParkSearch('');
                                                            setFilterData({ ...filterData, park_id: '' });
                                                            setParkSearchError('');
                                                        }}
                                                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-0.5"
                                                        aria-label="Quitar parque seleccionado"
                                                    >
                                                        <X className="size-3.5" />
                                                    </button>
                                                )}
                                            </div>

                                            {parkSuggestionsOpen && (
                                                <div
                                                    id="park-filter-options"
                                                    role="listbox"
                                                    className="bg-popover text-popover-foreground absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border p-1.5 shadow-xl"
                                                >
                                                    {parkSuggestions.length > 0 ? (
                                                        parkSuggestions.map((park) => (
                                                            <button
                                                                key={park.id}
                                                                type="button"
                                                                role="option"
                                                                aria-selected={filterData.park_id === String(park.id)}
                                                                onMouseDown={(event) => event.preventDefault()}
                                                                onClick={() => selectPark(park)}
                                                                className="hover:bg-muted flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left font-normal transition"
                                                            >
                                                                <span className="min-w-16 font-mono text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">
                                                                    {park.code ?? 'S/C'}
                                                                </span>
                                                                <span className="truncate text-[11px]">{park.name}</span>
                                                            </button>
                                                        ))
                                                    ) : (
                                                        <p className="text-muted-foreground px-3 py-4 text-center text-[10px] font-normal">
                                                            No se encontraron parques con ese criterio.
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                            {parkSearchError && <p className="mt-1 text-[10px] font-normal text-rose-600">{parkSearchError}</p>}
                                        </div>
                                        <label className="text-xs font-semibold">
                                            Trabajador
                                            <select
                                                value={filterData.worker_id}
                                                onChange={(event) => setFilterData({ ...filterData, worker_id: event.target.value })}
                                                className="field"
                                            >
                                                <option value="">Todos los trabajadores</option>
                                                {filterOptions.workers.map((worker) => (
                                                    <option key={worker.id} value={worker.id}>
                                                        {worker.full_name}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <label className="text-xs font-semibold">
                                            Origen
                                            <select
                                                value={filterData.maintenance_origin}
                                                onChange={(event) => setFilterData({ ...filterData, maintenance_origin: event.target.value })}
                                                className="field"
                                            >
                                                <option value="">Programados y adicionales</option>
                                                <option value="ordinary">Solo programados</option>
                                                <option value="extraordinary">Solo adicionales</option>
                                            </select>
                                        </label>
                                        <label className="text-xs font-semibold">
                                            Asignación
                                            <select
                                                value={filterData.assignment}
                                                onChange={(event) => setFilterData({ ...filterData, assignment: event.target.value })}
                                                className="field"
                                            >
                                                <option value="">Asignados y sin asignar</option>
                                                <option value="assigned">Con responsable</option>
                                                <option value="unassigned">Sin responsable</option>
                                            </select>
                                        </label>
                                        <div className="grid grid-cols-2 gap-2">
                                            <label className="text-xs font-semibold">
                                                Duración mín.
                                                <input
                                                    type="number"
                                                    min="0"
                                                    placeholder="Minutos"
                                                    value={filterData.duration_min}
                                                    onChange={(event) => setFilterData({ ...filterData, duration_min: event.target.value })}
                                                    className="field"
                                                />
                                            </label>
                                            <label className="text-xs font-semibold">
                                                Duración máx.
                                                <input
                                                    type="number"
                                                    min="0"
                                                    placeholder="Minutos"
                                                    value={filterData.duration_max}
                                                    onChange={(event) => setFilterData({ ...filterData, duration_max: event.target.value })}
                                                    className="field"
                                                />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <div className="mt-4 flex flex-col justify-between gap-3 border-t pt-4 sm:flex-row sm:items-center">
                                <p className="text-muted-foreground text-[10px]">
                                    {activeFilterCount > 0
                                        ? `${filteredResultCount} registros coinciden con la combinación actual.`
                                        : `Sin filtros: se muestran todos los ${
                                              activeTab === 'history'
                                                  ? 'registros históricos'
                                                  : activeTab === 'audit'
                                                    ? 'motivos registrados'
                                                    : 'mantenimientos'
                                          } registrados.`}
                                </p>
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        onClick={clearFilters}
                                        className="hover:bg-muted inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-xs font-semibold"
                                    >
                                        <RotateCcw className="h-3.5 w-3.5" /> Limpiar
                                    </button>
                                    <button
                                        type="submit"
                                        className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                                    >
                                        <Search className="h-3.5 w-3.5" /> Consultar
                                    </button>
                                </div>
                            </div>
                        </form>
                    )}

                    {activeTab === 'maintenances' && (
                        <section className="surface min-w-0 overflow-hidden">
                            <div className="flex items-center justify-between border-b p-5">
                                <div>
                                    <h2 className="text-foreground font-bold">Resultados de mantenimientos</h2>
                                    <p className="text-muted-foreground text-xs">Órdenes que coinciden con los criterios aplicados.</p>
                                </div>
                                <span className="text-muted-foreground text-xs font-semibold">{maintenanceReport.length} resultados</span>
                            </div>
                            {maintenanceReport.length ? (
                                <div className="overflow-x-auto">
                                    <table className="data-table min-w-[950px]">
                                        <thead>
                                            <tr>
                                                <th className="px-4 py-3">Programada</th>
                                                <th className="px-4 py-3">Realizada</th>
                                                <th className="px-4 py-3">Parque</th>
                                                <th className="px-4 py-3">Tipo</th>
                                                <th className="px-4 py-3">Trabajador</th>
                                                <th className="px-4 py-3">Duración</th>
                                                <th className="px-4 py-3 text-center">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {maintenanceReport.map((item) => (
                                                <tr key={item.id} className="hover:bg-muted/25">
                                                    <td className="px-4 py-3">{item.scheduled_date ?? '—'}</td>
                                                    <td className="px-4 py-3 font-medium">{item.execution_date ?? '—'}</td>
                                                    <td className="px-4 py-3">
                                                        <span className="text-foreground font-semibold">{item.park_name}</span>
                                                        <span className="text-muted-foreground block font-mono text-[10px]">{item.park_code}</span>
                                                    </td>
                                                    <td className="px-4 py-3">{item.maintenance_type}</td>
                                                    <td className="px-4 py-3">{item.worker_name}</td>
                                                    <td className="px-4 py-3">
                                                        <span className="inline-flex items-center gap-1">
                                                            <Clock className="text-muted-foreground h-3.5 w-3.5" />
                                                            {item.duration_label}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <StatusBadge status={item.status} label={item.status_label} />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <EmptyState
                                    icon={<Search className="h-6 w-6" />}
                                    title="Sin resultados"
                                    description="Modifique los filtros para consultar otro periodo o criterio."
                                />
                            )}
                        </section>
                    )}

                    {activeTab === 'audit' && (
                        <section className="surface min-w-0 overflow-hidden">
                            <div className="flex flex-col justify-between gap-2 border-b p-5 sm:flex-row sm:items-center">
                                <div>
                                    <h2 className="text-foreground flex items-center gap-2 font-bold">
                                        <ShieldCheck className="size-4 text-rose-600" /> Motivos de retraso registrados
                                    </h2>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        Motivos registrados antes de iniciar o atender una orden vencida.
                                    </p>
                                </div>
                                <span className="text-muted-foreground text-xs font-semibold">{auditReport.length} eventos</span>
                            </div>
                            {auditReport.length ? (
                                <div className="overflow-x-auto">
                                    <table className="data-table min-w-[1080px]">
                                        <thead>
                                            <tr>
                                                <th className="px-4 py-3">Registrado</th>
                                                <th className="px-4 py-3">Orden / parque</th>
                                                <th className="px-4 py-3">Tipo</th>
                                                <th className="px-4 py-3">Programada</th>
                                                <th className="px-4 py-3 text-center">Retraso</th>
                                                <th className="w-[32%] px-4 py-3">Motivo registrado</th>
                                                <th className="px-4 py-3">Responsable del registro</th>
                                                <th className="px-4 py-3 text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {auditReport.map((item) => (
                                                <tr key={item.id} className="hover:bg-muted/25 align-top">
                                                    <td className="px-4 py-3 whitespace-nowrap">{item.recorded_at ?? '—'}</td>
                                                    <td className="px-4 py-3">
                                                        <span className="font-mono text-[10px] font-bold text-rose-700 dark:text-rose-400">
                                                            {item.maintenance_number}
                                                        </span>
                                                        <span className="text-foreground mt-0.5 block font-semibold">{item.park_name}</span>
                                                        <span className="text-muted-foreground block font-mono text-[10px]">{item.park_code}</span>
                                                    </td>
                                                    <td className="px-4 py-3">{item.maintenance_type}</td>
                                                    <td className="px-4 py-3 whitespace-nowrap">{item.scheduled_date ?? '—'}</td>
                                                    <td className="px-4 py-3 text-center">
                                                        <span className="inline-flex rounded-full bg-rose-100 px-2.5 py-1 font-bold text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                                            {item.overdue_days} {item.overdue_days === 1 ? 'día' : 'días'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 leading-relaxed">
                                                        <p>{item.reason}</p>
                                                        {item.document_url && (
                                                            <a
                                                                href={item.document_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 hover:underline dark:text-emerald-400"
                                                            >
                                                                <FileText className="size-3.5 shrink-0" />
                                                                {item.document_name ?? 'Ver documento'}
                                                            </a>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">{item.recorded_by}</td>
                                                    <td className="px-4 py-3 text-center capitalize">{item.action}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <EmptyState
                                    icon={<ShieldCheck className="h-6 w-6" />}
                                    title="Sin motivos registrados"
                                    description="No existen motivos de retraso que coincidan con los filtros aplicados."
                                />
                            )}
                        </section>
                    )}

                    {activeTab === 'history' && (
                        <div className="space-y-5">
                            <section className="bg-card rounded-2xl border p-5 shadow-xs">
                                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                                    <div>
                                        <h2 className="text-foreground font-bold">Historial por parque</h2>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Consulta la trazabilidad completa de las labores realizadas y programadas.
                                        </p>
                                    </div>
                                    <label className="w-full text-xs font-semibold sm:max-w-sm">
                                        Navegar entre parques filtrados
                                        <div className="relative">
                                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                            <input
                                                type="search"
                                                role="combobox"
                                                autoComplete="off"
                                                aria-expanded={historySuggestionsOpen}
                                                aria-controls="history-park-options"
                                                value={historyParkSearch}
                                                placeholder="Escribe código o nombre⬦"
                                                onFocus={() => setHistorySuggestionsOpen(true)}
                                                onBlur={() => window.setTimeout(() => setHistorySuggestionsOpen(false), 120)}
                                                onChange={(event) => {
                                                    const value = event.target.value;
                                                    setHistoryParkSearch(value);
                                                    setHistorySuggestionsOpen(true);
                                                }}
                                                className="field pr-9 pl-9"
                                            />
                                            {historyParkSearch && (
                                                <button
                                                    type="button"
                                                    onMouseDown={(event) => event.preventDefault()}
                                                    onClick={() => {
                                                        setHistoryParkSearch('');
                                                        setHistorySuggestionsOpen(true);
                                                    }}
                                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-0.5"
                                                    aria-label="Limpiar búsqueda de historial"
                                                >
                                                    <X className="size-3.5" />
                                                </button>
                                            )}
                                            {historySuggestionsOpen && (
                                                <div
                                                    id="history-park-options"
                                                    role="listbox"
                                                    className="bg-popover text-popover-foreground absolute z-30 mt-1 w-full overflow-hidden rounded-xl border p-1.5 shadow-xl"
                                                >
                                                    {historyParkSuggestions.length > 0 ? (
                                                        historyParkSuggestions.map((park) => (
                                                            <button
                                                                key={park.id}
                                                                type="button"
                                                                role="option"
                                                                aria-selected={historyParkId === String(park.id)}
                                                                onMouseDown={(event) => event.preventDefault()}
                                                                onClick={() => selectHistoryPark(park)}
                                                                className="hover:bg-muted flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left font-normal transition"
                                                            >
                                                                <span className="min-w-16 font-mono text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">
                                                                    {park.code}
                                                                </span>
                                                                <span className="truncate text-[11px]">{park.name}</span>
                                                            </button>
                                                        ))
                                                    ) : (
                                                        <p className="text-muted-foreground px-3 py-4 text-center text-[10px] font-normal">
                                                            No hay parques que coincidan.
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                        <span className="text-muted-foreground mt-1 block text-[9px] font-normal">
                                            {parkHistories.length} {parkHistories.length === 1 ? 'parque coincide' : 'parques coinciden'} con los
                                            filtros.
                                        </span>
                                    </label>
                                </div>
                            </section>
                            {selectedHistory ? (
                                <section className="surface min-w-0 overflow-hidden">
                                    <div className="border-b p-5">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p className="font-mono text-xs font-semibold text-emerald-600">{selectedHistory.code}</p>
                                                <h2 className="text-foreground text-lg font-bold">{selectedHistory.name}</h2>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-foreground text-2xl font-bold">{selectedHistory.maintenance_count}</p>
                                                <p className="text-muted-foreground text-[11px]">mantenimientos registrados</p>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-1.5">
                                            {selectedHistory.maintenance_types.map((type) => (
                                                <span
                                                    key={type}
                                                    className="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                                >
                                                    {type}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                    {selectedHistory.records.length ? (
                                        <div className="overflow-x-auto">
                                            <table className="data-table min-w-[1050px]">
                                                <thead>
                                                    <tr>
                                                        <th className="px-4 py-3">Tipo</th>
                                                        <th className="px-4 py-3">Fechas</th>
                                                        <th className="px-4 py-3">Trabajador</th>
                                                        <th className="px-4 py-3">Horario</th>
                                                        <th className="px-4 py-3">Observaciones</th>
                                                        <th className="px-4 py-3">Documento</th>
                                                        <th className="px-4 py-3 text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-border divide-y">
                                                    {selectedHistory.records.map((record) => (
                                                        <tr key={record.id} className="hover:bg-muted/25">
                                                            <td className="px-4 py-3 font-semibold">{record.maintenance_type}</td>
                                                            <td className="px-4 py-3">
                                                                <span className="block">Programada: {record.scheduled_date ?? '—'}</span>
                                                                <span className="text-muted-foreground block">
                                                                    Realizada: {record.execution_date ?? '—'}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3">{record.worker_name}</td>
                                                            <td className="px-4 py-3">{record.schedule}</td>
                                                            <td className="max-w-xs px-4 py-3">{record.observations}</td>
                                                            <td className="px-4 py-3">
                                                                {record.document_url ? (
                                                                    <a
                                                                        href={record.document_url}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="inline-flex items-center gap-1 font-semibold text-emerald-700 hover:underline"
                                                                    >
                                                                        <FileText className="h-3.5 w-3.5" />
                                                                        {record.document_name ?? 'Abrir'}
                                                                    </a>
                                                                ) : (
                                                                    <span className="text-muted-foreground">Sin documento</span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 text-center">
                                                                <StatusBadge status={record.status} label={record.status_label} />
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <EmptyState
                                            icon={<History className="h-6 w-6" />}
                                            title="Sin historial"
                                            description="Este parque todavía no tiene mantenimientos registrados."
                                        />
                                    )}
                                </section>
                            ) : (
                                <EmptyState
                                    icon={<Trees className="h-6 w-6" />}
                                    title="Sin parques"
                                    description="Registre un parque para consultar su historial."
                                />
                            )}
                        </div>
                    )}

                    {activeTab === 'workers' && (
                        <section className="surface min-w-0 overflow-hidden">
                            <div className="border-b p-5">
                                <h2 className="text-foreground font-bold">Actividad por trabajador</h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Resumen referencial de labores. Esta información no constituye control de asistencia, planilla ni cálculo de
                                    pagos.
                                </p>
                            </div>
                            {workerReport.length ? (
                                <div className="overflow-x-auto">
                                    <table className="data-table min-w-[1050px]">
                                        <thead>
                                            <tr>
                                                <th className="px-4 py-3">Trabajador</th>
                                                <th className="px-4 py-3 text-center">Mantenimientos</th>
                                                <th className="px-4 py-3">Parques atendidos</th>
                                                <th className="px-4 py-3">Tipos de trabajo</th>
                                                <th className="px-4 py-3 text-center">Horas registradas</th>
                                                <th className="px-4 py-3 text-center">Finalizados</th>
                                                <th className="px-4 py-3 text-center">Cancelados</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {workerReport.map((worker) => (
                                                <tr key={worker.id} className="hover:bg-muted/25">
                                                    <td className="px-4 py-3">
                                                        <span className="text-foreground font-semibold">{worker.full_name}</span>
                                                        <span className="text-muted-foreground block text-[11px]">
                                                            {worker.code} · {worker.position}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-center text-base font-bold">{worker.maintenance_count}</td>
                                                    <td className="max-w-xs px-4 py-3">{worker.parks.length ? worker.parks.join(', ') : '—'}</td>
                                                    <td className="max-w-xs px-4 py-3">
                                                        {worker.work_types.length ? worker.work_types.join(', ') : '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-center font-semibold">{worker.hours} h</td>
                                                    <td className="px-4 py-3 text-center text-emerald-700">{worker.completed_count}</td>
                                                    <td className="px-4 py-3 text-center text-slate-600">{worker.cancelled_count}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <EmptyState
                                    icon={<Users className="h-6 w-6" />}
                                    title="Sin trabajadores"
                                    description="Registre trabajadores para generar este reporte."
                                />
                            )}
                        </section>
                    )}

                    {activeTab === 'alerts' && (
                        <div className="space-y-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                                <button type="button" onClick={() => setAlertCategory('up_to_date')}>
                                    <SummaryCard
                                        label="Parques al día"
                                        value={alertsReport.counts.up_to_date}
                                        detail="Sin atención vencida"
                                        icon={<CheckCircle2 className="h-5 w-5" />}
                                        tone="bg-emerald-100 text-emerald-700"
                                    />
                                </button>
                                <button type="button" onClick={() => setAlertCategory('upcoming')}>
                                    <SummaryCard
                                        label="Próximos"
                                        value={alertsReport.counts.upcoming}
                                        detail="Programados a futuro"
                                        icon={<CalendarDays className="h-5 w-5" />}
                                        tone="bg-violet-100 text-violet-700"
                                    />
                                </button>
                                <button type="button" onClick={() => setAlertCategory('due_today')}>
                                    <SummaryCard
                                        label="Pendientes hoy"
                                        value={alertsReport.counts.due_today}
                                        detail="Requieren atención"
                                        icon={<Clock className="h-5 w-5" />}
                                        tone="bg-amber-100 text-amber-700"
                                    />
                                </button>
                                <button type="button" onClick={() => setAlertCategory('overdue')}>
                                    <SummaryCard
                                        label="Vencidos"
                                        value={alertsReport.counts.overdue}
                                        detail="Fuera de fecha"
                                        icon={<AlertTriangle className="h-5 w-5" />}
                                        tone="bg-red-100 text-red-700"
                                    />
                                </button>
                                <button type="button" onClick={() => setAlertCategory('in_progress')}>
                                    <SummaryCard
                                        label="En proceso"
                                        value={alertsReport.counts.in_progress}
                                        detail="Labores en curso"
                                        icon={<PlayCircle className="h-5 w-5" />}
                                        tone="bg-blue-100 text-blue-700"
                                    />
                                </button>
                            </div>
                            <section className="surface min-w-0 overflow-hidden">
                                <div className="border-b p-5">
                                    <h2 className="text-foreground font-bold capitalize">
                                        Detalle:{' '}
                                        {alertCategory === 'up_to_date'
                                            ? 'parques al día'
                                            : alertCategory === 'due_today'
                                              ? 'pendientes hoy'
                                              : alertCategory === 'in_progress'
                                                ? 'en proceso'
                                                : alertCategory === 'upcoming'
                                                  ? 'próximos'
                                                  : 'vencidos'}
                                    </h2>
                                    <p className="text-muted-foreground text-xs">Seleccione una tarjeta para consultar su detalle.</p>
                                </div>
                                {alertCategory === 'up_to_date' ? (
                                    alertsReport.up_to_date_parks.length ? (
                                        <div className="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                                            {alertsReport.up_to_date_parks.map((park) => (
                                                <div key={park.id} className="rounded-xl border bg-emerald-50/50 p-3 dark:bg-emerald-950/20">
                                                    <p className="text-foreground font-semibold">{park.name}</p>
                                                    <p className="font-mono text-[11px] text-emerald-700">{park.code}</p>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <EmptyState
                                            icon={<CheckCircle2 className="h-6 w-6" />}
                                            title="Sin parques al día"
                                            description="Todos los parques registrados tienen alguna atención pendiente."
                                        />
                                    )
                                ) : alertItems.length ? (
                                    <div className="overflow-x-auto">
                                        <table className="data-table min-w-[720px]">
                                            <thead>
                                                <tr>
                                                    <th className="px-4 py-3">Orden</th>
                                                    <th className="px-4 py-3">Parque</th>
                                                    <th className="px-4 py-3">Tipo</th>
                                                    <th className="px-4 py-3">Fecha programada</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-border divide-y">
                                                {alertItems.map((item) => (
                                                    <tr key={item.id}>
                                                        <td className="px-4 py-3 font-mono font-semibold">{item.maintenance_number}</td>
                                                        <td className="px-4 py-3 font-semibold">{item.park_name}</td>
                                                        <td className="px-4 py-3">{item.maintenance_type}</td>
                                                        <td className="px-4 py-3">{item.scheduled_date ?? '—'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={<BellRing className="h-6 w-6" />}
                                        title="Sin alertas en esta categoría"
                                        description="No existen mantenimientos que requieran mostrarse aquí."
                                    />
                                )}
                            </section>
                        </div>
                    )}

                    {activeTab === 'water' && (
                        <div className="space-y-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                                <SummaryCard
                                    label="Con punto de agua"
                                    value={waterReport.counts.with_water}
                                    detail="Parques abastecidos"
                                    icon={<Droplets className="h-5 w-5" />}
                                    tone="bg-blue-100 text-blue-700"
                                />
                                <SummaryCard
                                    label="Sin punto de agua"
                                    value={waterReport.counts.without_water}
                                    detail="Requieren alternativa"
                                    icon={<Droplets className="h-5 w-5" />}
                                    tone="bg-slate-100 text-slate-600"
                                />
                                <SummaryCard
                                    label="Operativos"
                                    value={waterReport.counts.operational}
                                    detail="Puntos funcionales"
                                    icon={<CheckCircle2 className="h-5 w-5" />}
                                    tone="bg-emerald-100 text-emerald-700"
                                />
                                <SummaryCard
                                    label="Con observaciones"
                                    value={waterReport.counts.with_observations}
                                    detail="Requieren revisión"
                                    icon={<FileText className="h-5 w-5" />}
                                    tone="bg-amber-100 text-amber-700"
                                />
                                <SummaryCard
                                    label="No operativos"
                                    value={waterReport.counts.non_operational}
                                    detail="Requieren atención"
                                    icon={<AlertTriangle className="h-5 w-5" />}
                                    tone="bg-red-100 text-red-700"
                                />
                            </div>
                            <section className="surface min-w-0 overflow-hidden">
                                <div className="border-b p-5">
                                    <h2 className="text-foreground font-bold">Estado de los puntos de agua</h2>
                                    <p className="text-muted-foreground text-xs">
                                        Disponibilidad y condición del abastecimiento registrado por parque.
                                    </p>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="data-table min-w-[850px]">
                                        <thead>
                                            <tr>
                                                <th className="px-4 py-3">Código</th>
                                                <th className="px-4 py-3">Parque</th>
                                                <th className="px-4 py-3 text-center">Punto de agua</th>
                                                <th className="px-4 py-3">Abastecimiento</th>
                                                <th className="px-4 py-3">Estado</th>
                                                <th className="px-4 py-3">Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {waterReport.parks.map((park) => (
                                                <tr key={park.id} className="hover:bg-muted/25">
                                                    <td className="px-4 py-3 font-mono font-semibold text-emerald-700">{park.code}</td>
                                                    <td className="px-4 py-3 font-semibold">{park.name}</td>
                                                    <td className="px-4 py-3 text-center">{park.has_water_point ? 'Sí' : 'No'}</td>
                                                    <td className="px-4 py-3">{park.water_supply_type ?? '—'}</td>
                                                    <td className="px-4 py-3 capitalize">{park.water_point_status ?? '—'}</td>
                                                    <td className="max-w-sm px-4 py-3">{park.observations ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
