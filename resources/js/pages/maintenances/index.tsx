import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SectionTabs } from '@/components/section-tabs';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Calendar,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Clock,
    Droplets,
    Eye,
    FileCheck2,
    FileText,
    Layers,
    LoaderCircle,
    Paperclip,
    Play,
    Printer,
    RefreshCw,
    Search,
    ShieldCheck,
    Sparkles,
    Trash2,
    Trees,
    Upload,
    UserCheck,
    Wrench,
    X,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';

// Tipos de datos
export interface MaintenanceItem {
    id: number;
    maintenance_number: string;
    park_id: number;
    park_name: string;
    park_code: string;
    green_area_type: string;
    park_zone: string;
    park_address: string;
    maintenance_type: string;
    is_extraordinary: boolean;
    reason?: string | null;
    frequency_days: number | null;
    scheduled_date: string | null;
    scheduled_date_formatted: string;
    alert_date: string | null;
    alert_date_formatted: string | null;
    advance_days: number;
    is_alert_active: boolean;
    execution_date: string | null;
    execution_date_formatted?: string | null;
    worker_id: number | null;
    responsible_worker: string;
    start_time: string | null;
    end_time?: string | null;
    duration_minutes?: number | null;
    status: string;
    computed_status: string;
    initial_observation?: string | null;
    work_description?: string | null;
    result?: string | null;
    photo_before_url?: string | null;
    photo_after_url?: string | null;
    observations?: string | null;
    document_name?: string | null;
    document_url?: string | null;
}

export interface ParkOption {
    id: number;
    code: string;
    name: string;
    green_area_type: string;
    address: string;
    zone: string;
    schedules?: Array<{
        id: number;
        maintenance_type: string;
        frequency_days: number;
        next_scheduled_date: string;
    }>;
}

export interface RuleOption {
    id: number;
    green_area_type: string;
    maintenance_type: string;
    frequency_days: number;
}

export interface WorkerOption {
    id: number;
    full_name: string;
    position: string;
    crew: string | null;
}

interface Props {
    records: {
        data: MaintenanceItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        vista: 'operativa' | 'historial';
        estado: string;
        search: string;
        park_id: string;
        maintenance_type: string;
    };
    counts: {
        todos: number;
        proximos: number;
        pendientes: number;
        vencidos: number;
        en_proceso: number;
        finalizados: number;
        cancelados: number;
    };
    parks: ParkOption[];
    rules: RuleOption[];
    tiposMantenimiento: string[];
    workers: WorkerOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Mantenimientos', href: '/mantenimientos' },
];

const STATUS_META: Record<string, { label: string; className: string; dot: string }> = {
    al_dia: {
        label: 'Al día',
        className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
    proximo: {
        label: 'Próximo',
        className: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
        dot: 'bg-sky-500',
    },
    pendiente: {
        label: 'Pendiente',
        className: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    vencido: {
        label: 'Vencido',
        className: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
        dot: 'bg-rose-500',
    },
    en_proceso: {
        label: 'En mantenimiento',
        className: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
        dot: 'bg-sky-500 animate-pulse',
    },
    finalizado: {
        label: 'Finalizado',
        className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
    cancelado: {
        label: 'Cancelado',
        className: 'bg-muted text-muted-foreground',
        dot: 'bg-muted-foreground/60',
    },
};

export default function MaintenanceIndex({ records, filters, counts, parks, tiposMantenimiento, workers }: Props) {
    // El texto conserva respuesta inmediata; el resto de filtros viene de la URL/servidor.
    const [busqueda, setBusqueda] = useState(filters.search ?? '');
    const [consultaParque, setConsultaParque] = useState('');

    // Modales
    const [modalExtraordinarioAbierto, setModalExtraordinarioAbierto] = useState(false);
    const [modalAtenderAbierto, setModalAtenderAbierto] = useState(false);
    const [modalIniciarAbierto, setModalIniciarAbierto] = useState(false);
    const [modalFinalizarAbierto, setModalFinalizarAbierto] = useState(false);
    const [modalDetalleAbierto, setModalDetalleAbierto] = useState(false);
    const [modalCancelarAbierto, setModalCancelarAbierto] = useState(false);

    // Paso previo obligatorio para órdenes vencidas
    const [modalJustificacionAbierto, setModalJustificacionAbierto] = useState(false);
    const [destinoJustificacion, setDestinoJustificacion] = useState<'atender' | 'iniciar'>('atender');
    const [justificacionMotivo, setJustificacionMotivo] = useState('');
    const [justificacionDocumento, setJustificacionDocumento] = useState<File | null>(null);
    const [justificacionError, setJustificacionError] = useState<string | null>(null);
    const justificacionArchivoRef = useRef<HTMLInputElement>(null);

    // Registro en gestión
    const [mantenimientoSeleccionado, setMantenimientoSeleccionado] = useState<MaintenanceItem | null>(null);
    const [registroCargandoId, setRegistroCargandoId] = useState<number | null>(null);
    const [errorCargaRegistro, setErrorCargaRegistro] = useState<string | null>(null);

    // Formulario de Mantenimiento Extraordinario
    const formExtraordinario = useForm({
        park_id: parks.length > 0 ? parks[0].id : '',
        maintenance_type: '',
        scheduled_date: new Date().toISOString().split('T')[0],
        advance_days: 1,
        reason: '',
        worker_id: '',
        observations: '',
    });

    // Formulario de Atender Mantenimiento Completo
    const formAtender = useForm({
        worker_id: '',
        execution_date: new Date().toISOString().split('T')[0],
        start_time: '08:00',
        end_time: '10:30',
        work_description: '',
        result: 'Óptimo',
        observations: '',
        overdue_reason: '',
        overdue_document: null as File | null,
        status: 'finalizado',
        photo_before: null as File | null,
        photo_after: null as File | null,
    });

    // Formulario de Iniciar Mantenimiento
    const formIniciar = useForm({
        execution_date: new Date().toISOString().split('T')[0],
        start_time: new Date().toTimeString().substring(0, 5),
        worker_id: '',
        overdue_reason: '',
        overdue_document: null as File | null,
        initial_observation: '',
        photo_before: null as File | null,
    });

    // Formulario de Finalizar Mantenimiento
    const formFinalizar = useForm({
        execution_date: new Date().toISOString().split('T')[0],
        start_time: '08:00',
        end_time: new Date().toTimeString().substring(0, 5),
        work_description: '',
        result: 'Óptimo',
        observations: '',
        worker_id: '',
        photo_after: null as File | null,
    });

    // Formulario de Cancelar Mantenimiento
    const formCancelar = useForm({
        reason: '',
    });

    // Cálculo de duración en vivo en modal Atender
    const duracionAtenderMinutos = useMemo(() => {
        if (!formAtender.data.start_time || !formAtender.data.end_time) return 0;
        const [h1, m1] = formAtender.data.start_time.split(':').map(Number);
        const [h2, m2] = formAtender.data.end_time.split(':').map(Number);
        const mins = h2 * 60 + m2 - (h1 * 60 + m1);
        return mins > 0 ? mins : 0;
    }, [formAtender.data.start_time, formAtender.data.end_time]);

    // Cálculo de duración en vivo en modal Finalizar
    const duracionFinalizarMinutos = useMemo(() => {
        if (!formFinalizar.data.start_time || !formFinalizar.data.end_time) return 0;
        const [h1, m1] = formFinalizar.data.start_time.split(':').map(Number);
        const [h2, m2] = formFinalizar.data.end_time.split(':').map(Number);
        const mins = h2 * 60 + m2 - (h1 * 60 + m1);
        return mins > 0 ? mins : 0;
    }, [formFinalizar.data.start_time, formFinalizar.data.end_time]);

    const parqueSeleccionado = useMemo(() => parks.find((park) => String(park.id) === String(filters.park_id)) ?? null, [parks, filters.park_id]);

    const parquesFiltrados = useMemo(() => {
        const query = consultaParque.trim().toLocaleLowerCase('es');
        if (!query) return parks;

        return parks.filter((park) =>
            [park.name, park.code, park.zone, park.address].some((value) => value?.toLocaleLowerCase('es').includes(query)),
        );
    }, [parks, consultaParque]);

    const paginasVisibles = useMemo(() => {
        const first = Math.max(1, Math.min(records.current_page - 2, records.last_page - 4));
        const last = Math.min(records.last_page, first + 4);
        return Array.from({ length: Math.max(0, last - first + 1) }, (_, index) => first + index);
    }, [records.current_page, records.last_page]);

    const navegarConFiltros = (cambios: Partial<Props['filters']> & { page?: number }, options: { preserveScroll?: boolean } = {}) => {
        const params = {
            vista: filters.vista,
            estado: filters.estado && filters.estado !== 'todos' ? filters.estado : undefined,
            search: filters.search || undefined,
            park_id: filters.park_id || undefined,
            maintenance_type: filters.maintenance_type || undefined,
            ...cambios,
        };

        router.get('/mantenimientos', params, {
            preserveState: true,
            preserveScroll: options.preserveScroll ?? true,
            replace: true,
        });
    };

    useEffect(() => {
        setBusqueda(filters.search ?? '');
    }, [filters.search]);

    useEffect(() => {
        if (!modalJustificacionAbierto) return;

        const cerrarConEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setModalJustificacionAbierto(false);
            }
        };

        window.addEventListener('keydown', cerrarConEscape);

        return () => window.removeEventListener('keydown', cerrarConEscape);
    }, [modalJustificacionAbierto]);

    useEffect(() => {
        const search = busqueda.trim();
        if (search === (filters.search ?? '')) return;

        const timeout = window.setTimeout(() => {
            router.get(
                '/mantenimientos',
                {
                    vista: filters.vista,
                    estado: filters.estado && filters.estado !== 'todos' ? filters.estado : undefined,
                    search,
                    park_id: filters.park_id || undefined,
                    maintenance_type: filters.maintenance_type || undefined,
                    page: 1,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [busqueda, filters.estado, filters.maintenance_type, filters.park_id, filters.search, filters.vista]);

    const cargarRegistroCompleto = async (itemOrId: MaintenanceItem | number): Promise<MaintenanceItem | null> => {
        const id = typeof itemOrId === 'number' ? itemOrId : itemOrId.id;
        setRegistroCargandoId(id);
        setErrorCargaRegistro(null);

        try {
            const response = await fetch(`/mantenimientos/${id}`, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) throw new Error('No se pudo cargar el mantenimiento.');

            const payload = (await response.json()) as { record: MaintenanceItem };
            return payload.record;
        } catch {
            setErrorCargaRegistro('No se pudo cargar el detalle. Intente nuevamente.');
            return null;
        } finally {
            setRegistroCargandoId(null);
        }
    };

    const abrirJustificacion = (destino: 'atender' | 'iniciar', valores?: { motivo: string; documento: File | null }) => {
        setDestinoJustificacion(destino);
        setJustificacionMotivo(valores?.motivo ?? '');
        setJustificacionDocumento(valores?.documento ?? null);
        setJustificacionError(null);
        setModalAtenderAbierto(false);
        setModalIniciarAbierto(false);
        setModalJustificacionAbierto(true);

        if (justificacionArchivoRef.current) {
            justificacionArchivoRef.current.value = '';
        }
    };

    const seleccionarJustificacionDocumento = (file: File | null) => {
        if (!file) {
            setJustificacionDocumento(null);
            return;
        }

        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
        const permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];

        if (!permitidas.includes(extension)) {
            setJustificacionDocumento(null);
            setJustificacionError('Adjunta un archivo PDF, imagen o Word.');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setJustificacionDocumento(null);
            setJustificacionError('El documento no debe superar los 10 MB.');
            return;
        }

        setJustificacionDocumento(file);
        setJustificacionError(null);
    };

    const continuarJustificacion = () => {
        const motivo = justificacionMotivo.trim();

        if (!justificacionDocumento && motivo.length < 10) {
            setJustificacionError('Escribe el motivo (mínimo 10 caracteres) o adjunta un documento de respaldo.');
            return;
        }

        if (destinoJustificacion === 'atender') {
            formAtender.setData('overdue_reason', motivo);
            formAtender.setData('overdue_document', justificacionDocumento);
            setModalJustificacionAbierto(false);
            setModalAtenderAbierto(true);
        } else {
            formIniciar.setData('overdue_reason', motivo);
            formIniciar.setData('overdue_document', justificacionDocumento);
            setModalJustificacionAbierto(false);
            setModalIniciarAbierto(true);
        }
    };

    // Handlers para abrir modales. El registro completo se solicita solo al actuar.
    const abrirAtender = async (itemOrId: MaintenanceItem | number) => {
        const item = await cargarRegistroCompleto(itemOrId);
        if (!item) return;

        setMantenimientoSeleccionado(item);
        formAtender.setData({
            worker_id: workers.some((worker) => worker.id === item.worker_id) ? String(item.worker_id) : (workers[0]?.id.toString() ?? ''),
            execution_date: new Date().toISOString().split('T')[0],
            start_time: item.start_time || '08:00',
            end_time: item.end_time || '10:30',
            work_description: item.work_description || `Atención de ${item.maintenance_type} en ${item.park_name}.`,
            result: item.result || 'Óptimo',
            observations: item.observations || '',
            overdue_reason: '',
            overdue_document: null,
            status: 'finalizado',
            photo_before: null,
            photo_after: null,
        });

        if (item.computed_status === 'vencido') {
            abrirJustificacion('atender');
        } else {
            setModalAtenderAbierto(true);
        }
    };

    const abrirIniciar = async (itemOrId: MaintenanceItem | number) => {
        const item = await cargarRegistroCompleto(itemOrId);
        if (!item) return;

        setMantenimientoSeleccionado(item);
        formIniciar.setData({
            execution_date: new Date().toISOString().split('T')[0],
            start_time: new Date().toTimeString().substring(0, 5),
            worker_id: workers.some((worker) => worker.id === item.worker_id) ? String(item.worker_id) : (workers[0]?.id.toString() ?? ''),
            overdue_reason: '',
            overdue_document: null,
            initial_observation: item.initial_observation || '',
            photo_before: null,
        });

        if (item.computed_status === 'vencido') {
            abrirJustificacion('iniciar');
        } else {
            setModalIniciarAbierto(true);
        }
    };

    const editarJustificacion = (destino: 'atender' | 'iniciar') => {
        if (destino === 'atender') {
            abrirJustificacion('atender', {
                motivo: formAtender.data.overdue_reason,
                documento: formAtender.data.overdue_document,
            });
            return;
        }

        abrirJustificacion('iniciar', {
            motivo: formIniciar.data.overdue_reason,
            documento: formIniciar.data.overdue_document,
        });
    };

    const abrirFinalizar = async (itemOrId: MaintenanceItem | number) => {
        const item = await cargarRegistroCompleto(itemOrId);
        if (!item) return;

        setMantenimientoSeleccionado(item);
        formFinalizar.setData({
            execution_date: item.execution_date || new Date().toISOString().split('T')[0],
            start_time: item.start_time || '08:00',
            end_time: new Date().toTimeString().substring(0, 5),
            work_description: item.work_description || `Trabajo de ${item.maintenance_type} completado de forma integral.`,
            result: item.result || 'Óptimo',
            observations: item.observations || '',
            worker_id: workers.some((worker) => worker.id === item.worker_id) ? String(item.worker_id) : (workers[0]?.id.toString() ?? ''),
            photo_after: null,
        });
        setModalFinalizarAbierto(true);
    };

    const abrirDetalle = async (itemOrId: MaintenanceItem | number) => {
        const item = await cargarRegistroCompleto(itemOrId);
        if (!item) return;

        setMantenimientoSeleccionado(item);
        setModalDetalleAbierto(true);
    };

    const abrirCancelar = async (itemOrId: MaintenanceItem | number) => {
        const item = await cargarRegistroCompleto(itemOrId);
        if (!item) return;

        setMantenimientoSeleccionado(item);
        formCancelar.setData({ reason: '' });
        setModalCancelarAbierto(true);
    };

    // Submits
    const submitExtraordinario = (e: FormEvent) => {
        e.preventDefault();
        formExtraordinario.post('/mantenimientos/extraordinario', {
            onSuccess: () => {
                setModalExtraordinarioAbierto(false);
                formExtraordinario.reset();
            },
        });
    };

    const submitAtender = (e: FormEvent) => {
        e.preventDefault();
        if (!mantenimientoSeleccionado) return;

        formAtender.post(`/mantenimientos/${mantenimientoSeleccionado.id}/atender`, {
            forceFormData: true,
            onSuccess: () => {
                setModalAtenderAbierto(false);
            },
        });
    };

    const submitIniciar = (e: FormEvent) => {
        e.preventDefault();
        if (!mantenimientoSeleccionado) return;

        formIniciar.post(`/mantenimientos/${mantenimientoSeleccionado.id}/iniciar`, {
            forceFormData: true,
            onSuccess: () => {
                setModalIniciarAbierto(false);
            },
        });
    };

    const submitFinalizar = (e: FormEvent) => {
        e.preventDefault();
        if (!mantenimientoSeleccionado) return;

        formFinalizar.post(`/mantenimientos/${mantenimientoSeleccionado.id}/finalizar`, {
            forceFormData: true,
            onSuccess: () => {
                setModalFinalizarAbierto(false);
            },
        });
    };

    const submitCancelar = (e: FormEvent) => {
        e.preventDefault();
        if (!mantenimientoSeleccionado) return;

        formCancelar.patch(`/mantenimientos/${mantenimientoSeleccionado.id}/cancelar`, {
            onSuccess: () => {
                setModalCancelarAbierto(false);
            },
        });
    };

    useEffect(() => {
        const parameters = new URLSearchParams(window.location.search);

        if (parameters.get('accion') === 'extraordinario') {
            setModalExtraordinarioAbierto(true);
        }

        const attendId = Number(parameters.get('atender'));
        const finishId = Number(parameters.get('finalizar'));

        if (attendId) {
            void abrirAtender(attendId);
        } else if (finishId) {
            void abrirFinalizar(finishId);
        }
        // Los parámetros de acción solo se procesan al montar para evitar reabrir modales.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gestión de Mantenimientos - Sistema de Áreas Verdes" />

            <div className="page-shell">
                <div className="page-container">
                    <PageHeader
                        title="Gestión de mantenimientos"
                        description="Control operativo, atención de órdenes por frecuencia, intervenciones adicionales y emisión de partes oficiales."
                        icon={Wrench}
                        meta={
                            <span className="pill bg-muted text-muted-foreground">
                                {counts.en_proceso + counts.vencidos + counts.pendientes + counts.proximos} órdenes activas
                            </span>
                        }
                        actions={
                            <Button onClick={() => setModalExtraordinarioAbierto(true)} className="h-9 rounded-[9px]">
                                <Zap className="size-4" />
                                Mantenimiento adicional
                            </Button>
                        }
                    />

                    {/* TARJETAS RESUMEN DE INDICADORES */}
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <StatCard label="Total de órdenes" value={counts.todos} hint="Historial acumulado" icon={Layers} tone="neutral" />
                        <StatCard label="Pendientes" value={counts.pendientes} hint="Por atender" icon={Clock} tone="warning" />
                        <StatCard label="En mantenimiento" value={counts.en_proceso} hint="Cuadrillas operando" icon={RefreshCw} tone="info" />
                        <StatCard label="Finalizados" value={counts.finalizados} hint="Labores concluidas" icon={CheckCircle2} tone="success" />
                        <StatCard label="Vencidos" value={counts.vencidos} hint="Requieren prioridad" icon={AlertTriangle} tone="danger" />
                    </div>

                    {/* BANDEJA DE MANTENIMIENTOS */}
                    <section className="surface min-w-0 overflow-hidden">
                        {/* VISTAS PRINCIPALES */}
                        <SectionTabs
                            ariaLabel="Vistas de mantenimientos"
                            active={filters.vista === 'historial' ? 'historial' : 'operativa'}
                            onChange={(id) => navegarConFiltros({ vista: id as 'operativa' | 'historial', estado: '', page: 1 })}
                            items={[
                                {
                                    id: 'operativa',
                                    label: 'Trabajos pendientes',
                                    count: counts.en_proceso + counts.vencidos + counts.pendientes + counts.proximos,
                                },
                                { id: 'historial', label: 'Historial', count: counts.finalizados + counts.cancelados },
                            ]}
                            className="border-b"
                        />

                        {/* FILTROS Y BÚSQUEDA */}
                        <div className="border-b p-4">
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_minmax(240px,1fr)_200px_190px_auto]">
                                <div className="relative">
                                    <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                    <input
                                        type="text"
                                        value={busqueda}
                                        onChange={(e) => setBusqueda(e.target.value)}
                                        placeholder="Buscar orden, parque o trabajador…"
                                        aria-label="Buscar mantenimientos"
                                        className="field-control pl-9"
                                    />
                                </div>

                                <Combobox
                                    value={parqueSeleccionado}
                                    onChange={(park: ParkOption | null) => {
                                        setConsultaParque('');
                                        navegarConFiltros({ park_id: park ? String(park.id) : '', page: 1 });
                                    }}
                                >
                                    <div className="relative">
                                        <ComboboxInput
                                            aria-label="Filtrar por parque"
                                            displayValue={(park: ParkOption | null) => (park ? `${park.code} · ${park.name}` : '')}
                                            onChange={(event) => setConsultaParque(event.target.value)}
                                            placeholder="Todos los parques"
                                            className="field-control pr-9"
                                        />
                                        <ComboboxButton className="text-muted-foreground absolute inset-y-0 right-0 flex items-center px-3">
                                            <ChevronsUpDown className="h-4 w-4" />
                                        </ComboboxButton>
                                        <ComboboxOptions className="bg-popover absolute z-50 mt-1 max-h-72 w-full overflow-auto rounded-xl border p-1 text-xs shadow-lg empty:invisible">
                                            <ComboboxOption
                                                value={null}
                                                className="data-focus:bg-muted flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2"
                                            >
                                                <span className="flex-1">Todos los parques</span>
                                                {!parqueSeleccionado && <Check className="h-4 w-4 text-emerald-600" />}
                                            </ComboboxOption>
                                            {parquesFiltrados.map((park) => (
                                                <ComboboxOption
                                                    key={park.id}
                                                    value={park}
                                                    className="data-focus:bg-muted flex cursor-pointer items-start gap-2 rounded-lg px-3 py-2"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate font-semibold">
                                                            {park.code} · {park.name}
                                                        </p>
                                                        <p className="text-muted-foreground truncate text-[11px]">
                                                            {park.zone} · {park.address}
                                                        </p>
                                                    </div>
                                                    {parqueSeleccionado?.id === park.id && (
                                                        <Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                                    )}
                                                </ComboboxOption>
                                            ))}
                                        </ComboboxOptions>
                                    </div>
                                </Combobox>

                                <select
                                    value={filters.estado === 'todos' ? '' : (filters.estado ?? '')}
                                    onChange={(e) => navegarConFiltros({ estado: e.target.value, page: 1 })}
                                    aria-label="Filtrar por estado"
                                    className="field-control"
                                >
                                    <option value="">Todos los estados</option>
                                    {filters.vista === 'operativa' ? (
                                        <>
                                            <option value="en_proceso">En proceso</option>
                                            <option value="vencidos">Vencidos</option>
                                            <option value="pendientes">Pendientes</option>
                                            <option value="proximos">Próximos</option>
                                        </>
                                    ) : (
                                        <>
                                            <option value="finalizados">Finalizados</option>
                                            <option value="cancelados">Cancelados</option>
                                        </>
                                    )}
                                </select>

                                <select
                                    value={filters.maintenance_type ?? ''}
                                    onChange={(e) => navegarConFiltros({ maintenance_type: e.target.value, page: 1 })}
                                    aria-label="Filtrar por tipo de mantenimiento"
                                    className="field-control"
                                >
                                    <option value="">Todos los tipos de labor</option>
                                    {tiposMantenimiento.map((t) => (
                                        <option key={t} value={t}>
                                            {t}
                                        </option>
                                    ))}
                                </select>

                                <button
                                    type="button"
                                    onClick={() => {
                                        setBusqueda('');
                                        setConsultaParque('');
                                        navegarConFiltros({ estado: '', search: '', park_id: '', maintenance_type: '', page: 1 });
                                    }}
                                    disabled={
                                        (!filters.estado || filters.estado === 'todos') &&
                                        !filters.search &&
                                        !filters.park_id &&
                                        !filters.maintenance_type
                                    }
                                    className="hover:bg-muted h-10 rounded-[10px] border px-3 text-[12px] font-semibold disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    Limpiar filtros
                                </button>
                            </div>

                            {errorCargaRegistro && (
                                <p className="mt-3 flex items-center gap-1.5 text-xs font-medium text-red-600" role="alert">
                                    <AlertCircle className="h-4 w-4" /> {errorCargaRegistro}
                                </p>
                            )}
                        </div>

                        {/* TABLA DE MANTENIMIENTOS */}
                        <div className="w-full overflow-x-auto">
                            <table className="data-table min-w-[900px]">
                                <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Parque</th>
                                        <th>Trabajo</th>
                                        <th>Programación</th>
                                        <th className="text-right">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {records.data.length > 0 ? (
                                        records.data.map((item) => {
                                            return (
                                                <tr key={item.id}>
                                                    <td>
                                                        <div className="flex flex-col items-start gap-1">
                                                            <span className="bg-brand-soft text-primary inline-flex items-center gap-1.5 rounded-[6px] px-2 py-0.5 font-mono text-[11px] font-semibold whitespace-nowrap">
                                                                <FileText className="size-3" />
                                                                {item.maintenance_number}
                                                            </span>
                                                            {item.is_extraordinary && (
                                                                <span className="pill bg-amber-50 text-[10px] text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                                                    Adicional
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <div className="flex items-start gap-2">
                                                            <Trees className="text-primary mt-0.5 size-4 shrink-0" />
                                                            <div className="min-w-0">
                                                                <p className="text-foreground line-clamp-1 font-semibold">{item.park_name}</p>
                                                                <span className="text-muted-foreground font-mono text-[11px]">
                                                                    {item.park_code} · {item.park_zone}
                                                                </span>
                                                                <p className="text-muted-foreground mt-0.5 text-[11px]">{item.green_area_type}</p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <div className="text-foreground flex items-center gap-1.5 font-medium">
                                                            {item.maintenance_type.toLowerCase().includes('riego') ? (
                                                                <Droplets className="size-4 text-sky-600 dark:text-sky-400" />
                                                            ) : item.maintenance_type.toLowerCase().includes('poda') ? (
                                                                <Trees className="text-primary size-4" />
                                                            ) : (
                                                                <Sparkles className="size-4 text-amber-500" />
                                                            )}
                                                            {item.maintenance_type}
                                                            {item.frequency_days && (
                                                                <span className="text-muted-foreground text-[11px]">
                                                                    cada {item.frequency_days} d
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-muted-foreground mt-1 flex items-center gap-1.5 text-[11px]">
                                                            <UserCheck className="size-3.5" />
                                                            <span className="line-clamp-1">{item.responsible_worker}</span>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <div className="text-foreground flex items-center gap-1.5 text-[12px] font-medium tabular-nums">
                                                            <Calendar className="text-muted-foreground size-3.5" />
                                                            {item.scheduled_date_formatted}
                                                        </div>
                                                        {item.alert_date_formatted ? (
                                                            <p
                                                                className={`mt-1 text-[11px] ${item.is_alert_active ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-muted-foreground'}`}
                                                            >
                                                                Alerta: {item.alert_date_formatted}
                                                            </p>
                                                        ) : null}
                                                        <div className="mt-1.5">
                                                            <span className={`pill ${STATUS_META[item.computed_status]?.className ?? ''}`}>
                                                                <span
                                                                    className={`size-1.5 rounded-full ${STATUS_META[item.computed_status]?.dot ?? ''}`}
                                                                />
                                                                {STATUS_META[item.computed_status]?.label ?? item.computed_status}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            {registroCargandoId === item.id && (
                                                                <span className="text-muted-foreground inline-flex items-center gap-1 text-[11px]">
                                                                    <LoaderCircle className="size-3.5 animate-spin" /> Cargando
                                                                </span>
                                                            )}

                                                            {/* Acciones para pendientes / próximos / vencidos */}
                                                            {registroCargandoId !== item.id &&
                                                                ['pendiente', 'proximo', 'vencido'].includes(item.computed_status) && (
                                                                    <>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => void abrirAtender(item)}
                                                                            className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-8 items-center gap-1.5 rounded-[8px] px-2.5 text-[11px] font-semibold transition"
                                                                        >
                                                                            <CheckCircle2 className="size-3.5" /> Atender
                                                                        </button>

                                                                        <RowActions
                                                                            label={`Acciones de ${item.maintenance_number}`}
                                                                            items={[
                                                                                {
                                                                                    label: 'Comenzar trabajo',
                                                                                    icon: Play,
                                                                                    onSelect: () => void abrirIniciar(item),
                                                                                },
                                                                                {
                                                                                    label: 'Cancelar orden',
                                                                                    icon: Trash2,
                                                                                    danger: true,
                                                                                    onSelect: () => void abrirCancelar(item),
                                                                                },
                                                                            ]}
                                                                        />
                                                                    </>
                                                                )}

                                                            {/* Acciones para en proceso */}
                                                            {registroCargandoId !== item.id && item.computed_status === 'en_proceso' && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => void abrirFinalizar(item)}
                                                                    className="inline-flex h-8 items-center gap-1.5 rounded-[8px] bg-sky-600 px-2.5 text-[11px] font-semibold text-white transition hover:bg-sky-700"
                                                                >
                                                                    <CheckCircle2 className="size-3.5" /> Finalizar
                                                                </button>
                                                            )}

                                                            {/* Acciones para finalizados */}
                                                            {registroCargandoId !== item.id && item.computed_status === 'finalizado' && (
                                                                <>
                                                                    <a
                                                                        href={`/mantenimientos/${item.id}/documento`}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="hover:bg-muted inline-flex h-8 items-center gap-1.5 rounded-[8px] border px-2.5 text-[11px] font-semibold transition"
                                                                    >
                                                                        <Printer className="size-3.5" /> Parte oficial
                                                                    </a>

                                                                    <RowActions
                                                                        label={`Acciones de ${item.maintenance_number}`}
                                                                        items={[
                                                                            {
                                                                                label: 'Ver resumen del mantenimiento',
                                                                                icon: Eye,
                                                                                onSelect: () => void abrirDetalle(item),
                                                                            },
                                                                        ]}
                                                                    />
                                                                </>
                                                            )}

                                                            {/* Acciones para cancelados */}
                                                            {registroCargandoId !== item.id && item.computed_status === 'cancelado' && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => void abrirDetalle(item)}
                                                                    title="Ver detalle de cancelación"
                                                                    aria-label="Ver detalle de cancelación"
                                                                    className="text-muted-foreground hover:bg-accent hover:text-accent-foreground grid size-8 place-items-center rounded-[8px] transition"
                                                                >
                                                                    <Eye className="size-4" />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="text-muted-foreground py-12 text-center">
                                                <Wrench className="text-muted-foreground/50 mx-auto mb-2 h-8 w-8" />
                                                <p className="font-medium">No se encontraron mantenimientos</p>
                                                <p className="mt-1 text-xs">Cambie la vista o limpie los filtros para ampliar la búsqueda.</p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-muted-foreground text-xs font-medium">
                                {records.total > 0 ? (
                                    <>
                                        Mostrando{' '}
                                        <span className="text-foreground font-semibold">
                                            {records.from}–{records.to}
                                        </span>{' '}
                                        de <span className="text-foreground font-semibold">{records.total}</span> registros
                                    </>
                                ) : (
                                    'Sin registros'
                                )}
                            </p>

                            {records.last_page > 1 && (
                                <nav className="flex items-center gap-1" aria-label="Paginación de mantenimientos">
                                    <button
                                        type="button"
                                        disabled={records.current_page <= 1}
                                        onClick={() => navegarConFiltros({ page: records.current_page - 1 })}
                                        className="hover:bg-muted rounded-lg border p-2 disabled:cursor-not-allowed disabled:opacity-40"
                                        aria-label="Página anterior"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </button>
                                    {paginasVisibles.map((page) => (
                                        <button
                                            key={page}
                                            type="button"
                                            onClick={() => navegarConFiltros({ page })}
                                            aria-current={page === records.current_page ? 'page' : undefined}
                                            className={`h-8 min-w-8 rounded-[8px] border px-2 text-[12px] font-semibold tabular-nums ${
                                                page === records.current_page
                                                    ? 'bg-primary text-primary-foreground border-transparent'
                                                    : 'hover:bg-muted'
                                            }`}
                                        >
                                            {page}
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        disabled={records.current_page >= records.last_page}
                                        onClick={() => navegarConFiltros({ page: records.current_page + 1 })}
                                        className="hover:bg-muted rounded-lg border p-2 disabled:cursor-not-allowed disabled:opacity-40"
                                        aria-label="Página siguiente"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </button>
                                </nav>
                            )}
                        </div>
                    </section>
                </div>
            </div>

            {/* ========================================================================= */}
            {/* MODAL ATENDER MANTENIMIENTO (GENERADO POR FRECUENCIA O PROGRAMADO)          */}
            {/* ========================================================================= */}
            {modalAtenderAbierto && mantenimientoSeleccionado && (
                <div className="fixed inset-0 z-50 flex items-stretch justify-center bg-black/60 p-2 backdrop-blur-xs sm:items-center sm:p-4">
                    <div className="bg-background flex h-[calc(100dvh-1rem)] max-h-[calc(100dvh-1rem)] w-full max-w-3xl flex-col overflow-hidden rounded-xl border shadow-2xl sm:h-auto sm:max-h-[92dvh] sm:rounded-2xl">
                        <div className="flex shrink-0 items-center justify-between gap-3 border-b bg-emerald-500/10 px-4 py-3 sm:px-6 sm:py-4">
                            <div className="min-w-0">
                                <h2 className="text-foreground flex items-center gap-2 text-sm font-bold sm:text-lg">
                                    <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600" />
                                    Atender mantenimiento — {mantenimientoSeleccionado.maintenance_number}
                                </h2>
                                <p className="text-muted-foreground mt-0.5 hidden text-xs sm:block">
                                    Revisa los datos de la orden y registra cómo se realizó el trabajo.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalAtenderAbierto(false)}
                                className="text-muted-foreground hover:bg-muted shrink-0 rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitAtender} className="flex min-h-0 flex-1 flex-col">
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-4 pb-8 sm:space-y-5 sm:p-6 sm:pb-8">
                                {/* Datos autocompletados del parque y la regla */}
                                <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/40">
                                    <h4 className="mb-3 text-xs font-bold tracking-wider text-emerald-900 uppercase dark:text-emerald-200">
                                        Datos del mantenimiento
                                    </h4>
                                    <div className="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                        <div>
                                            <span className="text-muted-foreground block">Parque:</span>
                                            <strong className="text-foreground">{mantenimientoSeleccionado.park_name}</strong>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground block">Tipo de área verde:</span>
                                            <strong className="text-foreground">{mantenimientoSeleccionado.green_area_type}</strong>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground block">Tipo de mantenimiento:</span>
                                            <strong className="text-foreground">{mantenimientoSeleccionado.maintenance_type}</strong>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground block">Fecha programada:</span>
                                            <strong className="text-foreground">{mantenimientoSeleccionado.scheduled_date_formatted}</strong>
                                        </div>
                                        {mantenimientoSeleccionado.frequency_days && (
                                            <div>
                                                <span className="text-muted-foreground block">Frecuencia aplicada:</span>
                                                <strong className="text-emerald-700 dark:text-emerald-400">
                                                    Cada {mantenimientoSeleccionado.frequency_days} días
                                                </strong>
                                            </div>
                                        )}
                                        <div>
                                            <span className="text-muted-foreground block">Estado actual:</span>
                                            <strong className="text-foreground capitalize">{mantenimientoSeleccionado.status}</strong>
                                        </div>
                                    </div>
                                </div>

                                {mantenimientoSeleccionado.computed_status === 'vencido' && (
                                    <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900 dark:bg-amber-950/35">
                                        <div className="flex items-start gap-3">
                                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-xs font-bold text-amber-900 dark:text-amber-200">Retraso justificado</p>
                                                <p className="mt-1 text-[11px] leading-relaxed text-amber-800 dark:text-amber-300">
                                                    {formAtender.data.overdue_reason ||
                                                        'Se adjuntó un documento de respaldo para esta orden vencida.'}
                                                </p>
                                                {formAtender.data.overdue_document && (
                                                    <p className="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-900 dark:text-amber-200">
                                                        <FileText className="size-3.5 shrink-0" />
                                                        <span className="truncate">{formAtender.data.overdue_document.name}</span>
                                                    </p>
                                                )}
                                                <InputError
                                                    message={formAtender.errors.overdue_reason || formAtender.errors.overdue_document}
                                                    className="mt-2 text-[11px]"
                                                />
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => editarJustificacion('atender')}
                                                className="shrink-0 rounded-lg border border-amber-300 bg-white/70 px-2.5 py-1 text-[11px] font-semibold text-amber-900 transition hover:bg-white dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/70"
                                            >
                                                Editar
                                            </button>
                                        </div>
                                    </div>
                                )}

                                {/* Campos a completar por el encargado */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">
                                            Trabajador responsable <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            required
                                            value={formAtender.data.worker_id}
                                            onChange={(e) => formAtender.setData('worker_id', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                        >
                                            <option value="">Seleccione un trabajador</option>
                                            {workers.map((worker) => (
                                                <option key={worker.id} value={worker.id}>
                                                    {worker.full_name} — {worker.position}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">
                                            Fecha real de ejecución <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="date"
                                            required
                                            value={formAtender.data.execution_date}
                                            onChange={(e) => formAtender.setData('execution_date', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">
                                            Hora de inicio <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="time"
                                            required
                                            value={formAtender.data.start_time}
                                            onChange={(e) => formAtender.setData('start_time', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">
                                            Hora de finalización <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="time"
                                            required
                                            value={formAtender.data.end_time}
                                            onChange={(e) => formAtender.setData('end_time', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                        />
                                        {duracionAtenderMinutos > 0 && (
                                            <p className="mt-1 text-[11px] font-semibold text-emerald-600">
                                                Duración calculada: {Math.floor(duracionAtenderMinutos / 60)}h {duracionAtenderMinutos % 60}m (
                                                {duracionAtenderMinutos} min)
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        Descripción del trabajo realizado <span className="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        required
                                        rows={2}
                                        value={formAtender.data.work_description}
                                        onChange={(e) => formAtender.setData('work_description', e.target.value)}
                                        placeholder="Detalle de las labores efectuadas..."
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">Resultado / Calificación de labor</label>
                                        <select
                                            value={formAtender.data.result}
                                            onChange={(e) => formAtender.setData('result', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                        >
                                            <option value="Óptimo">Óptimo</option>
                                            <option value="Favorable">Favorable</option>
                                            <option value="Regular">Regular</option>
                                            <option value="Requiere intervención complementaria">Requiere intervención complementaria</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">Estado final</label>
                                        <select
                                            value={formAtender.data.status}
                                            onChange={(e) => formAtender.setData('status', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                        >
                                            <option value="finalizado">Finalizado (Concluido)</option>
                                            <option value="en_proceso">En proceso (Continúa laborando)</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Observaciones</label>
                                    <textarea
                                        rows={2}
                                        value={formAtender.data.observations}
                                        onChange={(e) => formAtender.setData('observations', e.target.value)}
                                        placeholder="Observaciones de terreno..."
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">Fotografía antes del trabajo (opcional)</label>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => formAtender.setData('photo_before', e.target.files ? e.target.files[0] : null)}
                                            className="text-muted-foreground w-full text-xs file:mr-3 file:rounded-xl file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-950 dark:file:text-emerald-300"
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">Fotografía posterior / resultado (opcional)</label>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => formAtender.setData('photo_after', e.target.files ? e.target.files[0] : null)}
                                            className="text-muted-foreground w-full text-xs file:mr-3 file:rounded-xl file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-950 dark:file:text-emerald-300"
                                        />
                                    </div>
                                </div>

                                {mantenimientoSeleccionado.frequency_days && formAtender.data.status === 'finalizado' && (
                                    <div className="flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                                        <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                                        <span>
                                            <strong>Próxima fecha automática:</strong> Al finalizar, el sistema calculará la siguiente fecha: fecha
                                            real ({formAtender.data.execution_date}) + frecuencia ({mantenimientoSeleccionado.frequency_days} días) y
                                            creará la siguiente orden en la cadena.
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div className="bg-background flex shrink-0 flex-col-reverse gap-2 border-t px-4 py-3 sm:flex-row sm:justify-end sm:gap-3 sm:px-6 sm:py-4">
                                <button
                                    type="button"
                                    onClick={() => setModalAtenderAbierto(false)}
                                    className="hover:bg-muted w-full rounded-xl border px-4 py-2.5 text-xs font-semibold sm:w-auto sm:py-2"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={formAtender.processing}
                                    className="w-full rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 disabled:opacity-50 sm:w-auto sm:py-2"
                                >
                                    {formAtender.processing ? 'Guardando...' : 'Guardar atención'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ========================================================================= */}
            {/* MODAL REGISTRAR MANTENIMIENTO EXTRAORDINARIO                               */}
            {/* ========================================================================= */}
            {modalExtraordinarioAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
                    <div className="bg-background flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="flex items-center justify-between border-b bg-amber-500/10 px-6 py-4">
                            <div>
                                <h2 className="flex items-center gap-2 text-lg font-bold text-amber-900 dark:text-amber-200">
                                    <Zap className="h-5 w-5 text-amber-600" />
                                    Registrar mantenimiento adicional
                                </h2>
                                <p className="text-xs text-amber-800 dark:text-amber-300">
                                    Para trabajos especiales que no se originan por una regla de frecuencia recurrente.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalExtraordinarioAbierto(false)}
                                className="text-muted-foreground hover:bg-muted rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitExtraordinario} className="flex-1 space-y-4 overflow-y-auto p-6">
                            <div>
                                <label className="mb-1 block text-xs font-semibold">
                                    Parque o Área Verde <span className="text-red-500">*</span>
                                </label>
                                <select
                                    required
                                    value={formExtraordinario.data.park_id}
                                    onChange={(e) => formExtraordinario.setData('park_id', Number(e.target.value))}
                                    className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                >
                                    {parks.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} ({p.code} • {p.green_area_type})
                                        </option>
                                    ))}
                                </select>
                                <InputError message={formExtraordinario.errors.park_id} className="mt-1 text-xs" />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        Tipo de labor adicional <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        required
                                        value={formExtraordinario.data.maintenance_type}
                                        onChange={(e) => formExtraordinario.setData('maintenance_type', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option value="" disabled>
                                            Seleccione un tipo de mantenimiento
                                        </option>
                                        {tiposMantenimiento.map((tipo) => (
                                            <option key={tipo} value={tipo}>
                                                {tipo}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={formExtraordinario.errors.maintenance_type} className="mt-1 text-xs" />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        Fecha programada <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        required
                                        value={formExtraordinario.data.scheduled_date}
                                        onChange={(e) => formExtraordinario.setData('scheduled_date', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                    />
                                    <InputError message={formExtraordinario.errors.scheduled_date} className="mt-1 text-xs" />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Días de anticipación para alerta</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="15"
                                        value={formExtraordinario.data.advance_days}
                                        onChange={(e) => formExtraordinario.setData('advance_days', Number(e.target.value))}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                    />
                                    <p className="text-muted-foreground mt-0.5 text-[11px]">
                                        La alerta se activará {formExtraordinario.data.advance_days} día(s) antes de la fecha programada.
                                    </p>
                                    <InputError message={formExtraordinario.errors.advance_days} className="mt-1 text-xs" />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Trabajador responsable</label>
                                    <select
                                        value={formExtraordinario.data.worker_id}
                                        onChange={(e) => formExtraordinario.setData('worker_id', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option value="">Por asignar</option>
                                        {workers.map((worker) => (
                                            <option key={worker.id} value={worker.id}>
                                                {worker.full_name} — {worker.position}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={formExtraordinario.errors.worker_id} className="mt-1 text-xs" />
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold">
                                    Motivo del trabajo adicional <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    required
                                    rows={2}
                                    value={formExtraordinario.data.reason}
                                    onChange={(e) => formExtraordinario.setData('reason', e.target.value)}
                                    placeholder="Indique la causa o urgencia de la intervención (ej: caída de ramas por vientos fuertes, fuga en válvula)..."
                                    className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                />
                                <InputError message={formExtraordinario.errors.reason} className="mt-1 text-xs" />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold">Observaciones adicionales</label>
                                <textarea
                                    rows={2}
                                    value={formExtraordinario.data.observations}
                                    onChange={(e) => formExtraordinario.setData('observations', e.target.value)}
                                    placeholder="Herramientas necesarias o indicaciones..."
                                    className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                />
                                <InputError message={formExtraordinario.errors.observations} className="mt-1 text-xs" />
                            </div>

                            <div className="flex justify-end gap-3 border-t pt-3">
                                <button
                                    type="button"
                                    onClick={() => setModalExtraordinarioAbierto(false)}
                                    className="hover:bg-muted rounded-xl border px-4 py-2 text-xs font-semibold"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={formExtraordinario.processing}
                                    className="rounded-xl bg-amber-600 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-amber-700 disabled:opacity-50"
                                >
                                    {formExtraordinario.processing ? 'Programando...' : 'Programar mantenimiento adicional'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ========================================================================= */}
            {/* MODAL INICIO DEL MANTENIMIENTO                                            */}
            {/* ========================================================================= */}
            {modalIniciarAbierto && mantenimientoSeleccionado && (
                <div className="fixed inset-0 z-50 flex items-stretch justify-center bg-black/60 p-2 backdrop-blur-xs sm:items-center sm:p-4">
                    <div className="bg-background flex h-[calc(100dvh-1rem)] max-h-[calc(100dvh-1rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border shadow-2xl sm:h-auto sm:max-h-[92dvh] sm:rounded-2xl">
                        <div className="flex shrink-0 items-center justify-between gap-3 border-b bg-blue-500/10 px-4 py-3 sm:px-6 sm:py-4">
                            <div className="min-w-0">
                                <h3 className="flex items-center gap-2 text-sm font-bold text-blue-900 sm:text-lg dark:text-blue-200">
                                    <Play className="h-5 w-5 shrink-0 text-blue-600" />
                                    Comenzar trabajo
                                </h3>
                                <p className="truncate text-xs text-blue-800 dark:text-blue-300">
                                    {mantenimientoSeleccionado.maintenance_number} • {mantenimientoSeleccionado.park_name}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalIniciarAbierto(false)}
                                className="text-muted-foreground hover:bg-muted shrink-0 rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitIniciar} className="flex min-h-0 flex-1 flex-col">
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-4 pb-8 sm:p-6 sm:pb-8">
                                {mantenimientoSeleccionado.computed_status === 'vencido' && (
                                    <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900 dark:bg-amber-950/35">
                                        <div className="flex items-start gap-3">
                                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-xs font-bold text-amber-900 dark:text-amber-200">Retraso justificado</p>
                                                <p className="mt-1 text-[11px] leading-relaxed text-amber-800 dark:text-amber-300">
                                                    {formIniciar.data.overdue_reason ||
                                                        'Se adjuntó un documento de respaldo para esta orden vencida.'}
                                                </p>
                                                {formIniciar.data.overdue_document && (
                                                    <p className="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-900 dark:text-amber-200">
                                                        <FileText className="size-3.5 shrink-0" />
                                                        <span className="truncate">{formIniciar.data.overdue_document.name}</span>
                                                    </p>
                                                )}
                                                <InputError
                                                    message={formIniciar.errors.overdue_reason || formIniciar.errors.overdue_document}
                                                    className="mt-2 text-[11px]"
                                                />
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => editarJustificacion('iniciar')}
                                                className="shrink-0 rounded-lg border border-amber-300 bg-white/70 px-2.5 py-1 text-[11px] font-semibold text-amber-900 transition hover:bg-white dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/70"
                                            >
                                                Editar
                                            </button>
                                        </div>
                                    </div>
                                )}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">Fecha real de inicio</label>
                                        <input
                                            type="date"
                                            required
                                            value={formIniciar.data.execution_date}
                                            onChange={(e) => formIniciar.setData('execution_date', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-semibold">Hora de inicio</label>
                                        <input
                                            type="time"
                                            required
                                            value={formIniciar.data.start_time}
                                            onChange={(e) => formIniciar.setData('start_time', e.target.value)}
                                            className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Trabajador responsable</label>
                                    <select
                                        required
                                        value={formIniciar.data.worker_id}
                                        onChange={(e) => formIniciar.setData('worker_id', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    >
                                        <option value="">Seleccione un trabajador</option>
                                        {workers.map((worker) => (
                                            <option key={worker.id} value={worker.id}>
                                                {worker.full_name} — {worker.position}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Observación inicial</label>
                                    <textarea
                                        rows={2}
                                        value={formIniciar.data.initial_observation}
                                        onChange={(e) => formIniciar.setData('initial_observation', e.target.value)}
                                        placeholder="Estado previo del terreno o herramientas..."
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Fotografía antes del trabajo (opcional)</label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => formIniciar.setData('photo_before', e.target.files ? e.target.files[0] : null)}
                                        className="text-muted-foreground w-full text-xs file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100"
                                    />
                                </div>

                                <p className="text-muted-foreground text-[11px] italic">
                                    Al confirmar, el estado cambiará a <strong>"En mantenimiento"</strong>.
                                </p>
                            </div>

                            <div className="bg-background flex shrink-0 flex-col-reverse gap-2 border-t px-4 py-3 sm:flex-row sm:justify-end sm:gap-3 sm:px-6 sm:py-4">
                                <button
                                    type="button"
                                    onClick={() => setModalIniciarAbierto(false)}
                                    className="hover:bg-muted w-full rounded-xl border px-4 py-2.5 text-xs font-semibold sm:w-auto sm:py-2"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={formIniciar.processing}
                                    className="w-full rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-semibold text-white shadow-xs hover:bg-blue-700 disabled:opacity-50 sm:w-auto sm:py-2"
                                >
                                    {formIniciar.processing ? 'Iniciando...' : 'Confirmar Inicio de Labor'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ========================================================================= */}
            {/* MODAL FINALIZACIÓN DEL MANTENIMIENTO                                      */}
            {/* ========================================================================= */}
            {modalFinalizarAbierto && mantenimientoSeleccionado && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
                    <div className="bg-background flex max-h-[92dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="flex items-center justify-between border-b bg-sky-500/10 px-6 py-4">
                            <div>
                                <h3 className="flex items-center gap-2 text-lg font-bold text-sky-900 dark:text-sky-200">
                                    <CheckCircle2 className="h-5 w-5 text-sky-600" />
                                    Finalización del Mantenimiento
                                </h3>
                                <p className="text-xs text-sky-800 dark:text-sky-300">
                                    {mantenimientoSeleccionado.maintenance_number} • {mantenimientoSeleccionado.park_name}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalFinalizarAbierto(false)}
                                className="text-muted-foreground hover:bg-muted rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitFinalizar} className="flex-1 space-y-4 overflow-y-auto p-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Hora de inicio</label>
                                    <input
                                        type="time"
                                        required
                                        value={formFinalizar.data.start_time}
                                        onChange={(e) => formFinalizar.setData('start_time', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Hora de finalización</label>
                                    <input
                                        type="time"
                                        required
                                        value={formFinalizar.data.end_time}
                                        onChange={(e) => formFinalizar.setData('end_time', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            {duracionFinalizarMinutos > 0 && (
                                <div className="flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 p-2.5 text-xs font-semibold text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
                                    <Clock className="h-4 w-4 text-sky-600" />
                                    Duración calculada: {Math.floor(duracionFinalizarMinutos / 60)}h {duracionFinalizarMinutos % 60}m (
                                    {duracionFinalizarMinutos} minutos)
                                </div>
                            )}

                            <div>
                                <label className="mb-1 block text-xs font-semibold">
                                    Descripción del trabajo realizado <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    required
                                    rows={2}
                                    value={formFinalizar.data.work_description}
                                    onChange={(e) => formFinalizar.setData('work_description', e.target.value)}
                                    placeholder="Indique las tareas concluidas..."
                                    className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-sky-500 focus:ring-1 focus:ring-sky-500 focus:outline-none"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Resultado de la labor</label>
                                    <select
                                        value={formFinalizar.data.result}
                                        onChange={(e) => formFinalizar.setData('result', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs"
                                    >
                                        <option value="Óptimo">Óptimo</option>
                                        <option value="Favorable">Favorable</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Requiere intervención complementaria">Requiere intervención complementaria</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Trabajador responsable</label>
                                    <select
                                        required
                                        value={formFinalizar.data.worker_id}
                                        onChange={(e) => formFinalizar.setData('worker_id', e.target.value)}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs"
                                    >
                                        <option value="">Seleccione un trabajador</option>
                                        {workers.map((worker) => (
                                            <option key={worker.id} value={worker.id}>
                                                {worker.full_name} — {worker.position}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold">Observaciones</label>
                                <textarea
                                    rows={2}
                                    value={formFinalizar.data.observations}
                                    onChange={(e) => formFinalizar.setData('observations', e.target.value)}
                                    placeholder="Observaciones finales..."
                                    className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold">Fotografía posterior / resultado (opcional)</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => formFinalizar.setData('photo_after', e.target.files ? e.target.files[0] : null)}
                                    className="text-muted-foreground w-full text-xs file:mr-3 file:rounded-xl file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sky-700 hover:file:bg-sky-100"
                                />
                            </div>

                            {mantenimientoSeleccionado.frequency_days && (
                                <p className="text-[11px] font-semibold text-emerald-600">
                                    ✓ Al guardar, el sistema calculará la próxima fecha automática sumando {mantenimientoSeleccionado.frequency_days}{' '}
                                    días y generará el Parte Oficial.
                                </p>
                            )}

                            <div className="flex justify-end gap-3 border-t pt-3">
                                <button
                                    type="button"
                                    onClick={() => setModalFinalizarAbierto(false)}
                                    className="hover:bg-muted rounded-xl border px-4 py-2 text-xs font-semibold"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={formFinalizar.processing}
                                    className="rounded-xl bg-sky-600 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-sky-700 disabled:opacity-50"
                                >
                                    {formFinalizar.processing ? 'Finalizando...' : 'Concluir y Generar Parte'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ========================================================================= */}
            {/* MODAL DETALLE / VISTA DEL PARTE DE MANTENIMIENTO                          */}
            {/* ========================================================================= */}
            {modalDetalleAbierto && mantenimientoSeleccionado && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
                    <div className="bg-background flex max-h-[90dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="bg-muted/20 flex items-center justify-between border-b px-6 py-4">
                            <div>
                                <h3 className="text-foreground flex items-center gap-2 text-lg font-bold">
                                    <FileText className="h-5 w-5 text-emerald-600" />
                                    Detalle del Mantenimiento — {mantenimientoSeleccionado.maintenance_number}
                                </h3>
                                <p className="text-muted-foreground text-xs">
                                    {mantenimientoSeleccionado.park_name} ({mantenimientoSeleccionado.park_code})
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalDetalleAbierto(false)}
                                className="text-muted-foreground hover:bg-muted rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="flex-1 space-y-4 overflow-y-auto p-6 text-xs">
                            <div className="bg-muted/10 grid grid-cols-2 gap-3 rounded-xl border p-4 sm:grid-cols-3">
                                <div>
                                    <span className="text-muted-foreground block">Modalidad:</span>
                                    <strong className="text-foreground">
                                        {mantenimientoSeleccionado.is_extraordinary ? 'Adicional' : 'Programado'}
                                    </strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Labor:</span>
                                    <strong className="text-foreground">{mantenimientoSeleccionado.maintenance_type}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Estado:</span>
                                    <strong className="text-emerald-600 capitalize">{mantenimientoSeleccionado.status}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Fecha programada:</span>
                                    <strong className="text-foreground">{mantenimientoSeleccionado.scheduled_date_formatted}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Fecha ejecutada:</span>
                                    <strong className="text-foreground">{mantenimientoSeleccionado.execution_date_formatted || '—'}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Horario y duración:</span>
                                    <strong className="text-foreground">
                                        {mantenimientoSeleccionado.start_time || '—'} a {mantenimientoSeleccionado.end_time || '—'}{' '}
                                        {mantenimientoSeleccionado.duration_minutes && `(${mantenimientoSeleccionado.duration_minutes}m)`}
                                    </strong>
                                </div>
                            </div>

                            {mantenimientoSeleccionado.reason && (
                                <div>
                                    <span className="text-muted-foreground block font-semibold">Motivo adicional:</span>
                                    <p className="mt-0.5 rounded-lg border bg-amber-50/50 p-2.5 text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                        {mantenimientoSeleccionado.reason}
                                    </p>
                                </div>
                            )}

                            {mantenimientoSeleccionado.work_description && (
                                <div>
                                    <span className="text-muted-foreground block font-semibold">Descripción del trabajo realizado:</span>
                                    <p className="bg-muted/20 text-foreground mt-0.5 rounded-lg border p-2.5">
                                        {mantenimientoSeleccionado.work_description}
                                    </p>
                                </div>
                            )}

                            {mantenimientoSeleccionado.observations && (
                                <div>
                                    <span className="text-muted-foreground block font-semibold">Observaciones:</span>
                                    <p className="bg-muted/20 text-foreground mt-0.5 rounded-lg border p-2.5">
                                        {mantenimientoSeleccionado.observations}
                                    </p>
                                </div>
                            )}

                            {/* Evidencia fotográfica */}
                            {(mantenimientoSeleccionado.photo_before_url || mantenimientoSeleccionado.photo_after_url) && (
                                <div>
                                    <span className="text-muted-foreground mb-2 block font-semibold">Evidencia Fotográfica:</span>
                                    <div className="grid grid-cols-2 gap-3">
                                        {mantenimientoSeleccionado.photo_before_url && (
                                            <div className="bg-muted/20 overflow-hidden rounded-xl border text-center">
                                                <img
                                                    src={mantenimientoSeleccionado.photo_before_url}
                                                    alt="Antes"
                                                    className="h-36 w-full object-cover"
                                                />
                                                <p className="text-muted-foreground py-1 text-[11px] font-semibold">Antes del trabajo</p>
                                            </div>
                                        )}
                                        {mantenimientoSeleccionado.photo_after_url && (
                                            <div className="bg-muted/20 overflow-hidden rounded-xl border text-center">
                                                <img
                                                    src={mantenimientoSeleccionado.photo_after_url}
                                                    alt="Posterior"
                                                    className="h-36 w-full object-cover"
                                                />
                                                <p className="text-muted-foreground py-1 text-[11px] font-semibold">Resultado posterior</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="bg-muted/10 flex items-center justify-between border-t px-6 py-4">
                            <a
                                href={`/mantenimientos/${mantenimientoSeleccionado.id}/documento`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700"
                            >
                                <Printer className="h-4 w-4" /> Imprimir Parte Oficial
                            </a>
                            <button
                                type="button"
                                onClick={() => setModalDetalleAbierto(false)}
                                className="hover:bg-muted rounded-xl border px-4 py-2 text-xs font-semibold"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ========================================================================= */}
            {/* MODAL CANCELAR MANTENIMIENTO                                              */}
            {/* ========================================================================= */}
            {modalCancelarAbierto && mantenimientoSeleccionado && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
                    <div className="bg-background flex max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="flex items-center justify-between border-b bg-red-500/10 px-6 py-4">
                            <h3 className="flex items-center gap-2 text-base font-bold text-red-900 dark:text-red-200">
                                <AlertTriangle className="h-5 w-5 text-red-600" />
                                Cancelar Mantenimiento
                            </h3>
                            <button
                                type="button"
                                onClick={() => setModalCancelarAbierto(false)}
                                className="text-muted-foreground hover:bg-muted rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitCancelar} className="space-y-4 p-6">
                            <p className="text-muted-foreground text-xs">
                                ¿Está seguro de cancelar el mantenimiento <strong>{mantenimientoSeleccionado.maintenance_number}</strong> para el
                                parque <strong>{mantenimientoSeleccionado.park_name}</strong>?
                            </p>

                            <div>
                                <label className="mb-1 block text-xs font-semibold">
                                    Motivo de la cancelación <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    required
                                    rows={3}
                                    value={formCancelar.data.reason}
                                    onChange={(e) => formCancelar.setData('reason', e.target.value)}
                                    placeholder="Indique la justificación de la cancelación..."
                                    className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-none"
                                />
                            </div>

                            <div className="flex justify-end gap-3 border-t pt-3">
                                <button
                                    type="button"
                                    onClick={() => setModalCancelarAbierto(false)}
                                    className="hover:bg-muted rounded-xl border px-4 py-2 text-xs font-semibold"
                                >
                                    Volver
                                </button>
                                <button
                                    type="submit"
                                    disabled={formCancelar.processing}
                                    className="rounded-xl bg-red-600 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-red-700 disabled:opacity-50"
                                >
                                    {formCancelar.processing ? 'Cancelando...' : 'Confirmar Cancelación'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            {/* ========================================================================= */}
            {/* PASO PREVIO: MOTIVO DEL RETRASO (SOLO ÓRDENES VENCIDAS)                    */}
            {/* ========================================================================= */}
            {modalJustificacionAbierto && mantenimientoSeleccionado && (
                <div className="fixed inset-0 z-[60] flex items-stretch justify-center bg-black/60 p-2 backdrop-blur-xs sm:items-center sm:p-4">
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="overdue-title"
                        className="bg-background flex h-[calc(100dvh-1rem)] max-h-[calc(100dvh-1rem)] w-full max-w-xl flex-col overflow-hidden rounded-xl border shadow-2xl sm:h-auto sm:max-h-[92dvh] sm:rounded-2xl"
                    >
                        <div className="flex shrink-0 items-start justify-between gap-3 border-b bg-amber-500/10 px-4 py-3 sm:px-6 sm:py-4">
                            <div className="min-w-0">
                                <h2
                                    id="overdue-title"
                                    className="flex items-center gap-2 text-sm font-bold text-amber-950 sm:text-lg dark:text-amber-100"
                                >
                                    <AlertTriangle className="h-5 w-5 shrink-0 text-amber-600" />
                                    Explica el motivo del retraso
                                </h2>
                                <p className="mt-0.5 text-xs text-amber-800 dark:text-amber-300">
                                    {mantenimientoSeleccionado.maintenance_number} · {mantenimientoSeleccionado.park_name}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalJustificacionAbierto(false)}
                                aria-label="Cerrar"
                                className="text-muted-foreground hover:bg-muted shrink-0 rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain p-4 sm:p-6">
                            <div className="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 dark:border-amber-900 dark:bg-amber-950/35">
                                <Clock className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                                <p className="text-[12px] leading-relaxed text-amber-900 dark:text-amber-200">
                                    Esta orden estaba programada para el <strong>{mantenimientoSeleccionado.scheduled_date_formatted}</strong>. Antes
                                    de continuar, registra el motivo o adjunta un documento de respaldo. Quedará guardado en el historial.
                                </p>
                            </div>

                            <div>
                                <div className="mb-1.5 flex items-end justify-between gap-3">
                                    <label htmlFor="overdue-reason" className="text-xs font-semibold">
                                        Motivo del retraso
                                    </label>
                                    <span className="text-muted-foreground text-[11px] tabular-nums">
                                        {justificacionMotivo.trim().length} caracteres
                                    </span>
                                </div>
                                <textarea
                                    id="overdue-reason"
                                    autoFocus
                                    rows={4}
                                    maxLength={1000}
                                    value={justificacionMotivo}
                                    onChange={(event) => {
                                        setJustificacionMotivo(event.target.value);
                                        setJustificacionError(null);
                                    }}
                                    placeholder="Ej.: No se contó con personal disponible porque la cuadrilla atendió una emergencia."
                                    aria-describedby="overdue-reason-help"
                                    className="bg-background w-full resize-y rounded-xl border px-3 py-2.5 text-sm shadow-2xs transition focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                />
                                <p id="overdue-reason-help" className="text-muted-foreground mt-1 text-[11px]">
                                    Si no adjuntas un documento, escribe al menos 10 caracteres.
                                </p>
                            </div>

                            <div>
                                <p className="mb-1.5 text-xs font-semibold">Documento de respaldo</p>
                                <input
                                    ref={justificacionArchivoRef}
                                    id="overdue-document"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                    className="peer sr-only"
                                    onChange={(event) => seleccionarJustificacionDocumento(event.target.files?.[0] ?? null)}
                                />

                                {justificacionDocumento ? (
                                    <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-900 dark:bg-emerald-950/35">
                                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-900">
                                            <FileCheck2 className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-xs font-semibold">{justificacionDocumento.name}</p>
                                            <p className="text-muted-foreground text-[11px]">
                                                {(justificacionDocumento.size / 1024 / 1024).toFixed(2)} MB
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setJustificacionDocumento(null);
                                                if (justificacionArchivoRef.current) {
                                                    justificacionArchivoRef.current.value = '';
                                                }
                                            }}
                                            aria-label="Quitar documento"
                                            className="text-muted-foreground hover:bg-muted rounded-lg p-2"
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </div>
                                ) : (
                                    <label
                                        htmlFor="overdue-document"
                                        className="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed px-4 py-3.5 transition peer-focus-visible:ring-2 peer-focus-visible:ring-amber-500 peer-focus-visible:ring-offset-2 hover:border-amber-400 hover:bg-amber-50/40 dark:hover:border-amber-700 dark:hover:bg-amber-950/20"
                                    >
                                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            <Upload className="size-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-xs font-semibold">Adjuntar PDF, imagen o Word</span>
                                            <span className="text-muted-foreground block text-[11px]">
                                                Hasta 10 MB · Opcional si escribes el motivo
                                            </span>
                                        </span>
                                    </label>
                                )}

                                {justificacionError && (
                                    <p role="alert" className="mt-2 text-[11px] font-medium text-rose-600 dark:text-rose-400">
                                        {justificacionError}
                                    </p>
                                )}
                            </div>

                            <p className="text-muted-foreground flex items-start gap-2 text-[11px] leading-relaxed">
                                <Paperclip className="mt-0.5 size-3.5 shrink-0" />
                                <span>
                                    El documento reemplaza la explicación escrita. Puedes presentar solo el motivo, solo el documento o ambos.
                                </span>
                            </p>
                        </div>

                        <div className="bg-background/95 flex shrink-0 flex-col-reverse gap-2 border-t px-4 pt-3 pb-4 sm:flex-row sm:justify-end sm:px-6 sm:pb-5">
                            <button
                                type="button"
                                onClick={() => setModalJustificacionAbierto(false)}
                                className="hover:bg-muted w-full rounded-xl border px-4 py-2.5 text-xs font-semibold sm:w-auto"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={continuarJustificacion}
                                className="w-full rounded-xl bg-emerald-700 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-800 sm:w-auto"
                            >
                                Continuar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
