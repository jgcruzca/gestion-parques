<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRecord;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('workers/index', [
            'workers' => $this->workersForIndex(),
            'nextGeneratedCode' => $this->nextWorkerCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:20', Rule::unique('workers', 'document_number')->where('owner_id', auth()->id())],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'crew' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);

        Worker::create($validated);

        return redirect()
            ->route('workers.index')
            ->with('success', 'Trabajador registrado exitosamente.');
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        $validated = $request->validate([
            'document_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('workers', 'document_number')->where('owner_id', auth()->id())->ignore($worker),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'crew' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);

        $worker->update($validated);

        return redirect()
            ->route('workers.index')
            ->with('success', "Datos del trabajador {$worker->full_name} actualizados.");
    }

    public function toggleStatus(Worker $worker): RedirectResponse
    {
        $worker->update(['is_active' => !$worker->is_active]);
        $status = $worker->is_active ? 'activado' : 'desactivado';

        return redirect()
            ->route('workers.index')
            ->with('success', "Trabajador {$worker->full_name} {$status}.");
    }

    public function mantenimientos(Worker $worker): Response
    {
        $records = MaintenanceRecord::query()
            ->where(function ($query) use ($worker) {
                $query->where('worker_id', $worker->id)
                    ->orWhere('responsible_worker', $worker->full_name);
            })
            ->with('park')
            ->latest('execution_date')
            ->latest('scheduled_date')
            ->get()
            ->map(fn (MaintenanceRecord $record) => [
                'id' => $record->id,
                'maintenance_number' => $record->maintenance_number ?? "PM-{$record->id}",
                'park_name' => $record->park?->name ?? 'Sin parque',
                'park_code' => $record->park?->code ?? '',
                'maintenance_type' => $record->maintenance_type,
                'scheduled_date' => $record->scheduled_date?->format('d/m/Y'),
                'execution_date' => $record->execution_date?->format('d/m/Y'),
                'status' => $record->status,
                'duration_minutes' => $record->duration_minutes,
                'result' => $record->result,
            ]);

        return Inertia::render('workers/index', [
            'workers' => $this->workersForIndex(),
            'nextGeneratedCode' => $this->nextWorkerCode(),
            'workerMantenimientos' => $records,
            'workerSeleccionado' => [
                'id' => $worker->id,
                'code' => $worker->code,
                'full_name' => $worker->full_name,
                'position' => $worker->position,
                'crew' => $worker->crew,
            ],
        ]);
    }

    private function workersForIndex(): Collection
    {
        return Worker::query()
            ->withCount([
                'maintenanceRecords',
                'maintenanceRecords as maintenances_completed_count' => fn ($query) => $query
                    ->whereIn('status', ['finalizado', 'completado', 'ejecutado']),
            ])
            ->latest('id')
            ->get()
            ->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'code' => $worker->code,
                'document_number' => $worker->document_number,
                'first_name' => $worker->first_name,
                'last_name' => $worker->last_name,
                'full_name' => $worker->full_name,
                'position' => $worker->position,
                'phone' => $worker->phone,
                'crew' => $worker->crew,
                'observations' => $worker->observations,
                'is_active' => $worker->is_active,
                'maintenance_records_count' => $worker->maintenance_records_count,
                'maintenances_completed_count' => $worker->maintenances_completed_count,
                'created_at' => $worker->created_at?->format('d/m/Y'),
            ]);
    }

    private function nextWorkerCode(): string
    {
        $maxNumber = Worker::query()
            ->pluck('code')
            ->reduce(function (int $max, ?string $code) {
                return preg_match('/^TRB-(\d+)$/', (string) $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return sprintf('TRB-%04d', $maxNumber + 1);
    }
}
