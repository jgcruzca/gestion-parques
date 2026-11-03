<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Park;
use App\Models\Worker;
use App\Services\Maintenance\OperationalScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $today = Carbon::today()->toDateString();
        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();
        $tomorrowStart = Carbon::tomorrow()->startOfDay()->toDateTimeString();
        $filters = $this->filters($request);

        $query = MaintenanceRecord::query()
            ->select([
                'id',
                'maintenance_number',
                'park_id',
                'worker_id',
                'park_schedule_id',
                'maintenance_type',
                'is_extraordinary',
                'scheduled_date',
                'alert_date',
                'advance_days',
                'execution_date',
                'responsible_worker',
                'start_time',
                'status',
            ])
            ->with([
                'park:id,code,name,green_area_type,address,zone',
                'schedule:id,frequency_days',
                'worker:id,first_name,last_name',
            ]);

        $this->applyCommonFilters($query, $filters);
        $this->applyView($query, $filters['vista'], $filters['estado'], $todayStart, $tomorrowStart);

        $records = $query
            ->paginate(25)
            ->withQueryString()
            ->through(fn (MaintenanceRecord $record) => $this->summaryPayload($record, $today));

        $countQuery = MaintenanceRecord::query();
        $this->applyCommonFilters($countQuery, $filters);
        $countRow = $countQuery
            ->selectRaw('COUNT(*) as todos')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('finalizado', 'completado', 'ejecutado', 'en_proceso', 'en_mantenimiento', 'cancelado') AND scheduled_date >= ? AND alert_date IS NOT NULL AND alert_date < ? THEN 1 ELSE 0 END) as proximos", [$tomorrowStart, $tomorrowStart])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('finalizado', 'completado', 'ejecutado', 'en_proceso', 'en_mantenimiento', 'cancelado') AND scheduled_date >= ? AND scheduled_date < ? THEN 1 ELSE 0 END) as pendientes", [$todayStart, $tomorrowStart])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('finalizado', 'completado', 'ejecutado', 'en_proceso', 'en_mantenimiento', 'cancelado') AND scheduled_date < ? THEN 1 ELSE 0 END) as vencidos", [$todayStart])
            ->selectRaw("SUM(CASE WHEN status IN ('en_proceso', 'en_mantenimiento') THEN 1 ELSE 0 END) as en_proceso")
            ->selectRaw("SUM(CASE WHEN status IN ('finalizado', 'completado', 'ejecutado') THEN 1 ELSE 0 END) as finalizados")
            ->selectRaw("SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados")
            ->first();

        $counts = collect($countRow?->getAttributes() ?? [])
            ->map(fn ($value) => (int) $value)
            ->all();

        $parks = Park::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'green_area_type', 'address', 'zone']);

        $maintenanceTypes = MaintenanceType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        $workers = Worker::query()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'full_name' => $worker->full_name,
                'position' => $worker->position,
                'crew' => $worker->crew,
            ])
            ->values();

        return Inertia::render('maintenances/index', [
            'records' => $records,
            'counts' => $counts,
            'filters' => $filters,
            'parks' => $parks,
            'tiposMantenimiento' => $maintenanceTypes,
            'workers' => $workers,
        ]);
    }

    public function show(MaintenanceRecord $maintenance): JsonResponse
    {
        $maintenance->load([
            'park:id,code,name,green_area_type,address,zone',
            'schedule:id,frequency_days',
            'worker:id,first_name,last_name',
        ]);

        return response()->json([
            'record' => array_merge(
                $this->summaryPayload($maintenance, Carbon::today()->toDateString()),
                [
                    'reason' => $maintenance->reason,
                    'execution_date_formatted' => $maintenance->execution_date?->format('d/m/Y'),
                    'end_time' => $maintenance->end_time,
                    'duration_minutes' => $maintenance->duration_minutes,
                    'initial_observation' => $maintenance->initial_observation,
                    'work_description' => $maintenance->work_description,
                    'result' => $maintenance->result,
                    'photo_before_url' => $this->storageUrl($maintenance->photo_before_url),
                    'photo_after_url' => $this->storageUrl($maintenance->photo_after_url),
                    'observations' => $maintenance->observations,
                    'document_name' => $maintenance->document_name,
                    'document_url' => $maintenance->document_url,
                ],
            ),
        ]);
    }

    private function filters(Request $request): array
    {
        $aliases = [
            'proximo' => 'proximos',
            'pendiente' => 'pendientes',
            'vencido' => 'vencidos',
            'finalizado' => 'finalizados',
            'cancelado' => 'cancelados',
        ];

        $requestedStatus = (string) $request->string('estado', 'todos');
        $status = $aliases[$requestedStatus] ?? $requestedStatus;
        $view = (string) $request->string('vista', 'operativa');

        if (in_array($status, ['finalizados', 'cancelados'], true) && ! $request->has('vista')) {
            $view = 'historial';
        }

        $view = $view === 'historial' ? 'historial' : 'operativa';
        $allowedStatuses = $view === 'historial'
            ? ['todos', 'finalizados', 'cancelados']
            : ['todos', 'proximos', 'pendientes', 'vencidos', 'en_proceso'];

        return [
            'vista' => $view,
            'estado' => in_array($status, $allowedStatuses, true) ? $status : 'todos',
            'search' => trim(mb_substr((string) $request->string('search'), 0, 100)),
            'park_id' => $request->integer('park_id') > 0 ? (string) $request->integer('park_id') : '',
            'maintenance_type' => trim(mb_substr((string) $request->string('maintenance_type'), 0, 100)),
        ];
    }

    private function applyCommonFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['park_id'] !== '', fn (Builder $builder) => $builder->where('park_id', (int) $filters['park_id']))
            ->when($filters['maintenance_type'] !== '', fn (Builder $builder) => $builder->where('maintenance_type', $filters['maintenance_type']))
            ->when($filters['search'] !== '', function (Builder $builder) use ($filters) {
                $like = '%'.$filters['search'].'%';

                $builder->where(function (Builder $nested) use ($like) {
                    $nested->where('maintenance_number', 'like', $like)
                        ->orWhere('maintenance_type', 'like', $like)
                        ->orWhere('responsible_worker', 'like', $like)
                        ->orWhereHas('park', function (Builder $park) use ($like) {
                            $park->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like)
                                ->orWhere('zone', 'like', $like)
                                ->orWhere('address', 'like', $like);
                        })
                        ->orWhereHas('worker', function (Builder $worker) use ($like) {
                            $worker->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('crew', 'like', $like);
                        });
                });
            });
    }

    private function applyView(Builder $query, string $view, string $status, string $todayStart, string $tomorrowStart): void
    {
        if ($view === 'historial') {
            if ($status === 'finalizados') {
                $query->whereIn('status', OperationalScope::TERMINAL_STATUSES);
            } elseif ($status === 'cancelados') {
                $query->where('status', 'cancelado');
            } else {
                $query->whereIn('status', [...OperationalScope::TERMINAL_STATUSES, 'cancelado']);
            }

            $query->orderByRaw('COALESCE(execution_date, scheduled_date) DESC')->orderByDesc('id');

            return;
        }

        if ($status === 'en_proceso') {
            OperationalScope::inProgress($query);
        } elseif ($status === 'vencidos') {
            OperationalScope::overdue($query, $todayStart);
        } elseif ($status === 'pendientes') {
            OperationalScope::dueToday($query, $todayStart, $tomorrowStart);
        } elseif ($status === 'proximos') {
            OperationalScope::upcoming($query, $tomorrowStart);
        } else {
            OperationalScope::operational($query, $tomorrowStart);
        }

        $query
            ->orderByRaw(
                "CASE WHEN status IN ('en_proceso', 'en_mantenimiento') THEN 0 WHEN scheduled_date < ? THEN 1 WHEN scheduled_date < ? THEN 2 ELSE 3 END",
                [$todayStart, $tomorrowStart],
            )
            ->orderBy('scheduled_date')
            ->orderBy('id');
    }

    private function summaryPayload(MaintenanceRecord $record, string $today): array
    {
        $computedStatus = $record->operationalStatus(Carbon::parse($today));

        return [
            'id' => $record->id,
            'maintenance_number' => $record->maintenance_number ?? sprintf('PM-%06d-%s', $record->id, date('Y')),
            'park_id' => $record->park_id,
            'park_name' => $record->park?->name ?? 'Parque no especificado',
            'park_code' => $record->park?->code ?? ('PRQ-'.str_pad($record->park_id, 4, '0', STR_PAD_LEFT)),
            'green_area_type' => $record->park?->green_area_type ?? 'Parque',
            'park_zone' => $record->park?->zone ?? 'Sin zona',
            'park_address' => $record->park?->address ?? '',
            'maintenance_type' => $record->maintenance_type,
            'is_extraordinary' => (bool) $record->is_extraordinary,
            'frequency_days' => $record->schedule?->frequency_days,
            'scheduled_date' => $record->scheduled_date?->toDateString(),
            'scheduled_date_formatted' => $record->scheduled_date?->format('d/m/Y') ?? 'Sin fecha',
            'alert_date' => $record->alert_date?->toDateString(),
            'alert_date_formatted' => $record->alert_date?->format('d/m/Y'),
            'advance_days' => $record->advance_days ?? 1,
            'is_alert_active' => $computedStatus === 'proximo',
            'execution_date' => $record->execution_date?->toDateString(),
            'worker_id' => $record->worker_id,
            'responsible_worker' => $record->worker?->full_name ?? $record->responsible_worker ?? 'Por asignar',
            'start_time' => $record->start_time,
            'status' => $record->status,
            'computed_status' => $computedStatus,
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }
}
