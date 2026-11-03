import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardList, Edit, Eye, FileText, Phone, Power, Search, Shield, UserPlus, Users, Wrench, X } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';

// Tipos
export interface WorkerItem {
    id: number;
    code: string;
    document_number: string | null;
    first_name: string;
    last_name: string;
    full_name: string;
    position: string;
    phone: string | null;
    crew: string | null;
    observations: string | null;
    is_active: boolean;
    maintenance_records_count: number;
    maintenances_completed_count: number;
    created_at: string | null;
}

export interface MaintenimientoTrabajador {
    id: number;
    maintenance_number: string;
    park_name: string;
    park_code: string;
    maintenance_type: string;
    scheduled_date: string | null;
    execution_date: string | null;
    status: string;
    duration_minutes: number | null;
    result: string | null;
}

type WorkerFormData = {
    code: string;
    document_number: string;
    first_name: string;
    last_name: string;
    position: string;
    phone: string;
    crew: string;
    observations: string;
    is_active: boolean;
};

interface Props {
    workers: WorkerItem[];
    nextGeneratedCode: string;
    workerMantenimientos?: MaintenimientoTrabajador[];
    workerSeleccionado?: {
        id: number;
        code: string;
        full_name: string;
        position: string;
        crew: string | null;
    } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trabajadores', href: '/trabajadores' },
];

const CARGOS = [
    'Jardinero',
    'Capataz',
    'Técnico Hidráulico',
    'Operador de Maquinaria',
    'Podador',
    'Fumigador',
    'Auxiliar de Mantenimiento',
    'Supervisor de Áreas Verdes',
    'Chofer de Cisterna',
];

const CUADRILLAS = [
    'Cuadrilla 1 - Riego y Cisternas',
    'Cuadrilla 2 - Poda y Deshierbe',
    'Cuadrilla 3 - Limpieza y Mantenimiento',
    'Cuadrilla 4 - Fumigación',
    'Sin cuadrilla asignada',
];

const ESTILOS_ESTADO: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    en_proceso: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    finalizado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    completado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    ejecutado: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    cancelado: 'bg-slate-200 text-slate-700 dark:bg-neutral-800 dark:text-neutral-300',
    vencido: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

export default function WorkersIndex({ workers, nextGeneratedCode, workerMantenimientos, workerSeleccionado }: Props) {
    // Filtros
    const [busqueda, setBusqueda] = useState('');
    const [filtroEstado, setFiltroEstado] = useState<string>('todos');
    const [filtroCuadrilla, setFiltroCuadrilla] = useState<string>('todos');

    // Modales
    const [modalFormularioAbierto, setModalFormularioAbierto] = useState(false);
    const [modalDetalleAbierto, setModalDetalleAbierto] = useState(false);
    const [modalHistorialAbierto, setModalHistorialAbierto] = useState(!!workerMantenimientos);

    useEffect(() => {
        if (workerMantenimientos && workerSeleccionado) {
            setModalHistorialAbierto(true);
        }
    }, [workerMantenimientos, workerSeleccionado]);

    // Trabajador en edición / consulta
    const [trabajadorEnEdicion, setTrabajadorEnEdicion] = useState<WorkerItem | null>(null);
    const [trabajadorConsulta, setTrabajadorConsulta] = useState<WorkerItem | null>(null);

    // Formulario de registro / edición
    const formTrabajador = useForm<WorkerFormData>({
        code: nextGeneratedCode,
        document_number: '',
        first_name: '',
        last_name: '',
        position: 'Jardinero',
        phone: '',
        crew: 'Cuadrilla 1 - Riego y Cisternas',
        observations: '',
        is_active: true,
    });

    // Trabajadores filtrados
    const trabajadoresFiltrados = useMemo(() => {
        return workers.filter((w) => {
            const texto = busqueda.toLowerCase().trim();
            const coincideTexto =
                w.full_name.toLowerCase().includes(texto) ||
                w.code.toLowerCase().includes(texto) ||
                (w.document_number ?? '').toLowerCase().includes(texto) ||
                w.position.toLowerCase().includes(texto) ||
                (w.crew ?? '').toLowerCase().includes(texto);

            const coincideEstado =
                filtroEstado === 'todos' || (filtroEstado === 'activo' && w.is_active) || (filtroEstado === 'inactivo' && !w.is_active);

            const coincideCuadrilla = filtroCuadrilla === 'todos' || w.crew === filtroCuadrilla;

            return coincideTexto && coincideEstado && coincideCuadrilla;
        });
    }, [workers, busqueda, filtroEstado, filtroCuadrilla]);

    // Estadísticas
    const totalActivos = workers.filter((w) => w.is_active).length;
    const totalInactivos = workers.filter((w) => !w.is_active).length;
    const cuadrillasUnicas = [...new Set(workers.map((w) => w.crew).filter(Boolean))];

    // Abrir nuevo
    const abrirCreacion = () => {
        setTrabajadorEnEdicion(null);
        formTrabajador.reset();
        formTrabajador.setData({
            code: nextGeneratedCode,
            document_number: '',
            first_name: '',
            last_name: '',
            position: 'Jardinero',
            phone: '',
            crew: 'Cuadrilla 1 - Riego y Cisternas',
            observations: '',
            is_active: true,
        });
        setModalFormularioAbierto(true);
    };

    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('accion') === 'registrar') {
            abrirCreacion();
        }
    }, []);

    // Abrir edición
    const abrirEdicion = (worker: WorkerItem) => {
        setTrabajadorEnEdicion(worker);
        formTrabajador.setData({
            code: worker.code,
            document_number: worker.document_number ?? '',
            first_name: worker.first_name,
            last_name: worker.last_name,
            position: worker.position,
            phone: worker.phone ?? '',
            crew: worker.crew ?? '',
            observations: worker.observations ?? '',
            is_active: worker.is_active,
        });
        setModalFormularioAbierto(true);
    };

    // Abrir detalle / consulta
    const abrirConsulta = (worker: WorkerItem) => {
        setTrabajadorConsulta(worker);
        setModalDetalleAbierto(true);
    };

    // Guardar trabajador
    const submitTrabajador = (e: FormEvent) => {
        e.preventDefault();
        if (trabajadorEnEdicion) {
            formTrabajador.put(`/trabajadores/${trabajadorEnEdicion.id}`, {
                onSuccess: () => setModalFormularioAbierto(false),
            });
        } else {
            formTrabajador.post('/trabajadores', {
                onSuccess: () => setModalFormularioAbierto(false),
            });
        }
    };

    // Toggle estado
    const toggleEstado = (worker: WorkerItem) => {
        if (confirm(`¿Desea ${worker.is_active ? 'desactivar' : 'activar'} al trabajador ${worker.full_name}?`)) {
            router.patch(`/trabajadores/${worker.id}/toggle-status`);
        }
    };

    // Consultar mantenimientos
    const consultarMantenimientos = (worker: WorkerItem) => {
        router.get(`/trabajadores/${worker.id}/mantenimientos`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catálogo de Trabajadores - Sistema de Áreas Verdes" />

            <div className="page-shell">
                <div className="page-container">
                    <PageHeader
                        title="Trabajadores"
                        description="Registro del personal de campo para la trazabilidad de mantenimientos. Los trabajadores no acceden al sistema."
                        icon={Users}
                        meta={
                            <span className="pill bg-muted text-muted-foreground">
                                {workers.length} {workers.length === 1 ? 'registro' : 'registros'}
                            </span>
                        }
                        actions={
                            <Button onClick={abrirCreacion} className="h-9 rounded-[9px]">
                                <UserPlus className="size-4" />
                                Registrar trabajador
                            </Button>
                        }
                    />

                    {/* TARJETAS RESUMEN */}
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard label="Total de trabajadores" value={workers.length} hint={`${totalActivos} activos`} icon={Users} tone="success" />
                        <StatCard label="Cuadrillas" value={cuadrillasUnicas.length} hint="Equipos de trabajo" icon={Shield} tone="info" />
                        <StatCard
                            label="Mantenimientos asignados"
                            value={workers.reduce((acc, w) => acc + w.maintenance_records_count, 0)}
                            hint="Labores registradas"
                            icon={Wrench}
                            tone="warning"
                        />
                        <StatCard label="Personal inactivo" value={totalInactivos} hint="No disponible" icon={Power} tone="danger" />
                    </div>

                    {/* LISTADO DE TRABAJADORES */}
                    <section className="surface min-w-0 overflow-hidden">
                        {/* FILTROS Y BÚSQUEDA */}
                        <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex flex-1 flex-col gap-2.5 sm:flex-row sm:items-center">
                                <div className="relative flex-1 sm:max-w-[300px]">
                                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <input
                                        type="text"
                                        value={busqueda}
                                        onChange={(e) => setBusqueda(e.target.value)}
                                        placeholder="Buscar por nombre, código, DNI o cargo"
                                        aria-label="Buscar trabajadores"
                                        className="field-control pl-9"
                                    />
                                </div>

                                <select
                                    value={filtroEstado}
                                    onChange={(e) => setFiltroEstado(e.target.value)}
                                    aria-label="Filtrar por estado"
                                    className="field-control sm:w-auto"
                                >
                                    <option value="todos">Todos los estados</option>
                                    <option value="activo">Activos</option>
                                    <option value="inactivo">Inactivos</option>
                                </select>

                                <select
                                    value={filtroCuadrilla}
                                    onChange={(e) => setFiltroCuadrilla(e.target.value)}
                                    aria-label="Filtrar por cuadrilla"
                                    className="field-control sm:w-auto"
                                >
                                    <option value="todos">Todas las cuadrillas</option>
                                    {cuadrillasUnicas.map((c) => (
                                        <option key={c} value={c!}>
                                            {c}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <p className="text-muted-foreground text-[11.5px] whitespace-nowrap">
                                Mostrando <span className="text-foreground font-semibold tabular-nums">{trabajadoresFiltrados.length}</span> de{' '}
                                {workers.length} trabajadores
                            </p>
                        </div>

                        {/* TABLA */}
                        <div className="w-full overflow-x-auto">
                            <table className="data-table min-w-[940px]">
                                <thead>
                                    <tr>
                                        <th className="w-10 text-center">N.º</th>
                                        <th>Código</th>
                                        <th>Documento</th>
                                        <th>Nombres y apellidos</th>
                                        <th>Cargo o función</th>
                                        <th>Teléfono</th>
                                        <th>Cuadrilla</th>
                                        <th className="text-center">Estado</th>
                                        <th className="text-right">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {trabajadoresFiltrados.length > 0 ? (
                                        trabajadoresFiltrados.map((worker, index) => (
                                            <tr key={worker.id}>
                                                <td className="text-muted-foreground text-center font-mono text-[11.5px]">{index + 1}</td>

                                                <td>
                                                    <span className="bg-brand-soft text-primary inline-flex rounded-[6px] px-2 py-0.5 font-mono text-[11px] font-semibold whitespace-nowrap">
                                                        {worker.code}
                                                    </span>
                                                </td>

                                                <td className="text-[12px] font-medium tabular-nums">
                                                    {worker.document_number || <span className="text-muted-foreground italic">Sin documento</span>}
                                                </td>

                                                <td>
                                                    <div className="flex items-center gap-2.5">
                                                        <div className="bg-brand-soft text-primary grid size-8 shrink-0 place-items-center rounded-full text-[11px] font-semibold">
                                                            {worker.first_name.charAt(0)}
                                                            {worker.last_name.charAt(0)}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="text-foreground truncate font-semibold">{worker.full_name}</p>
                                                            {worker.maintenance_records_count > 0 && (
                                                                <span className="text-muted-foreground text-[11px] tabular-nums">
                                                                    {worker.maintenances_completed_count}/{worker.maintenance_records_count}{' '}
                                                                    mantenimientos
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="text-[12px] font-medium">{worker.position}</td>

                                                <td className="text-[12px] tabular-nums">
                                                    {worker.phone ? (
                                                        <span className="inline-flex items-center gap-1.5">
                                                            <Phone className="text-muted-foreground size-3" />
                                                            {worker.phone}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </td>

                                                <td>
                                                    {worker.crew ? (
                                                        <span className="pill bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">
                                                            <Shield className="size-3" />
                                                            {worker.crew}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-[12px] italic">Sin cuadrilla</span>
                                                    )}
                                                </td>

                                                <td className="text-center">
                                                    <span
                                                        className={`pill ${
                                                            worker.is_active
                                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                                : 'bg-muted text-muted-foreground'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`size-1.5 rounded-full ${worker.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/60'}`}
                                                        />
                                                        {worker.is_active ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>

                                                <td>
                                                    <div className="flex items-center justify-end gap-0.5">
                                                        <button
                                                            type="button"
                                                            onClick={() => abrirConsulta(worker)}
                                                            title="Consultar datos del trabajador"
                                                            aria-label={`Consultar datos de ${worker.full_name}`}
                                                            className="text-muted-foreground hover:bg-accent hover:text-accent-foreground grid size-8 place-items-center rounded-[8px] transition"
                                                        >
                                                            <Eye className="size-4" />
                                                        </button>

                                                        <RowActions
                                                            label={`Acciones de ${worker.full_name}`}
                                                            items={[
                                                                { label: 'Editar trabajador', icon: Edit, onSelect: () => abrirEdicion(worker) },
                                                                {
                                                                    label: 'Mantenimientos realizados',
                                                                    icon: ClipboardList,
                                                                    onSelect: () => consultarMantenimientos(worker),
                                                                },
                                                                {
                                                                    label: worker.is_active ? 'Desactivar trabajador' : 'Activar trabajador',
                                                                    icon: Power,
                                                                    danger: worker.is_active,
                                                                    onSelect: () => toggleEstado(worker),
                                                                },
                                                            ]}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={9} className="py-14 text-center">
                                                <Users className="text-muted-foreground/40 mx-auto mb-2 size-8" />
                                                <p className="font-medium">No se encontraron trabajadores</p>
                                                <p className="text-muted-foreground mt-1 text-[12px]">
                                                    {workers.length === 0
                                                        ? 'Comience registrando al personal de campo.'
                                                        : 'Pruebe con otro filtro o término de búsqueda.'}
                                                </p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            {/* ========================================================================= */}
            {/* MODAL DE REGISTRO / EDICIÓN DE TRABAJADOR                                 */}
            {/* ========================================================================= */}
            {modalFormularioAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
                    <div className="bg-background flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="bg-muted/20 flex items-center justify-between border-b px-6 py-4">
                            <div>
                                <h2 className="text-foreground flex items-center gap-2 text-xl font-bold">
                                    <UserPlus className="h-5 w-5 text-emerald-600" />
                                    {trabajadorEnEdicion ? `Editar: ${trabajadorEnEdicion.full_name}` : 'Registro de Nuevo Trabajador'}
                                </h2>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    {trabajadorEnEdicion
                                        ? 'Actualice los datos del trabajador.'
                                        : 'Complete los datos de identificación del personal de campo.'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalFormularioAbierto(false)}
                                className="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg p-2 transition"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitTrabajador} className="flex-1 space-y-5 overflow-y-auto p-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {/* Código automático */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        Código <span className="text-emerald-600">(automático)</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={formTrabajador.data.code}
                                        className="bg-muted/30 w-full rounded-xl border px-3 py-2 font-mono text-xs font-bold shadow-2xs"
                                        readOnly
                                    />
                                </div>

                                {/* DNI / Documento */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        DNI o documento de identidad <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        maxLength={20}
                                        value={formTrabajador.data.document_number}
                                        onChange={(e) => formTrabajador.setData('document_number', e.target.value)}
                                        placeholder="Ej: 12345678"
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                    {formTrabajador.errors.document_number && (
                                        <p className="mt-0.5 text-xs text-red-500">{formTrabajador.errors.document_number}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {/* Nombres */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        Nombres <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={formTrabajador.data.first_name}
                                        onChange={(e) => formTrabajador.setData('first_name', e.target.value)}
                                        placeholder="Ej: Carlos Eduardo"
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                    {formTrabajador.errors.first_name && (
                                        <p className="mt-0.5 text-xs text-red-500">{formTrabajador.errors.first_name}</p>
                                    )}
                                </div>

                                {/* Apellidos */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        Apellidos <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={formTrabajador.data.last_name}
                                        onChange={(e) => formTrabajador.setData('last_name', e.target.value)}
                                        placeholder="Ej: Méndez Torres"
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                    {formTrabajador.errors.last_name && (
                                        <p className="mt-0.5 text-xs text-red-500">{formTrabajador.errors.last_name}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {/* Cargo o función */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">
                                        Cargo o función <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        list="lista-cargos"
                                        required
                                        value={formTrabajador.data.position}
                                        onChange={(e) => formTrabajador.setData('position', e.target.value)}
                                        placeholder="Seleccione o ingrese el cargo"
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                    <datalist id="lista-cargos">
                                        {CARGOS.map((c) => (
                                            <option key={c} value={c} />
                                        ))}
                                    </datalist>
                                </div>

                                {/* Teléfono */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Teléfono (opcional)</label>
                                    <input
                                        type="text"
                                        maxLength={30}
                                        value={formTrabajador.data.phone}
                                        onChange={(e) => formTrabajador.setData('phone', e.target.value)}
                                        placeholder="Ej: 943 123 456"
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {/* Cuadrilla */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Cuadrilla (opcional)</label>
                                    <input
                                        type="text"
                                        list="lista-cuadrillas"
                                        value={formTrabajador.data.crew}
                                        onChange={(e) => formTrabajador.setData('crew', e.target.value)}
                                        placeholder="Seleccione o ingrese cuadrilla"
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    />
                                    <datalist id="lista-cuadrillas">
                                        {CUADRILLAS.map((c) => (
                                            <option key={c} value={c} />
                                        ))}
                                    </datalist>
                                </div>

                                {/* Estado */}
                                <div>
                                    <label className="mb-1 block text-xs font-semibold">Estado</label>
                                    <select
                                        value={formTrabajador.data.is_active ? 'activo' : 'inactivo'}
                                        onChange={(e) => formTrabajador.setData('is_active', e.target.value === 'activo')}
                                        className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                    >
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                            </div>

                            {/* Observaciones */}
                            <div>
                                <label className="mb-1 block text-xs font-semibold">Observaciones</label>
                                <textarea
                                    rows={2}
                                    value={formTrabajador.data.observations}
                                    onChange={(e) => formTrabajador.setData('observations', e.target.value)}
                                    placeholder="Notas internas sobre el trabajador..."
                                    className="bg-background w-full rounded-xl border px-3 py-2 text-xs shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                />
                            </div>

                            {/* Botones */}
                            <div className="flex justify-end gap-3 border-t pt-3">
                                <button
                                    type="button"
                                    onClick={() => setModalFormularioAbierto(false)}
                                    className="hover:bg-muted rounded-xl border px-4 py-2 text-xs font-semibold"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={formTrabajador.processing}
                                    className="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {formTrabajador.processing
                                        ? 'Guardando...'
                                        : trabajadorEnEdicion
                                          ? 'Actualizar Trabajador'
                                          : 'Registrar Trabajador'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ========================================================================= */}
            {/* MODAL DETALLE / CONSULTA DE TRABAJADOR                                    */}
            {/* ========================================================================= */}
            {modalDetalleAbierto && trabajadorConsulta && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
                    <div className="bg-background flex max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="bg-muted/20 flex items-center justify-between border-b px-6 py-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-base font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {trabajadorConsulta.first_name.charAt(0)}
                                    {trabajadorConsulta.last_name.charAt(0)}
                                </div>
                                <div>
                                    <h3 className="text-foreground text-lg font-bold">{trabajadorConsulta.full_name}</h3>
                                    <p className="text-muted-foreground font-mono text-xs">{trabajadorConsulta.code}</p>
                                </div>
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
                            <div className="bg-muted/10 grid grid-cols-2 gap-4 rounded-xl border p-4">
                                <div>
                                    <span className="text-muted-foreground block">Documento de identidad:</span>
                                    <strong className="text-foreground">{trabajadorConsulta.document_number || 'Sin documento'}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Cargo o función:</span>
                                    <strong className="text-foreground">{trabajadorConsulta.position}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Teléfono:</span>
                                    <strong className="text-foreground">{trabajadorConsulta.phone || '—'}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Cuadrilla:</span>
                                    <strong className="text-foreground">{trabajadorConsulta.crew || 'Sin cuadrilla'}</strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Estado:</span>
                                    <strong className={trabajadorConsulta.is_active ? 'text-emerald-600' : 'text-slate-500'}>
                                        {trabajadorConsulta.is_active ? 'Activo' : 'Inactivo'}
                                    </strong>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block">Fecha de registro:</span>
                                    <strong className="text-foreground">{trabajadorConsulta.created_at}</strong>
                                </div>
                            </div>

                            <div className="bg-muted/10 rounded-xl border p-4">
                                <h4 className="text-muted-foreground mb-2 text-xs font-bold tracking-wider uppercase">Resumen de actividad</h4>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="rounded-xl bg-emerald-50 p-3 text-center dark:bg-emerald-950/30">
                                        <p className="text-2xl font-bold text-emerald-600">{trabajadorConsulta.maintenances_completed_count}</p>
                                        <p className="text-muted-foreground text-[11px]">Mantenimientos completados</p>
                                    </div>
                                    <div className="dark:bg-muted/60 rounded-xl bg-slate-50 p-3 text-center">
                                        <p className="text-foreground text-2xl font-bold">{trabajadorConsulta.maintenance_records_count}</p>
                                        <p className="text-muted-foreground text-[11px]">Total de asignaciones</p>
                                    </div>
                                </div>
                            </div>

                            {trabajadorConsulta.observations && (
                                <div>
                                    <span className="text-muted-foreground block font-semibold">Observaciones:</span>
                                    <p className="bg-muted/20 text-foreground mt-0.5 rounded-lg border p-2.5">{trabajadorConsulta.observations}</p>
                                </div>
                            )}
                        </div>

                        <div className="bg-muted/10 flex items-center justify-between border-t px-6 py-4">
                            <button
                                type="button"
                                onClick={() => {
                                    setModalDetalleAbierto(false);
                                    consultarMantenimientos(trabajadorConsulta);
                                }}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-amber-100 px-4 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-200"
                            >
                                <ClipboardList className="h-4 w-4" /> Ver mantenimientos realizados
                            </button>
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
            {/* MODAL HISTORIAL DE MANTENIMIENTOS DEL TRABAJADOR                          */}
            {/* ========================================================================= */}
            {modalHistorialAbierto && workerMantenimientos && workerSeleccionado && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
                    <div className="bg-background flex max-h-[90dvh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border shadow-2xl">
                        <div className="bg-muted/20 flex items-center justify-between border-b px-6 py-4">
                            <div>
                                <h3 className="text-foreground flex items-center gap-2 text-lg font-bold">
                                    <ClipboardList className="h-5 w-5 text-amber-600" />
                                    Mantenimientos realizados — {workerSeleccionado.full_name}
                                </h3>
                                <p className="text-muted-foreground text-xs">
                                    {workerSeleccionado.code} • {workerSeleccionado.position}
                                    {workerSeleccionado.crew && ` • ${workerSeleccionado.crew}`}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalHistorialAbierto(false)}
                                className="text-muted-foreground hover:bg-muted rounded-lg p-2"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6">
                            {workerMantenimientos.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                                            <tr>
                                                <th className="px-3 py-2">N° PM</th>
                                                <th className="px-3 py-2">Parque</th>
                                                <th className="px-3 py-2">Tipo</th>
                                                <th className="px-3 py-2">Programada</th>
                                                <th className="px-3 py-2">Ejecutada</th>
                                                <th className="px-3 py-2 text-center">Estado</th>
                                                <th className="px-3 py-2 text-center">Duración</th>
                                                <th className="px-3 py-2">Resultado</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border divide-y">
                                            {workerMantenimientos.map((m) => (
                                                <tr key={m.id} className="hover:bg-muted/20">
                                                    <td className="px-3 py-2 font-mono font-bold">
                                                        <span className="dark:bg-muted/60 inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5">
                                                            <FileText className="h-3 w-3 text-emerald-600" />
                                                            {m.maintenance_number}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 font-medium">
                                                        {m.park_name}
                                                        <span className="text-muted-foreground block">{m.park_code}</span>
                                                    </td>
                                                    <td className="px-3 py-2">{m.maintenance_type}</td>
                                                    <td className="px-3 py-2">{m.scheduled_date ?? '—'}</td>
                                                    <td className="px-3 py-2 font-medium">{m.execution_date ?? '—'}</td>
                                                    <td className="px-3 py-2 text-center">
                                                        <span
                                                            className={`inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${ESTILOS_ESTADO[m.status] ?? 'bg-slate-100 text-slate-700'}`}
                                                        >
                                                            {m.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 text-center font-medium">
                                                        {m.duration_minutes ? `${m.duration_minutes}m` : '—'}
                                                    </td>
                                                    <td className="px-3 py-2">{m.result ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="text-muted-foreground py-10 text-center">
                                    <ClipboardList className="text-muted-foreground/50 mx-auto mb-2 h-8 w-8" />
                                    <p className="font-medium">Sin mantenimientos registrados</p>
                                    <p className="mt-1 text-xs">Este trabajador aún no tiene labores asignadas en el sistema.</p>
                                </div>
                            )}
                        </div>

                        <div className="bg-muted/20 flex justify-end border-t px-6 py-3.5">
                            <button
                                type="button"
                                onClick={() => setModalHistorialAbierto(false)}
                                className="hover:bg-muted rounded-xl border px-4 py-2 text-xs font-semibold"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
