<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Models\MaintenanceAudit;
use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\Worker;
use App\Services\Reports\MaintenanceReportFilters;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->filters($request);

        return Inertia::render('reports/index', [
            'filters' => $filters,
            'activeReport' => (string) $request->query('type', ''),
            'filterOptions' => [
                'parks' => Park::query()->orderBy('name')->get(['id', 'code', 'name']),
                'workers' => Worker::query()->orderBy('last_name')->orderBy('first_name')->get()
                    ->map(fn (Worker $worker) => [
                        'id' => $worker->id,
                        'full_name' => $worker->full_name,
                    ])->values(),
                'maintenanceTypes' => MaintenanceRecord::query()
                    ->whereNotNull('maintenance_type')
                    ->distinct()
                    ->orderBy('maintenance_type')
                    ->pluck('maintenance_type'),
            ],
            'parksReport' => $this->parksReport($filters),
            'maintenanceReport' => $this->maintenanceReport($filters),
            'parkHistories' => $this->parkHistories($filters),
            'auditReport' => $this->auditReport($filters),
            'workerReport' => $this->workerReport($filters),
            'alertsReport' => $this->alertsReport(),
            'waterReport' => $this->waterReport($filters),
            'institutionName' => InstitutionalSetting::query()->value('organization_name')
                ?? 'Mi organización',
        ]);
    }

    public function csv(Request $request)
    {
        $filters = $this->filters($request);
        $dataset = $this->dataset((string) $request->query('type', 'parks'), $filters);
        $filename = $this->filename($dataset['slug'], 'csv');

        return response()->streamDownload(function () use ($dataset) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $dataset['headers'], ';');

            foreach ($dataset['rows'] as $row) {
                fputcsv($stream, $row, ';');
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request)
    {
        $filters = $this->filters($request);
        $dataset = $this->dataset((string) $request->query('type', 'parks'), $filters);
        $institution = InstitutionalSetting::query()->first();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.export', [
            ...$dataset,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'printable' => false,
            'institution' => $institution,
            'logo' => $this->logoDataUri($institution),
        ])->render(), 'UTF-8');
        $dompdf->setPaper('a4', count($dataset['headers']) > 6 ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($dataset['slug'], 'pdf').'"',
        ]);
    }

    public function print(Request $request)
    {
        $filters = $this->filters($request);
        $dataset = $this->dataset((string) $request->query('type', 'parks'), $filters);
        $institution = InstitutionalSetting::query()->first();

        return view('reports.export', [
            ...$dataset,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'printable' => true,
            'institution' => $institution,
            'logo' => $this->logoDataUri($institution),
        ]);
    }

    /**
     * Logo para los documentos: el institucional si dompdf puede incrustarlo
     * (sin GD solo admite JPEG) y, si no, el escudo del distrito.
     */
    private function logoDataUri(?InstitutionalSetting $institution): ?string
    {
        $candidates = [];

        if ($institution?->logo_path) {
            $candidates[] = $institution->logo_path;
        }


        foreach ($candidates as $storagePath) {
            $contents = Storage::disk('public')->exists($storagePath) ? Storage::disk('public')->get($storagePath) : null;
            $mime = $contents !== null ? (@getimagesizefromstring($contents)['mime'] ?? null) : null;

            if ($mime === 'image/jpeg' || ($mime !== null && extension_loaded('gd') && in_array($mime, ['image/png', 'image/gif'], true))) {
                return 'data:'.$mime.';base64,'.base64_encode($contents);
            }
        }

        return null;
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'park_id' => ['nullable', 'integer', Rule::exists('parks', 'id')->where('owner_id', auth()->id())],
            'park_ids' => ['nullable', 'string', 'regex:/^\d+(,\d+)*$/'],
            'maintenance_type' => ['nullable', 'string', 'max:100'],
            'worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')->where('owner_id', auth()->id())],
            'status' => ['nullable', Rule::in(['pendiente', 'proximo', 'vencido', 'en_proceso', 'finalizado', 'cancelado'])],
            'date_basis' => ['nullable', Rule::in(['operational', 'scheduled', 'executed'])],
            'maintenance_origin' => ['nullable', Rule::in(['ordinary', 'extraordinary'])],
            'assignment' => ['nullable', Rule::in(['assigned', 'unassigned'])],
            'duration_min' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'duration_max' => [
                'nullable',
                'integer',
                'min:0',
                'max:100000',
                Rule::when($request->filled('duration_min'), ['gte:duration_min']),
            ],
            'retraso_min' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'has_document' => ['nullable', Rule::in(['0', '1'])],
            'has_photo' => ['nullable', Rule::in(['0', '1'])],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['parks', 'maintenances', 'history', 'audit', 'workers', 'alerts', 'water'])],
            'park_type' => ['nullable', 'string', 'max:100'],
            'park_zone' => ['nullable', 'string', 'max:100'],
            'park_conservation' => ['nullable', Rule::in(['bueno', 'regular', 'malo'])],
            'park_active' => ['nullable', Rule::in(['0', '1'])],
            'park_water' => ['nullable', Rule::in(['con', 'sin'])],
            'park_water_status' => ['nullable', 'string', 'max:100'],
            'park_supply' => ['nullable', 'string', 'max:100'],
            'park_area_min' => ['nullable', 'numeric', 'min:0'],
            'park_area_max' => ['nullable', 'numeric', 'min:0', Rule::when($request->filled('park_area_min'), ['gte:park_area_min'])],
            'park_photo' => ['nullable', Rule::in(['0', '1'])],
            'park_location' => ['nullable', Rule::in(['0', '1'])],
            'park_observations' => ['nullable', Rule::in(['0', '1'])],
            'park_registered_from' => ['nullable', 'date'],
            'park_registered_to' => ['nullable', 'date', 'after_or_equal:park_registered_from'],
            'worker_active' => ['nullable', Rule::in(['0', '1'])],
            'worker_position' => ['nullable', 'string', 'max:100'],
            'worker_crew' => ['nullable', 'string', 'max:100'],
            'worker_without_records' => ['nullable', Rule::in(['0', '1'])],
        ]);

        return [
            'month' => $validated['month'] ?? '',
            'date_from' => $validated['date_from'] ?? '',
            'date_to' => $validated['date_to'] ?? '',
            'park_id' => isset($validated['park_id']) ? (string) $validated['park_id'] : '',
            'park_ids' => $validated['park_ids'] ?? '',
            'maintenance_type' => $validated['maintenance_type'] ?? '',
            'worker_id' => isset($validated['worker_id']) ? (string) $validated['worker_id'] : '',
            'status' => $validated['status'] ?? '',
            'date_basis' => $validated['date_basis'] ?? 'operational',
            'maintenance_origin' => $validated['maintenance_origin'] ?? '',
            'assignment' => $validated['assignment'] ?? '',
            'duration_min' => isset($validated['duration_min']) ? (string) $validated['duration_min'] : '',
            'duration_max' => isset($validated['duration_max']) ? (string) $validated['duration_max'] : '',
            'retraso_min' => isset($validated['retraso_min']) ? (string) $validated['retraso_min'] : '',
            'has_document' => $validated['has_document'] ?? '',
            'has_photo' => $validated['has_photo'] ?? '',
            'search' => $validated['search'] ?? '',
            'park_type' => $validated['park_type'] ?? '',
            'park_zone' => $validated['park_zone'] ?? '',
            'park_conservation' => $validated['park_conservation'] ?? '',
            'park_active' => $validated['park_active'] ?? '',
            'park_water' => $validated['park_water'] ?? '',
            'park_water_status' => $validated['park_water_status'] ?? '',
            'park_supply' => $validated['park_supply'] ?? '',
            'park_area_min' => isset($validated['park_area_min']) ? (string) $validated['park_area_min'] : '',
            'park_area_max' => isset($validated['park_area_max']) ? (string) $validated['park_area_max'] : '',
            'park_photo' => $validated['park_photo'] ?? '',
            'park_location' => $validated['park_location'] ?? '',
            'park_observations' => $validated['park_observations'] ?? '',
            'park_registered_from' => $validated['park_registered_from'] ?? '',
            'park_registered_to' => $validated['park_registered_to'] ?? '',
            'worker_active' => $validated['worker_active'] ?? '',
            'worker_position' => $validated['worker_position'] ?? '',
            'worker_crew' => $validated['worker_crew'] ?? '',
            'worker_without_records' => $validated['worker_without_records'] ?? '',
        ];
    }

    /**
     * Filtros de parques compartidos por el reporte general y el de puntos de agua.
     */
    private function applyParkFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['park_ids'] ?? '', fn (Builder $query, string $ids) => $query
                ->whereIn('id', array_filter(array_map('intval', explode(',', $ids)))))
            ->when($filters['park_type'] ?? '', fn (Builder $query, string $tipo) => $query->where('green_area_type', $tipo))
            ->when($filters['park_zone'] ?? '', fn (Builder $query, string $zona) => $query->where('zone', $zona))
            ->when($filters['park_conservation'] ?? '', fn (Builder $query, string $estado) => $query->where('conservation_status', $estado))
            ->when(($filters['park_active'] ?? '') !== '', fn (Builder $query) => $query->where('is_active', $filters['park_active'] === '1'))
            ->when($filters['park_water'] ?? '', fn (Builder $query, string $agua) => $query->where('has_water_point', $agua === 'con'))
            ->when($filters['park_water_status'] ?? '', fn (Builder $query, string $estado) => $query->where('water_point_status', $estado))
            ->when($filters['park_supply'] ?? '', fn (Builder $query, string $tipo) => $query->where('water_supply_type', $tipo))
            ->when(($filters['park_area_min'] ?? '') !== '', fn (Builder $query) => $query->whereRaw('CAST(COALESCE(green_area, area, 0) AS REAL) >= ?', [(float) $filters['park_area_min']]))
            ->when(($filters['park_area_max'] ?? '') !== '', fn (Builder $query) => $query->whereRaw('CAST(COALESCE(green_area, area, 0) AS REAL) <= ?', [(float) $filters['park_area_max']]))
            ->when(($filters['park_photo'] ?? '') !== '', fn (Builder $query) => $filters['park_photo'] === '1'
                ? $query->whereNotNull('photo_url')->where('photo_url', '<>', '')
                : $query->where(fn (Builder $sin) => $sin->whereNull('photo_url')->orWhere('photo_url', '')))
            ->when(($filters['park_location'] ?? '') !== '', fn (Builder $query) => $filters['park_location'] === '1'
                ? $query->whereNotNull('latitude')->whereNotNull('longitude')
                : $query->where(fn (Builder $sin) => $sin->whereNull('latitude')->orWhereNull('longitude')))
            ->when(($filters['park_observations'] ?? '') !== '', fn (Builder $query) => $filters['park_observations'] === '1'
                ? $query->where(fn (Builder $con) => $con->whereNotNull('general_observations')->where('general_observations', '<>', ''))
                : $query->where(fn (Builder $sin) => $sin->whereNull('general_observations')->orWhere('general_observations', '')))
            ->when($filters['park_registered_from'] ?? '', fn (Builder $query, string $desde) => $query->whereDate('registration_date', '>=', $desde))
            ->when($filters['park_registered_to'] ?? '', fn (Builder $query, string $hasta) => $query->whereDate('registration_date', '<=', $hasta));
    }

    private function maintenanceQuery(array $filters): Builder
    {
        return $this->applyMaintenanceFilters(
            MaintenanceRecord::query()->with(['park', 'worker']),
            $filters,
        )->orderByDesc('scheduled_date')->orderByDesc('id');
    }

    private function applyMaintenanceFilters(Builder|Relation $query, array $filters): Builder|Relation
    {
        return MaintenanceReportFilters::apply($query, $filters);
    }

    private function parksReport(array $filters = []): Collection
    {
        return $this->applyParkFilters(Park::query(), $filters)->orderBy('name')->get()->map(fn (Park $park) => [
            'id' => $park->id,
            'code' => $park->code ?? sprintf('PRQ-%04d', $park->id),
            'name' => $park->name,
            'green_area_type' => $park->green_area_type ?? 'Parque',
            'location' => collect([$park->address, $park->zone])->filter()->implode(' - ') ?: 'Sin ubicación',
            'public_use_area' => $park->public_use_area !== null ? (float) $park->public_use_area : null,
            'green_area' => $park->green_area !== null ? (float) $park->green_area : ($park->area !== null ? (float) $park->area : null),
            'has_water_point' => (bool) $park->has_water_point,
            'water_point_status' => $park->water_point_status,
            'conservation_status' => $park->conservation_status ?? $park->status ?? 'regular',
            'is_active' => (bool) $park->is_active,
        ]);
    }

    private function maintenanceReport(array $filters): Collection
    {
        return $this->maintenanceQuery($filters)->get()->map(fn (MaintenanceRecord $record) => [
            'id' => $record->id,
            'maintenance_number' => $record->maintenance_number,
            'scheduled_date' => $record->scheduled_date?->format('d/m/Y'),
            'execution_date' => $record->execution_date?->format('d/m/Y'),
            'park_name' => $record->park?->name ?? 'Sin parque',
            'park_code' => $record->park?->code ?? '',
            'maintenance_type' => $record->maintenance_type,
            'worker_name' => $record->worker?->full_name ?? $record->responsible_worker ?? 'Por asignar',
            'duration_minutes' => $record->duration_minutes,
            'duration_label' => $this->durationLabel($record->duration_minutes),
            'status' => $this->computedStatus($record),
            'status_label' => $this->statusLabel($this->computedStatus($record)),
        ]);
    }

    private function parkHistories(array $filters): Collection
    {
        $hasActiveFilters = collect($filters)
            ->except('date_basis')
            ->contains(fn ($value) => $value !== '') || $filters['date_basis'] !== 'operational';

        return Park::query()
            ->when($filters['park_id'], fn (Builder $query, string $parkId) => $query->whereKey($parkId))
            ->when($hasActiveFilters && ! $filters['park_id'], fn (Builder $query) => $query
                ->whereHas('maintenanceRecords', fn (Builder $records) => $this->applyMaintenanceFilters($records, $filters)))
            ->with(['maintenanceRecords' => fn ($query) => $this
                ->applyMaintenanceFilters($query, $filters)
                ->with('worker')
                ->orderByDesc('scheduled_date')
                ->orderByDesc('id')])
            ->orderBy('name')
            ->get()
            ->map(fn (Park $park) => [
                'id' => $park->id,
                'code' => $park->code ?? sprintf('PRQ-%04d', $park->id),
                'name' => $park->name,
                'maintenance_count' => $park->maintenanceRecords->count(),
                'maintenance_types' => $park->maintenanceRecords->pluck('maintenance_type')->filter()->unique()->values(),
                'records' => $park->maintenanceRecords->map(fn (MaintenanceRecord $record) => [
                    'id' => $record->id,
                    'maintenance_number' => $record->maintenance_number,
                    'maintenance_type' => $record->maintenance_type,
                    'scheduled_date' => $record->scheduled_date?->format('d/m/Y'),
                    'execution_date' => $record->execution_date?->format('d/m/Y'),
                    'worker_name' => $record->worker?->full_name ?? $record->responsible_worker ?? 'Por asignar',
                    'schedule' => $this->scheduleLabel($record),
                    'observations' => $record->observations ?? $record->work_description ?? 'Sin observaciones',
                    'document_name' => $record->document_name,
                    'document_url' => $record->document_url,
                    'status' => $this->computedStatus($record),
                    'status_label' => $this->statusLabel($this->computedStatus($record)),
                ])->values(),
            ]);
    }

    private function auditReport(array $filters): Collection
    {
        return MaintenanceAudit::query()
            ->with(['maintenance.park', 'maintenance.worker', 'user'])
            ->where('event', 'overdue_justification')
            ->whereHas('maintenance', fn (Builder $query) => $this->applyMaintenanceFilters($query, $filters))
            ->latest()
            ->get()
            ->map(function (MaintenanceAudit $audit) {
                $maintenance = $audit->maintenance;

                return [
                    'id' => $audit->id,
                    'recorded_at' => $audit->created_at?->format('d/m/Y H:i'),
                    'maintenance_number' => $maintenance?->maintenance_number ?? '—',
                    'park_name' => $maintenance?->park?->name ?? 'Sin parque',
                    'park_code' => $maintenance?->park?->code ?? '',
                    'maintenance_type' => $maintenance?->maintenance_type ?? '—',
                    'scheduled_date' => $audit->scheduled_date?->format('d/m/Y'),
                    'overdue_days' => $audit->overdue_days,
                    'reason' => $audit->reason,
                    'document_name' => $audit->document_name,
                    'document_url' => $audit->document_path
                        ? route('maintenances.audit-document', $audit, false)
                        : null,
                    'recorded_by' => $audit->user?->name ?? 'Usuario no disponible',
                    'action' => data_get($audit->metadata, 'action', 'atender'),
                    'current_status' => $maintenance ? $this->computedStatus($maintenance) : 'cancelado',
                    'current_status_label' => $maintenance ? $this->statusLabel($this->computedStatus($maintenance)) : 'No disponible',
                ];
            });
    }

    private function workerReport(array $filters = []): Collection
    {
        return Worker::query()
            ->with(['maintenanceRecords.park'])
            ->when(($filters['worker_active'] ?? '') !== '', fn (Builder $query) => $query->where('is_active', $filters['worker_active'] === '1'))
            ->when($filters['worker_position'] ?? '', fn (Builder $query, string $cargo) => $query->where('position', $cargo))
            ->when($filters['worker_crew'] ?? '', fn (Builder $query, string $cuadrilla) => $query->where('crew', $cuadrilla))
            ->when(($filters['worker_without_records'] ?? '') === '1', fn (Builder $query) => $query->has('maintenanceRecords', '=', 0))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Worker $worker) {
                $records = $worker->maintenanceRecords;

                return [
                    'id' => $worker->id,
                    'code' => $worker->code,
                    'full_name' => $worker->full_name,
                    'position' => $worker->position,
                    'is_active' => $worker->is_active,
                    'maintenance_count' => $records->count(),
                    'parks' => $records->map(fn (MaintenanceRecord $record) => $record->park?->name)->filter()->unique()->values(),
                    'work_types' => $records->pluck('maintenance_type')->filter()->unique()->values(),
                    'hours' => round($records->sum('duration_minutes') / 60, 1),
                    'completed_count' => $records->whereIn('status', ['finalizado', 'completado', 'ejecutado'])->count(),
                    'cancelled_count' => $records->where('status', 'cancelado')->count(),
                ];
            });
    }

    private function alertsReport(): array
    {
        $records = MaintenanceRecord::query()->with('park')->get();
        $pending = $records->where('status', 'pendiente');
        $today = today()->toDateString();

        $upcoming = $pending->filter(fn (MaintenanceRecord $record) => $record->scheduled_date && $record->scheduled_date->toDateString() > $today);
        $dueToday = $pending->filter(fn (MaintenanceRecord $record) => $record->scheduled_date?->toDateString() === $today);
        $overdue = $pending->filter(fn (MaintenanceRecord $record) => $record->scheduled_date && $record->scheduled_date->toDateString() < $today);
        $inProgress = $records->whereIn('status', ['en_proceso', 'en_mantenimiento']);
        $attentionParkIds = $dueToday->concat($overdue)->concat($inProgress)->pluck('park_id')->unique();
        $upToDate = Park::query()->where('is_active', true)->whereNotIn('id', $attentionParkIds)->orderBy('name')->get();

        $mapRecords = fn (Collection $items) => $items->sortBy('scheduled_date')->map(fn (MaintenanceRecord $record) => [
            'id' => $record->id,
            'maintenance_number' => $record->maintenance_number,
            'park_name' => $record->park?->name ?? 'Sin parque',
            'maintenance_type' => $record->maintenance_type,
            'scheduled_date' => $record->scheduled_date?->format('d/m/Y'),
        ])->values();

        return [
            'counts' => [
                'up_to_date' => $upToDate->count(),
                'upcoming' => $upcoming->count(),
                'due_today' => $dueToday->count(),
                'overdue' => $overdue->count(),
                'in_progress' => $inProgress->count(),
            ],
            'up_to_date_parks' => $upToDate->map(fn (Park $park) => ['id' => $park->id, 'code' => $park->code, 'name' => $park->name])->values(),
            'upcoming' => $mapRecords($upcoming),
            'due_today' => $mapRecords($dueToday),
            'overdue' => $mapRecords($overdue),
            'in_progress' => $mapRecords($inProgress),
        ];
    }

    private function waterReport(array $filters = []): array
    {
        $parks = $this->applyParkFilters(Park::query(), $filters)->orderBy('name')->get();
        $withWater = $parks->where('has_water_point', true);

        $isOperational = fn (Park $park) => in_array(mb_strtolower((string) $park->water_point_status), ['operativo', 'bueno', 'funcional']);
        $isNonOperational = fn (Park $park) => in_array(mb_strtolower((string) $park->water_point_status), ['no operativo', 'inoperativo', 'malo']);

        return [
            'counts' => [
                'with_water' => $withWater->count(),
                'without_water' => $parks->where('has_water_point', false)->count(),
                'operational' => $withWater->filter($isOperational)->count(),
                'with_observations' => $withWater->filter(fn (Park $park) => filled($park->water_point_observations))->count(),
                'non_operational' => $withWater->filter($isNonOperational)->count(),
            ],
            'parks' => $parks->map(fn (Park $park) => [
                'id' => $park->id,
                'code' => $park->code ?? sprintf('PRQ-%04d', $park->id),
                'name' => $park->name,
                'has_water_point' => (bool) $park->has_water_point,
                'water_supply_type' => $park->water_supply_type,
                'water_point_status' => $park->water_point_status,
                'observations' => $park->water_point_observations,
            ])->values(),
        ];
    }

    private function dataset(string $type, array $filters): array
    {
        return match ($type) {
            'maintenances' => [
                'title' => 'Reporte de mantenimientos por periodo',
                'slug' => 'mantenimientos',
                'headers' => ['N.°', 'Programada', 'Realizada', 'Parque', 'Tipo', 'Trabajador', 'Duración', 'Estado'],
                'rows' => $this->maintenanceReport($filters)->map(fn (array $row) => [
                    $row['maintenance_number'], $row['scheduled_date'], $row['execution_date'], $row['park_name'],
                    $row['maintenance_type'], $row['worker_name'], $row['duration_label'], $row['status_label'],
                ])->all(),
            ],
            'history' => [
                'title' => 'Historial de mantenimientos por parque',
                'slug' => 'historial-parques',
                'headers' => ['Parque', 'N.°', 'Tipo', 'Programada', 'Realizada', 'Trabajador', 'Horario', 'Estado', 'Observaciones', 'Documento'],
                'rows' => $this->parkHistories($filters)->flatMap(fn (array $park) => collect($park['records'])->map(fn (array $record) => [
                    $park['name'], $record['maintenance_number'], $record['maintenance_type'], $record['scheduled_date'],
                    $record['execution_date'], $record['worker_name'], $record['schedule'], $record['status_label'],
                    $record['observations'], $record['document_name'] ?? 'Sin documento',
                ]))->all(),
            ],
            'audit' => [
                'title' => 'Motivos de retraso en mantenimientos vencidos',
                'slug' => 'auditoria-vencimientos',
                'headers' => ['Fecha de auditoría', 'N.°', 'Parque', 'Tipo', 'Programada', 'Días vencido', 'Motivo', 'Documento', 'Registrado por', 'Acción'],
                'rows' => $this->auditReport($filters)->map(fn (array $row) => [
                    $row['recorded_at'], $row['maintenance_number'], $row['park_name'], $row['maintenance_type'],
                    $row['scheduled_date'], $row['overdue_days'], $row['reason'], $row['document_name'] ?? 'Sin documento',
                    $row['recorded_by'], ucfirst($row['action']),
                ])->all(),
            ],
            'workers' => [
                'title' => 'Reporte por trabajador',
                'slug' => 'trabajadores',
                'headers' => ['Código', 'Trabajador', 'Cargo', 'Mantenimientos', 'Parques atendidos', 'Tipos de trabajo', 'Horas', 'Finalizados', 'Cancelados'],
                'rows' => $this->workerReport($filters)->map(fn (array $row) => [
                    $row['code'], $row['full_name'], $row['position'], $row['maintenance_count'], implode(', ', $row['parks']->all()),
                    implode(', ', $row['work_types']->all()), $row['hours'], $row['completed_count'], $row['cancelled_count'],
                ])->all(),
            ],
            'alerts' => $this->alertsDataset(),
            'water' => [
                'title' => 'Reporte de puntos de agua',
                'slug' => 'puntos-agua',
                'headers' => ['Código', 'Parque', 'Punto de agua', 'Abastecimiento', 'Estado', 'Observaciones'],
                'rows' => collect($this->waterReport($filters)['parks'])->map(fn (array $row) => [
                    $row['code'], $row['name'], $row['has_water_point'] ? 'Sí' : 'No', $row['water_supply_type'] ?? '-',
                    $row['water_point_status'] ?? '-', $row['observations'] ?? '-',
                ])->all(),
            ],
            default => [
                'title' => 'Reporte general de parques',
                'slug' => 'parques',
                'headers' => ['Código', 'Denominación', 'Tipo', 'Ubicación', 'Área pública', 'Área verde', 'Punto de agua', 'Conservación', 'Registro'],
                'rows' => $this->parksReport($filters)->map(fn (array $row) => [
                    $row['code'], $row['name'], $row['green_area_type'], $row['location'],
                    $row['public_use_area'] !== null ? $row['public_use_area'].' m²' : '-',
                    $row['green_area'] !== null ? $row['green_area'].' m²' : '-',
                    $row['has_water_point'] ? 'Sí - '.($row['water_point_status'] ?? 'Sin estado') : 'No',
                    ucfirst($row['conservation_status']), $row['is_active'] ? 'Activo' : 'Inactivo',
                ])->all(),
            ],
        };
    }

    private function alertsDataset(): array
    {
        $alerts = $this->alertsReport();
        $rows = collect();

        foreach ([
            'upcoming' => 'Próximo',
            'due_today' => 'Pendiente hoy',
            'overdue' => 'Vencido',
            'in_progress' => 'En proceso',
        ] as $key => $label) {
            foreach ($alerts[$key] as $record) {
                $rows->push([$label, $record['maintenance_number'], $record['park_name'], $record['maintenance_type'], $record['scheduled_date']]);
            }
        }

        foreach ($alerts['up_to_date_parks'] as $park) {
            $rows->push(['Al día', '-', $park['name'], '-', '-']);
        }

        return [
            'title' => 'Reporte de alertas',
            'slug' => 'alertas',
            'headers' => ['Categoría', 'N.°', 'Parque', 'Tipo de mantenimiento', 'Fecha programada'],
            'rows' => $rows->all(),
        ];
    }

    private function computedStatus(MaintenanceRecord $record): string
    {
        if ($record->status === 'pendiente' && $record->scheduled_date) {
            return match (true) {
                $record->scheduled_date->isBefore(today()) => 'vencido',
                $record->scheduled_date->isAfter(today()) => 'proximo',
                default => 'pendiente',
            };
        }

        return match (true) {
            in_array($record->status, ['en_proceso', 'en_mantenimiento']) => 'en_proceso',
            in_array($record->status, ['finalizado', 'completado', 'ejecutado']) => 'finalizado',
            default => $record->status,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'proximo' => 'Próximo',
            'pendiente' => 'Pendiente hoy',
            'vencido' => 'Vencido',
            'en_proceso' => 'En proceso',
            'finalizado' => 'Finalizado',
            'cancelado' => 'Cancelado',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function durationLabel(?int $minutes): string
    {
        if (! $minutes) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return trim(($hours ? "{$hours} h " : '').($remaining ? "{$remaining} min" : ''));
    }

    private function scheduleLabel(MaintenanceRecord $record): string
    {
        if (! $record->start_time && ! $record->end_time) {
            return '-';
        }

        return collect([$record->start_time ? substr($record->start_time, 0, 5) : null, $record->end_time ? substr($record->end_time, 0, 5) : null])
            ->filter()->implode(' - ');
    }

    private function filename(string $slug, string $extension): string
    {
        return "reporte-{$slug}-".now()->format('Y-m-d').".{$extension}";
    }
}
