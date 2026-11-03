<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalSetting;
use App\Models\MaintenanceAudit;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRule;
use App\Models\ParkSchedule;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceController extends Controller
{
    /**
     * Bandeja principal de mantenimientos
     */
    public function index(Request $request): Response
    {
        return app(MaintenanceIndexController::class)($request);
    }

    /**
     * Registrar mantenimiento extraordinario
     */
    public function storeExtraordinary(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'park_id' => ['required', Rule::exists('parks', 'id')->where('owner_id', auth()->id())],
            'maintenance_type' => [
                'required',
                'string',
                'max:100',
                Rule::exists('maintenance_types', 'name')->where('is_active', true),
            ],
            'scheduled_date' => ['required', 'date'],
            'advance_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'reason' => ['required', 'string', 'max:500'],
            'worker_id' => ['nullable', Rule::exists('workers', 'id')->where('is_active', true)],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        $advanceDays = (int) ($validated['advance_days'] ?? 1);
        $scheduledDate = Carbon::parse($validated['scheduled_date']);
        $alertDate = $scheduledDate->copy()->subDays($advanceDays)->toDateString();

        $maxId = (int) MaintenanceRecord::max('id') + 1;
        $maintenanceNumber = sprintf('PM-%06d-%s', $maxId, $scheduledDate->format('Y'));

        $worker = isset($validated['worker_id']) ? Worker::find($validated['worker_id']) : null;

        MaintenanceRecord::create([
            'maintenance_number' => $maintenanceNumber,
            'park_id' => $validated['park_id'],
            'maintenance_type' => $validated['maintenance_type'],
            'is_extraordinary' => true,
            'reason' => $validated['reason'],
            'scheduled_date' => $scheduledDate->toDateString(),
            'alert_date' => $alertDate,
            'advance_days' => $advanceDays,
            'worker_id' => $worker?->id,
            'responsible_worker' => $worker?->full_name ?? 'Por asignar',
            'status' => 'pendiente',
            'observations' => $validated['observations'] ?? null,
        ]);

        return back()->with('success', "Mantenimiento adicional {$maintenanceNumber} programado exitosamente.");
    }

    /**
     * Iniciar mantenimiento
     */
    public function iniciar(Request $request, MaintenanceRecord $maintenance): RedirectResponse
    {
        $isOverdue = $maintenance->operationalStatus(Carbon::today()) === 'vencido';
        $validated = $request->validate([
            'execution_date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'worker_id' => ['required', Rule::exists('workers', 'id')->where('is_active', true)],
            'overdue_reason' => ['nullable', 'string', 'max:1000'],
            'overdue_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:10240'],
            'initial_observation' => ['nullable', 'string', 'max:1000'],
            'photo_before' => ['nullable', 'image', 'max:10240'],
        ], $this->overdueDocumentMessages());

        $this->validateOverdueJustification($isOverdue, $validated['overdue_reason'] ?? null, $request->hasFile('overdue_document'));
        $document = $isOverdue ? $this->storeOverdueDocument($request) : ['path' => null, 'name' => null];

        $photoBeforeUrl = $maintenance->photo_before_url;
        if ($request->hasFile('photo_before')) {
            $path = $request->file('photo_before')->store('maintenances/before', 'public');
            $photoBeforeUrl = $path;
        }

        $worker = Worker::findOrFail($validated['worker_id']);

        DB::transaction(function () use ($request, $maintenance, $validated, $worker, $photoBeforeUrl, $isOverdue, $document): void {
            if ($isOverdue) {
                $this->recordOverdueAudit(
                    $request,
                    $maintenance,
                    $validated['overdue_reason'] ?? null,
                    'iniciar',
                    $document['path'],
                    $document['name'],
                );
            }

            $maintenance->update([
                'execution_date' => $validated['execution_date'],
                'start_time' => $validated['start_time'],
                'worker_id' => $worker->id,
                'responsible_worker' => $worker->full_name,
                'initial_observation' => $validated['initial_observation'] ?? null,
                'photo_before_url' => $photoBeforeUrl,
                'status' => 'en_proceso',
            ]);
        });

        return back()->with('success', "El mantenimiento {$maintenance->maintenance_number} ha iniciado y pasó a estado 'En mantenimiento'.");
    }

    /**
     * Finalizar mantenimiento
     */
    public function finalizar(Request $request, MaintenanceRecord $maintenance): RedirectResponse
    {
        $validated = $request->validate([
            'execution_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['required', 'string'],
            'work_description' => ['required', 'string', 'max:2000'],
            'result' => ['required', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'worker_id' => ['required', Rule::exists('workers', 'id')->where('is_active', true)],
            'photo_after' => ['nullable', 'image', 'max:10240'],
        ]);

        $executionDate = $validated['execution_date'] ?? $maintenance->execution_date ?? now()->toDateString();
        $startTime = $validated['start_time'] ?? $maintenance->start_time ?? '08:00';
        $endTime = $validated['end_time'];

        // Cálculo de duración en minutos
        $durationMinutes = null;
        try {
            $startDt = Carbon::parse("{$executionDate} {$startTime}");
            $endDt = Carbon::parse("{$executionDate} {$endTime}");
            if ($endDt->greaterThan($startDt)) {
                $durationMinutes = $startDt->diffInMinutes($endDt);
            }
        } catch (\Throwable $e) {
            $durationMinutes = null;
        }

        $photoAfterUrl = $maintenance->photo_after_url;
        if ($request->hasFile('photo_after')) {
            $path = $request->file('photo_after')->store('maintenances/after', 'public');
            $photoAfterUrl = $path;
        }

        $worker = Worker::findOrFail($validated['worker_id']);

        $maintenance->update([
            'execution_date' => $executionDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'work_description' => $validated['work_description'],
            'result' => $validated['result'],
            'observations' => $validated['observations'] ?? $maintenance->observations,
            'worker_id' => $worker->id,
            'responsible_worker' => $worker->full_name,
            'photo_after_url' => $photoAfterUrl,
            'status' => 'finalizado',
            'document_name' => "Parte de mantenimiento {$maintenance->maintenance_number}",
        ]);

        // Próxima fecha automática para mantenimientos recurrentes
        if (! $maintenance->is_extraordinary && $maintenance->park_schedule_id) {
            $schedule = ParkSchedule::find($maintenance->park_schedule_id);
            $activeRule = $schedule ? $this->activeRuleFor($schedule) : null;

            if ($schedule && $schedule->is_active && $activeRule) {
                $finishedBaseDate = Carbon::parse($executionDate);
                $nextDate = $finishedBaseDate->copy()->addDays($activeRule->frequency_days);

                // Actualizar programación
                $schedule->update([
                    'frequency_days' => $activeRule->frequency_days,
                    'last_executed_at' => $finishedBaseDate->toDateString(),
                    'next_scheduled_date' => $nextDate->toDateString(),
                ]);

                // Generar siguiente mantenimiento en la cadena
                $maxId = (int) MaintenanceRecord::max('id') + 1;
                $nextMaintenanceNumber = sprintf('PM-%06d-%s', $maxId, $nextDate->format('Y'));
                $advanceDays = $activeRule->advance_days;
                $alertDate = $nextDate->copy()->subDays($advanceDays)->toDateString();

                MaintenanceRecord::create([
                    'maintenance_number' => $nextMaintenanceNumber,
                    'park_id' => $schedule->park_id,
                    'park_schedule_id' => $schedule->id,
                    'maintenance_type' => $schedule->maintenance_type,
                    'is_extraordinary' => false,
                    'scheduled_date' => $nextDate->toDateString(),
                    'alert_date' => $alertDate,
                    'advance_days' => $advanceDays,
                    'status' => 'pendiente',
                ]);
            }
        }

        return back()->with('success', "Mantenimiento {$maintenance->maintenance_number} finalizado exitosamente. Parte de mantenimiento generado.");
    }

    /**
     * Atender mantenimiento completo en un solo paso
     */
    public function atender(Request $request, MaintenanceRecord $maintenance): RedirectResponse
    {
        $isOverdue = $maintenance->operationalStatus(Carbon::today()) === 'vencido';
        $validated = $request->validate([
            'worker_id' => ['required', Rule::exists('workers', 'id')->where('is_active', true)],
            'execution_date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'work_description' => ['required', 'string', 'max:2000'],
            'result' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:finalizado,en_proceso'],
            'overdue_reason' => ['nullable', 'string', 'max:1000'],
            'overdue_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:10240'],
            'photo_before' => ['nullable', 'image', 'max:10240'],
            'photo_after' => ['nullable', 'image', 'max:10240'],
        ], $this->overdueDocumentMessages());

        $this->validateOverdueJustification($isOverdue, $validated['overdue_reason'] ?? null, $request->hasFile('overdue_document'));
        $document = $isOverdue ? $this->storeOverdueDocument($request) : ['path' => null, 'name' => null];

        $executionDate = $validated['execution_date'];
        $startTime = $validated['start_time'];
        $endTime = $validated['end_time'];

        $durationMinutes = null;
        try {
            $startDt = Carbon::parse("{$executionDate} {$startTime}");
            $endDt = Carbon::parse("{$executionDate} {$endTime}");
            if ($endDt->greaterThan($startDt)) {
                $durationMinutes = $startDt->diffInMinutes($endDt);
            }
        } catch (\Throwable $e) {
            $durationMinutes = null;
        }

        $photoBeforeUrl = $maintenance->photo_before_url;
        if ($request->hasFile('photo_before')) {
            $photoBeforeUrl = $request->file('photo_before')->store('maintenances/before', 'public');
        }

        $photoAfterUrl = $maintenance->photo_after_url;
        if ($request->hasFile('photo_after')) {
            $photoAfterUrl = $request->file('photo_after')->store('maintenances/after', 'public');
        }

        $status = $validated['status'];

        $worker = Worker::findOrFail($validated['worker_id']);

        DB::transaction(function () use ($request, $maintenance, $validated, $worker, $executionDate, $startTime, $endTime, $durationMinutes, $photoBeforeUrl, $photoAfterUrl, $status, $isOverdue, $document): void {
            if ($isOverdue) {
                $this->recordOverdueAudit(
                    $request,
                    $maintenance,
                    $validated['overdue_reason'] ?? null,
                    'atender',
                    $document['path'],
                    $document['name'],
                );
            }

            $maintenance->update([
                'worker_id' => $worker->id,
                'responsible_worker' => $worker->full_name,
                'execution_date' => $executionDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'work_description' => $validated['work_description'],
                'result' => $validated['result'] ?? 'Óptimo',
                'observations' => $validated['observations'] ?? null,
                'photo_before_url' => $photoBeforeUrl,
                'photo_after_url' => $photoAfterUrl,
                'status' => $status,
                'document_name' => "Parte de mantenimiento {$maintenance->maintenance_number}",
            ]);
        });

        // Si se marca como finalizado y es recurrente, calcular próxima fecha automática
        if ($status === 'finalizado' && ! $maintenance->is_extraordinary && $maintenance->park_schedule_id) {
            $schedule = ParkSchedule::find($maintenance->park_schedule_id);
            $activeRule = $schedule ? $this->activeRuleFor($schedule) : null;

            if ($schedule && $schedule->is_active && $activeRule) {
                $finishedBaseDate = Carbon::parse($executionDate);
                $nextDate = $finishedBaseDate->copy()->addDays($activeRule->frequency_days);

                $schedule->update([
                    'frequency_days' => $activeRule->frequency_days,
                    'last_executed_at' => $finishedBaseDate->toDateString(),
                    'next_scheduled_date' => $nextDate->toDateString(),
                ]);

                $maxId = (int) MaintenanceRecord::max('id') + 1;
                $nextMaintenanceNumber = sprintf('PM-%06d-%s', $maxId, $nextDate->format('Y'));
                $advanceDays = $activeRule->advance_days;
                $alertDate = $nextDate->copy()->subDays($advanceDays)->toDateString();

                MaintenanceRecord::create([
                    'maintenance_number' => $nextMaintenanceNumber,
                    'park_id' => $schedule->park_id,
                    'park_schedule_id' => $schedule->id,
                    'maintenance_type' => $schedule->maintenance_type,
                    'is_extraordinary' => false,
                    'scheduled_date' => $nextDate->toDateString(),
                    'alert_date' => $alertDate,
                    'advance_days' => $advanceDays,
                    'status' => 'pendiente',
                ]);
            }
        }

        return back()->with('success', "Mantenimiento {$maintenance->maintenance_number} atendido exitosamente.");
    }

    /**
     * Cancelar mantenimiento
     */
    public function cancelar(Request $request, MaintenanceRecord $maintenance): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $maintenance->update([
            'status' => 'cancelado',
            'observations' => ($maintenance->observations ? $maintenance->observations.' | ' : '').'Cancelado: '.$validated['reason'],
        ]);

        return back()->with('success', "El mantenimiento {$maintenance->maintenance_number} ha sido cancelado.");
    }

    /**
     * Vista imprimible / PDF del Parte de Mantenimiento
     */
    public function imprimirParte(MaintenanceRecord $maintenance)
    {
        $maintenance->load(['park', 'schedule']);

        return view('maintenances.documento', [
            'maintenance' => $maintenance,
            'park' => $maintenance->park,
            'institution' => InstitutionalSetting::query()->first(),
        ]);
    }

    public function auditDocument(MaintenanceAudit $audit): StreamedResponse
    {
        abort_unless($audit->document_path && Storage::disk('public')->exists($audit->document_path), 404);

        return Storage::disk('public')->download(
            $audit->document_path,
            $audit->document_name ?: basename($audit->document_path),
        );
    }

    private function activeRuleFor(ParkSchedule $schedule): ?MaintenanceRule
    {
        $greenAreaType = $schedule->park()->value('green_area_type');

        return MaintenanceRule::query()
            ->where('green_area_type', $greenAreaType)
            ->where('maintenance_type', $schedule->maintenance_type)
            ->where('is_active', true)
            ->first();
    }

    private function validateOverdueJustification(bool $isOverdue, ?string $reason, bool $hasDocument): void
    {
        if (! $isOverdue || $hasDocument) {
            return;
        }

        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'overdue_reason' => 'Escribe el motivo del retraso o adjunta un documento de respaldo.',
            ]);
        }

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'overdue_reason' => 'El motivo debe tener al menos 10 caracteres o adjunta un documento de respaldo.',
            ]);
        }
    }

    /**
     * @return array{path: ?string, name: ?string}
     */
    private function storeOverdueDocument(Request $request): array
    {
        if (! $request->hasFile('overdue_document')) {
            return ['path' => null, 'name' => null];
        }

        $file = $request->file('overdue_document');

        return [
            'path' => $file->store('maintenance-audits', 'public'),
            'name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function overdueDocumentMessages(): array
    {
        return [
            'overdue_document.mimes' => 'El documento debe ser PDF, imagen o Word.',
            'overdue_document.max' => 'El documento no debe superar los 10 MB.',
        ];
    }

    private function recordOverdueAudit(
        Request $request,
        MaintenanceRecord $maintenance,
        ?string $reason,
        string $action,
        ?string $documentPath = null,
        ?string $documentName = null,
    ): void {
        $today = Carbon::today();
        $overdueDays = $maintenance->scheduled_date
            ? (int) $maintenance->scheduled_date->diffInDays($today)
            : 0;

        $reason = trim((string) $reason);

        if ($reason === '') {
            $reason = $documentName
                ? 'Se adjuntó un documento de respaldo.'
                : 'Sin motivo registrado.';
        }

        $maintenance->audits()->create([
            'user_id' => $request->user()?->id,
            'event' => 'overdue_justification',
            'reason' => $reason,
            'document_path' => $documentPath,
            'document_name' => $documentName,
            'previous_status' => $maintenance->status,
            'scheduled_date' => $maintenance->scheduled_date?->toDateString(),
            'overdue_days' => $overdueDays,
            'metadata' => [
                'action' => $action,
                'computed_status' => 'vencido',
                'has_document' => $documentPath !== null,
            ],
        ]);
    }
}
