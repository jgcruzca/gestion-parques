import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, CalendarClock, CheckCircle2, Edit, ImagePlus, Layers3, Plus, Power, Settings2, SlidersHorizontal, Wrench, X } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

type Tab = 'areas' | 'maintenances' | 'rules' | 'institution';
type CatalogKind = 'area' | 'maintenance';

interface CatalogItem {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
}

interface MaintenanceRule {
    id: number;
    green_area_type: string;
    maintenance_type: string;
    frequency_days: number;
    advance_days: number;
    is_active: boolean;
}

interface Institution {
    organization_name: string;
    unit_name: string;
    logo_url: string | null;
    address: string | null;
    manager_name: string | null;
    management_year: string | null;
    document_footer: string | null;
}

interface Props {
    greenAreaTypes: CatalogItem[];
    maintenanceTypes: CatalogItem[];
    maintenanceRules: MaintenanceRule[];
    institution: Institution;
    hasDemoData: boolean;
}

type CatalogForm = {
    name: string;
    description: string;
    is_active: boolean;
};

type RuleForm = {
    green_area_type: string;
    maintenance_type: string;
    frequency_days: number;
    advance_days: number;
    is_active: boolean;
};

type InstitutionForm = {
    organization_name: string;
    unit_name: string;
    logo: File | null;
    address: string;
    manager_name: string;
    management_year: string;
    document_footer: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Configuración', href: '/configuracion' },
];

const tabs: Array<{ id: Tab; label: string; description: string; icon: typeof Layers3 }> = [
    { id: 'areas', label: 'Tipos de áreas verdes', description: 'Catálogo para clasificar parques', icon: Layers3 },
    { id: 'maintenances', label: 'Tipos de mantenimiento', description: 'Labores disponibles en el sistema', icon: Wrench },
    { id: 'rules', label: 'Programación por frecuencia', description: 'Reglas para fechas y alertas', icon: CalendarClock },
    { id: 'institution', label: 'Mi organización', description: 'Información para documentos', icon: Building2 },
];

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={`pill ${
                active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-muted text-muted-foreground'
            }`}
        >
            <span className={`size-1.5 rounded-full ${active ? 'bg-emerald-500' : 'bg-muted-foreground/60'}`} />
            {active ? 'Activo' : 'Inactivo'}
        </span>
    );
}

export default function ConfigurationIndex({ greenAreaTypes, maintenanceTypes, maintenanceRules, institution, hasDemoData }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('areas');
    const [catalogModalOpen, setCatalogModalOpen] = useState(false);
    const [catalogKind, setCatalogKind] = useState<CatalogKind>('area');
    const [editingCatalog, setEditingCatalog] = useState<CatalogItem | null>(null);
    const [ruleModalOpen, setRuleModalOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<MaintenanceRule | null>(null);

    const catalogForm = useForm<CatalogForm>({ name: '', description: '', is_active: true });
    const ruleForm = useForm<RuleForm>({
        green_area_type: greenAreaTypes.find((item) => item.is_active)?.name ?? '',
        maintenance_type: maintenanceTypes.find((item) => item.is_active)?.name ?? '',
        frequency_days: 7,
        advance_days: 1,
        is_active: true,
    });
    const institutionForm = useForm<InstitutionForm>({
        organization_name: institution.organization_name,
        unit_name: institution.unit_name,
        logo: null,
        address: institution.address ?? '',
        manager_name: institution.manager_name ?? '',
        management_year: institution.management_year ?? new Date().getFullYear().toString(),
        document_footer: institution.document_footer ?? '',
    });

    const openCatalog = (kind: CatalogKind, item?: CatalogItem) => {
        setCatalogKind(kind);
        setEditingCatalog(item ?? null);
        catalogForm.clearErrors();
        catalogForm.setData({
            name: item?.name ?? '',
            description: item?.description ?? '',
            is_active: item?.is_active ?? true,
        });
        setCatalogModalOpen(true);
    };

    const submitCatalog = (event: FormEvent) => {
        event.preventDefault();
        const base = catalogKind === 'area' ? '/configuracion/tipos-area' : '/configuracion/tipos-mantenimiento';
        const options = { onSuccess: () => setCatalogModalOpen(false), preserveScroll: true };

        if (editingCatalog) {
            catalogForm.put(`${base}/${editingCatalog.id}`, options);
        } else {
            catalogForm.post(base, options);
        }
    };

    const toggleCatalog = (kind: CatalogKind, item: CatalogItem) => {
        const label = item.is_active ? 'desactivar' : 'activar';
        if (!confirm(`¿Desea ${label} "${item.name}"? Los registros relacionados se conservarán.`)) return;
        const base = kind === 'area' ? '/configuracion/tipos-area' : '/configuracion/tipos-mantenimiento';
        router.patch(`${base}/${item.id}/estado`, {}, { preserveScroll: true });
    };

    const openRule = (rule?: MaintenanceRule) => {
        setEditingRule(rule ?? null);
        ruleForm.clearErrors();
        ruleForm.setData({
            green_area_type: rule?.green_area_type ?? greenAreaTypes.find((item) => item.is_active)?.name ?? '',
            maintenance_type: rule?.maintenance_type ?? maintenanceTypes.find((item) => item.is_active)?.name ?? '',
            frequency_days: rule?.frequency_days ?? 7,
            advance_days: rule?.advance_days ?? 1,
            is_active: rule?.is_active ?? true,
        });
        setRuleModalOpen(true);
    };

    const submitRule = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => setRuleModalOpen(false), preserveScroll: true };
        if (editingRule) {
            ruleForm.put(`/configuracion/reglas/${editingRule.id}`, options);
        } else {
            ruleForm.post('/configuracion/reglas', options);
        }
    };

    const toggleRule = (rule: MaintenanceRule) => {
        const label = rule.is_active ? 'desactivar' : 'activar';
        if (!confirm(`¿Desea ${label} esta regla de programación?`)) return;
        router.patch(`/configuracion/reglas/${rule.id}/estado`, {}, { preserveScroll: true });
    };

    const submitInstitution = (event: FormEvent) => {
        event.preventDefault();
        institutionForm.post('/configuracion/datos-institucionales', { forceFormData: true, preserveScroll: true });
    };

    const activeRules = maintenanceRules.filter((rule) => rule.is_active).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuración - Sistema de Áreas Verdes" />

            <div className="page-shell">
                <div className="page-container">
                    <PageHeader
                        title="Configuración"
                        description="Catálogos generales, reglas automáticas de programación e información de tu organización para documentos."
                        icon={Settings2}
                        actions={
                            <Button asChild variant="outline" className="h-9 rounded-[9px]">
                                <Link href="/mantenimientos">
                                    <CalendarClock className="size-4" /> Ver mantenimientos
                                </Link>
                            </Button>
                        }
                    />

                    {hasDemoData && (
                        <div className="flex flex-col gap-3 rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-amber-500/30 dark:bg-amber-950/30">
                            <p className="text-amber-900 dark:text-amber-200">
                                Tu cuenta incluye parques y trabajadores de <strong>demostración</strong> para que explores el sistema. Cuando quieras
                                empezar con tus propios datos, elimínalos.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-9 shrink-0 rounded-[9px]"
                                onClick={() => {
                                    if (confirm('¿Eliminar todos los datos de demostración? Esta acción no se puede deshacer.')) {
                                        router.delete('/configuracion/datos-demo', { preserveScroll: true });
                                    }
                                }}
                            >
                                Eliminar datos de demostración
                            </Button>
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <StatCard
                            label="Tipos de áreas activos"
                            value={greenAreaTypes.filter((item) => item.is_active).length}
                            hint={`${greenAreaTypes.length} en el catálogo`}
                            icon={Layers3}
                            tone="success"
                        />
                        <StatCard
                            label="Mantenimientos activos"
                            value={maintenanceTypes.filter((item) => item.is_active).length}
                            hint={`${maintenanceTypes.length} en el catálogo`}
                            icon={Wrench}
                            tone="info"
                        />
                        <StatCard
                            label="Reglas que generan alertas"
                            value={activeRules}
                            hint={`${maintenanceRules.length} reglas registradas`}
                            icon={CalendarClock}
                            tone="warning"
                        />
                    </div>

                    <nav className="surface grid grid-cols-1 gap-1 p-1.5 sm:grid-cols-2 xl:grid-cols-4" aria-label="Secciones de configuración">
                        {tabs.map((tab) => {
                            const Icon = tab.icon;
                            const selected = activeTab === tab.id;

                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => setActiveTab(tab.id)}
                                    aria-current={selected ? 'page' : undefined}
                                    className={`flex items-start gap-2.5 rounded-[10px] px-3 py-2.5 text-left transition ${
                                        selected
                                            ? 'bg-brand-soft text-primary ring-primary/15 ring-1 ring-inset'
                                            : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                                    }`}
                                >
                                    <Icon className="mt-0.5 size-4 shrink-0" />
                                    <span className="min-w-0">
                                        <span className={`block text-[12.5px] font-semibold ${selected ? 'text-foreground' : ''}`}>{tab.label}</span>
                                        <span className="mt-0.5 block text-[11px] leading-snug">{tab.description}</span>
                                    </span>
                                </button>
                            );
                        })}
                    </nav>

                    {activeTab === 'areas' && (
                        <CatalogSection
                            title="Tipos de áreas verdes"
                            description="Defina las categorías disponibles al registrar parques y áreas verdes. No se eliminan categorías relacionadas: se desactivan para conservar el historial."
                            items={greenAreaTypes}
                            onCreate={() => openCatalog('area')}
                            onEdit={(item) => openCatalog('area', item)}
                            onToggle={(item) => toggleCatalog('area', item)}
                        />
                    )}

                    {activeTab === 'maintenances' && (
                        <CatalogSection
                            title="Tipos de mantenimiento"
                            description="Administre las labores que podrán utilizarse en reglas, programaciones y mantenimientos adicionales."
                            items={maintenanceTypes}
                            onCreate={() => openCatalog('maintenance')}
                            onEdit={(item) => openCatalog('maintenance', item)}
                            onToggle={(item) => toggleCatalog('maintenance', item)}
                        />
                    )}

                    {activeTab === 'rules' && (
                        <section className="surface min-w-0 overflow-hidden">
                            <div className="flex flex-col justify-between gap-3 border-b p-4 sm:flex-row sm:items-center">
                                <div>
                                    <h2 className="section-title">Programación por frecuencia</h2>
                                    <p className="section-description">
                                        Las reglas activas calculan las fechas de mantenimiento y sus alertas al registrar un parque.
                                    </p>
                                </div>
                                <Button onClick={() => openRule()} className="h-9 rounded-[9px]">
                                    <Plus className="size-4" /> Registrar regla
                                </Button>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="data-table min-w-[820px]">
                                    <thead>
                                        <tr>
                                            <th>Tipo de área verde</th>
                                            <th>Mantenimiento</th>
                                            <th className="text-center">Frecuencia</th>
                                            <th className="text-center">Anticipación</th>
                                            <th className="text-center">Estado</th>
                                            <th className="text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {maintenanceRules.map((rule) => (
                                            <tr key={rule.id}>
                                                <td className="font-semibold">{rule.green_area_type}</td>
                                                <td>{rule.maintenance_type}</td>
                                                <td className="text-center">
                                                    <span className="pill bg-sky-50 text-sky-700 tabular-nums dark:bg-sky-950/50 dark:text-sky-300">
                                                        Cada {rule.frequency_days} días
                                                    </span>
                                                </td>
                                                <td className="text-muted-foreground text-center tabular-nums">{rule.advance_days} día(s) antes</td>
                                                <td className="text-center">
                                                    <StatusBadge active={rule.is_active} />
                                                </td>
                                                <td>
                                                    <div className="flex justify-end">
                                                        <RowActions
                                                            label="Acciones de la regla"
                                                            items={[
                                                                { label: 'Editar regla', icon: Edit, onSelect: () => openRule(rule) },
                                                                {
                                                                    label: rule.is_active ? 'Desactivar regla' : 'Activar regla',
                                                                    icon: Power,
                                                                    danger: rule.is_active,
                                                                    onSelect: () => toggleRule(rule),
                                                                },
                                                            ]}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}

                                        {maintenanceRules.length === 0 && (
                                            <tr>
                                                <td colSpan={6} className="py-12 text-center">
                                                    <p className="font-medium">Aún no hay reglas de programación</p>
                                                    <p className="text-muted-foreground mt-1 text-[12px]">
                                                        Registrar una regla permite calcular fechas y alertas automáticamente.
                                                    </p>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    {activeTab === 'institution' && (
                        <section className="surface min-w-0">
                            <div className="border-b p-4">
                                <h2 className="section-title">Datos de la organización</h2>
                                <p className="section-description">
                                    Esta información se utiliza en reportes y documentos generados por el sistema.
                                </p>
                            </div>

                            <form onSubmit={submitInstitution} className="space-y-5 p-4 md:p-5">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <Field label="Nombre de la organización" error={institutionForm.errors.organization_name}>
                                        <input
                                            required
                                            value={institutionForm.data.organization_name}
                                            onChange={(e) => institutionForm.setData('organization_name', e.target.value)}
                                            className="field-control"
                                        />
                                    </Field>

                                    <Field label="Unidad o área responsable" error={institutionForm.errors.unit_name}>
                                        <input
                                            required
                                            value={institutionForm.data.unit_name}
                                            onChange={(e) => institutionForm.setData('unit_name', e.target.value)}
                                            className="field-control"
                                        />
                                    </Field>

                                    <Field label="Dirección" error={institutionForm.errors.address}>
                                        <input
                                            value={institutionForm.data.address}
                                            onChange={(e) => institutionForm.setData('address', e.target.value)}
                                            className="field-control"
                                        />
                                    </Field>

                                    <Field label="Nombre del encargado" error={institutionForm.errors.manager_name}>
                                        <input
                                            value={institutionForm.data.manager_name}
                                            onChange={(e) => institutionForm.setData('manager_name', e.target.value)}
                                            className="field-control"
                                        />
                                    </Field>

                                    <Field label="Año de gestión" error={institutionForm.errors.management_year}>
                                        <input
                                            required
                                            maxLength={20}
                                            value={institutionForm.data.management_year}
                                            onChange={(e) => institutionForm.setData('management_year', e.target.value)}
                                            className="field-control"
                                        />
                                    </Field>

                                    <Field label="Logotipo institucional" error={institutionForm.errors.logo}>
                                        <div className="mt-1 flex items-center gap-3">
                                            {institution.logo_url ? (
                                                <img
                                                    src={institution.logo_url}
                                                    alt="Logotipo institucional"
                                                    className="h-14 w-14 rounded-[10px] border bg-white object-contain p-1"
                                                />
                                            ) : (
                                                <div className="bg-muted grid h-14 w-14 place-items-center rounded-[10px] border">
                                                    <Building2 className="text-muted-foreground size-6" />
                                                </div>
                                            )}

                                            <label className="hover:bg-muted inline-flex h-9 cursor-pointer items-center gap-2 rounded-[9px] border px-3 text-[12px] font-semibold transition">
                                                <ImagePlus className="size-4" /> Seleccionar imagen
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    className="sr-only"
                                                    onChange={(e) => institutionForm.setData('logo', e.target.files?.[0] ?? null)}
                                                />
                                            </label>
                                        </div>
                                    </Field>
                                </div>

                                <Field label="Texto del pie del documento" error={institutionForm.errors.document_footer}>
                                    <textarea
                                        rows={3}
                                        value={institutionForm.data.document_footer}
                                        onChange={(e) => institutionForm.setData('document_footer', e.target.value)}
                                        className="field-control h-auto py-2"
                                    />
                                </Field>

                                <div className="flex justify-end border-t pt-4">
                                    <Button type="submit" disabled={institutionForm.processing} className="h-9 rounded-[9px]">
                                        {institutionForm.processing ? 'Guardando…' : 'Guardar datos institucionales'}
                                    </Button>
                                </div>
                            </form>
                        </section>
                    )}
                </div>
            </div>

            {catalogModalOpen && (
                <Modal
                    title={
                        editingCatalog
                            ? 'Editar catálogo'
                            : catalogKind === 'area'
                              ? 'Registrar tipo de área verde'
                              : 'Registrar tipo de mantenimiento'
                    }
                    onClose={() => setCatalogModalOpen(false)}
                >
                    <form onSubmit={submitCatalog} className="space-y-4">
                        <Field label="Nombre" error={catalogForm.errors.name}>
                            <input
                                required
                                autoFocus
                                value={catalogForm.data.name}
                                onChange={(e) => catalogForm.setData('name', e.target.value)}
                                className="field-control"
                                placeholder={catalogKind === 'area' ? 'Ej. Plazoleta' : 'Ej. Corte de césped'}
                            />
                        </Field>

                        <Field label="Descripción" error={catalogForm.errors.description}>
                            <textarea
                                rows={3}
                                value={catalogForm.data.description}
                                onChange={(e) => catalogForm.setData('description', e.target.value)}
                                className="field-control h-auto py-2"
                                placeholder="Descripción breve para los usuarios del sistema."
                            />
                        </Field>

                        <Field label="Estado">
                            <select
                                value={catalogForm.data.is_active ? '1' : '0'}
                                onChange={(e) => catalogForm.setData('is_active', e.target.value === '1')}
                                className="field-control"
                            >
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </Field>

                        <ModalActions processing={catalogForm.processing} editing={!!editingCatalog} onCancel={() => setCatalogModalOpen(false)} />
                    </form>
                </Modal>
            )}

            {ruleModalOpen && (
                <Modal
                    title={editingRule ? 'Editar regla de programación' : 'Registrar regla de programación'}
                    onClose={() => setRuleModalOpen(false)}
                >
                    <form onSubmit={submitRule} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Tipo de área verde" error={ruleForm.errors.green_area_type}>
                                <select
                                    required
                                    value={ruleForm.data.green_area_type}
                                    onChange={(e) => ruleForm.setData('green_area_type', e.target.value)}
                                    className="field-control"
                                >
                                    {greenAreaTypes.map((item) => (
                                        <option
                                            key={item.id}
                                            value={item.name}
                                            disabled={!item.is_active && item.name !== editingRule?.green_area_type}
                                        >
                                            {item.name}
                                            {!item.is_active ? ' (inactivo)' : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Tipo de mantenimiento" error={ruleForm.errors.maintenance_type}>
                                <select
                                    required
                                    value={ruleForm.data.maintenance_type}
                                    onChange={(e) => ruleForm.setData('maintenance_type', e.target.value)}
                                    className="field-control"
                                >
                                    {maintenanceTypes.map((item) => (
                                        <option
                                            key={item.id}
                                            value={item.name}
                                            disabled={!item.is_active && item.name !== editingRule?.maintenance_type}
                                        >
                                            {item.name}
                                            {!item.is_active ? ' (inactivo)' : ''}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Frecuencia en días" error={ruleForm.errors.frequency_days}>
                                <input
                                    type="number"
                                    required
                                    min={1}
                                    value={ruleForm.data.frequency_days}
                                    onChange={(e) => ruleForm.setData('frequency_days', Number(e.target.value))}
                                    className="field-control"
                                />
                            </Field>

                            <Field label="Días de anticipación" error={ruleForm.errors.advance_days}>
                                <input
                                    type="number"
                                    required
                                    min={0}
                                    max={ruleForm.data.frequency_days}
                                    value={ruleForm.data.advance_days}
                                    onChange={(e) => ruleForm.setData('advance_days', Number(e.target.value))}
                                    className="field-control"
                                />
                            </Field>
                        </div>

                        <Field label="Estado de la regla">
                            <select
                                value={ruleForm.data.is_active ? '1' : '0'}
                                onChange={(e) => ruleForm.setData('is_active', e.target.value === '1')}
                                className="field-control"
                            >
                                <option value="1">Activa: generará programaciones y alertas</option>
                                <option value="0">Inactiva</option>
                            </select>
                        </Field>

                        <div className="rounded-[10px] border border-amber-200 bg-amber-50 p-3 text-[11.5px] text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            <strong>Validación:</strong> la frecuencia debe ser mayor que cero y la anticipación no puede superar la frecuencia.
                        </div>

                        <ModalActions processing={ruleForm.processing} editing={!!editingRule} onCancel={() => setRuleModalOpen(false)} />
                    </form>
                </Modal>
            )}
        </AppLayout>
    );
}

function CatalogSection({
    title,
    description,
    items,
    onCreate,
    onEdit,
    onToggle,
}: {
    title: string;
    description: string;
    items: CatalogItem[];
    onCreate: () => void;
    onEdit: (item: CatalogItem) => void;
    onToggle: (item: CatalogItem) => void;
}) {
    return (
        <section className="surface min-w-0 overflow-hidden">
            <div className="flex flex-col justify-between gap-3 border-b p-4 sm:flex-row sm:items-center">
                <div>
                    <h2 className="section-title">{title}</h2>
                    <p className="section-description max-w-3xl">{description}</p>
                </div>

                <Button onClick={onCreate} className="h-9 rounded-[9px]">
                    <Plus className="size-4" /> Registrar
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="data-table min-w-[680px]">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th className="text-center">Estado</th>
                            <th className="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item.id}>
                                <td className="text-foreground font-semibold">{item.name}</td>
                                <td className="text-muted-foreground max-w-xl">{item.description || 'Sin descripción'}</td>
                                <td className="text-center">
                                    <StatusBadge active={item.is_active} />
                                </td>
                                <td>
                                    <div className="flex justify-end">
                                        <RowActions
                                            label={`Acciones de ${item.name}`}
                                            items={[
                                                { label: 'Editar', icon: Edit, onSelect: () => onEdit(item) },
                                                {
                                                    label: item.is_active ? 'Desactivar' : 'Activar',
                                                    icon: Power,
                                                    danger: item.is_active,
                                                    onSelect: () => onToggle(item),
                                                },
                                            ]}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}

                        {items.length === 0 && (
                            <tr>
                                <td colSpan={4} className="py-12 text-center">
                                    <CheckCircle2 className="text-muted-foreground/40 mx-auto mb-2 size-8" />
                                    <p className="font-medium">Sin registros en este catálogo</p>
                                    <p className="text-muted-foreground mt-1 text-[12px]">Utilice “Registrar” para agregar el primero.</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="text-foreground block text-[12px] font-semibold">
            {label}
            <div className="mt-1.5">{children}</div>
            {error && <span className="mt-1 block text-[11px] font-normal text-rose-600">{error}</span>}
        </label>
    );
}

function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-xs sm:p-4">
            <div className="bg-background max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-[16px] border shadow-2xl">
                <div className="bg-background sticky top-0 flex items-center justify-between border-b px-4 py-4 sm:px-6">
                    <h2 className="flex min-w-0 items-center gap-2 text-[16px] font-semibold">
                        <SlidersHorizontal className="text-primary size-5" />
                        {title}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Cerrar"
                        className="text-muted-foreground hover:bg-muted rounded-[8px] p-2 transition"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="p-4 sm:p-6">{children}</div>
            </div>
        </div>
    );
}

function ModalActions({ processing, editing, onCancel }: { processing: boolean; editing: boolean; onCancel: () => void }) {
    return (
        <div className="flex flex-wrap justify-end gap-2 border-t pt-4">
            <Button type="button" variant="outline" onClick={onCancel} className="h-9 rounded-[9px]">
                Cancelar
            </Button>
            <Button type="submit" disabled={processing} className="h-9 rounded-[9px]">
                {processing ? 'Guardando…' : editing ? 'Guardar cambios' : 'Registrar'}
            </Button>
        </div>
    );
}
