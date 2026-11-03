<?php

namespace App\Http\Controllers;

use App\Models\GreenAreaType;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRule;
use App\Models\Park;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ParkController extends Controller
{
    /**
     * Listado de parques
     */
    public function index(): Response
    {
        $parks = Park::query()
            ->with([
                'schedules' => function ($query) {
                    $query->where('is_active', true)->orderBy('next_scheduled_date');
                },
                'maintenanceRecords' => function ($query) {
                    $query->orderByDesc('execution_date')->orderByDesc('scheduled_date');
                },
            ])
            ->latest('id')
            ->get()
            ->map(function ($park) {
                // Próximo mantenimiento programado
                $nextSchedule = $park->schedules->where('next_scheduled_date', '>=', now()->toDateString())->first()
                    ?? $park->schedules->first();

                $nextMaintenance = null;
                if ($nextSchedule) {
                    $formattedDate = $nextSchedule->next_scheduled_date
                        ? Carbon::parse($nextSchedule->next_scheduled_date)->format('d/m/Y')
                        : 'Por definir';

                    $nextMaintenance = [
                        'type' => $nextSchedule->maintenance_type,
                        'date' => $formattedDate,
                        'raw_date' => $nextSchedule->next_scheduled_date,
                    ];
                }

                return [
                    'id' => $park->id,
                    'code' => $park->code ?? ('PRQ-'.str_pad($park->id, 4, '0', STR_PAD_LEFT)),
                    'name' => $park->name,
                    'green_area_type' => $park->green_area_type ?? 'Parque',
                    'registration_date' => $park->registration_date ? $park->registration_date->format('Y-m-d') : null,
                    'address' => $park->address,
                    'zone' => $park->zone,
                    'reference' => $park->reference,
                    'latitude' => $park->latitude,
                    'longitude' => $park->longitude,
                    'public_use_area' => $park->public_use_area,
                    'green_area' => $park->green_area ?? $park->area,
                    'total_area' => $park->total_area,
                    'has_water_point' => (bool) $park->has_water_point,
                    'water_supply_type' => $park->water_supply_type,
                    'water_point_status' => $park->water_point_status,
                    'water_point_observations' => $park->water_point_observations,
                    'conservation_status' => $park->conservation_status ?? ($park->status ?? 'regular'),
                    'photo_url' => $park->photo_url,
                    'general_observations' => $park->general_observations ?? $park->description,
                    'is_active' => (bool) $park->is_active,
                    'created_at' => $park->created_at ? $park->created_at->format('d/m/Y') : null,
                    'next_maintenance' => $nextMaintenance,
                    'schedules' => $park->schedules->map(fn ($s) => [
                        'id' => $s->id,
                        'maintenance_type' => $s->maintenance_type,
                        'frequency_days' => $s->frequency_days,
                        'last_executed_at' => $s->last_executed_at ? $s->last_executed_at->format('d/m/Y') : null,
                        'next_scheduled_date' => $s->next_scheduled_date ? $s->next_scheduled_date->format('d/m/Y') : null,
                        'is_active' => $s->is_active,
                    ]),
                    'maintenance_records' => $park->maintenanceRecords->map(fn ($m) => [
                        'id' => $m->id,
                        'maintenance_type' => $m->maintenance_type,
                        'is_extraordinary' => (bool) $m->is_extraordinary,
                        'scheduled_date' => $m->scheduled_date ? $m->scheduled_date->format('d/m/Y') : null,
                        'execution_date' => $m->execution_date ? $m->execution_date->format('d/m/Y') : null,
                        'responsible_worker' => $m->responsible_worker ?? 'Personal responsable',
                        'start_time' => $m->start_time ? substr($m->start_time, 0, 5) : null,
                        'end_time' => $m->end_time ? substr($m->end_time, 0, 5) : null,
                        'duration_minutes' => $m->duration_minutes,
                        'status' => $m->status,
                        'observations' => $m->observations,
                        'document_name' => $m->document_name,
                        'document_url' => $m->document_url,
                    ]),
                ];
            });

        // Código sugerido para el próximo parque
        $maxNum = 0;
        foreach (Park::pluck('code') as $code) {
            if (preg_match('/PRQ-(\d+)/', (string) $code, $matches)) {
                $maxNum = max($maxNum, (int) $matches[1]);
            }
        }
        $nextGeneratedCode = 'PRQ-'.str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);

        // Reglas de mantenimiento activas agrupadas por tipo de área verde (para preview en frontend)
        $rules = MaintenanceRule::where('is_active', true)->get();

        return Inertia::render('parks/index', [
            'parks' => $parks,
            'nextGeneratedCode' => $nextGeneratedCode,
            'maintenanceRules' => $rules,
            'greenAreaTypes' => GreenAreaType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    /**
     * Registro de parque y vinculación de automatizaciones
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('parks', 'code')->where('owner_id', auth()->id())],
            'name' => ['required', 'string', 'max:255'],
            'green_area_type' => [
                'required',
                'string',
                'max:100',
                Rule::exists('green_area_types', 'name')->where('is_active', true),
            ],
            'registration_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'public_use_area' => ['nullable', 'numeric', 'gte:0', 'lte:99999999.99'],
            'green_area' => ['nullable', 'numeric', 'gte:0', 'lte:99999999.99'],
            'total_area' => ['nullable', 'numeric', 'gte:0', 'lte:99999999.99'],
            'has_water_point' => ['boolean'],
            'water_supply_type' => ['nullable', 'string', 'max:100'],
            'water_point_status' => ['nullable', 'string', 'max:100'],
            'water_point_observations' => ['nullable', 'string', 'max:2000'],
            'conservation_status' => ['required', Rule::in(['bueno', 'regular', 'malo'])],
            'photo' => ['nullable', 'image', 'max:4096'],
            'photo_url' => ['nullable', 'string', 'max:1000'],
            'general_observations' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ]);

        // Procesar subida de fotografía si se adjuntó archivo
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('parks', 'public');
            $validated['photo_url'] = Storage::disk('public')->url($path);
        }

        // Compatibilidad con campos anteriores
        $validated['area'] = $validated['green_area'] ?? null;
        $validated['status'] = $validated['conservation_status'] ?? 'regular';
        $validated['description'] = $validated['general_observations'] ?? null;

        Park::create($validated);

        return redirect()
            ->route('parks.index')
            ->with('success', 'El parque se registró correctamente y se generaron sus programaciones automáticas.');
    }

    /**
     * Actualizar parque
     */
    public function update(Request $request, Park $park): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'green_area_type' => ['required', 'string', 'max:100', Rule::exists('green_area_types', 'name')->where('owner_id', auth()->id())],
            'registration_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'public_use_area' => ['nullable', 'numeric', 'gte:0', 'lte:99999999.99'],
            'green_area' => ['nullable', 'numeric', 'gte:0', 'lte:99999999.99'],
            'total_area' => ['nullable', 'numeric', 'gte:0', 'lte:99999999.99'],
            'has_water_point' => ['boolean'],
            'water_supply_type' => ['nullable', 'string', 'max:100'],
            'water_point_status' => ['nullable', 'string', 'max:100'],
            'water_point_observations' => ['nullable', 'string', 'max:2000'],
            'conservation_status' => ['required', Rule::in(['bueno', 'regular', 'malo'])],
            'photo' => ['nullable', 'image', 'max:4096'],
            'photo_url' => ['nullable', 'string', 'max:1000'],
            'general_observations' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('photo')) {
            if ($park->photo_url) {
                $base = rtrim(Storage::disk('public')->url(''), '/').'/';
                $oldPath = str_starts_with($park->photo_url, $base)
                    ? substr($park->photo_url, strlen($base))
                    : (str_starts_with($park->photo_url, '/storage/') ? substr($park->photo_url, 9) : null);

                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $path = $request->file('photo')->store('parks', 'public');
            $validated['photo_url'] = Storage::disk('public')->url($path);
        }

        $validated['area'] = $validated['green_area'] ?? $park->area;
        $validated['status'] = $validated['conservation_status'] ?? $park->status;
        $validated['description'] = $validated['general_observations'] ?? $park->description;

        $park->update($validated);

        return redirect()
            ->route('parks.index')
            ->with('success', 'Información del parque actualizada correctamente.');
    }

    /**
     * Activar o desactivar registro de parque
     */
    public function toggleStatus(Park $park): RedirectResponse
    {
        $park->is_active = ! $park->is_active;
        $park->save();

        $estado = $park->is_active ? 'activado' : 'desactivado';

        return redirect()
            ->route('parks.index')
            ->with('success', "El parque {$park->name} ha sido {$estado} correctamente.");
    }

    /**
     * Registrar mantenimiento extraordinario
     */
    public function storeExtraordinaryMaintenance(Request $request, Park $park): RedirectResponse
    {
        $validated = $request->validate([
            'maintenance_type' => ['required', 'string', 'max:100'],
            'execution_date' => ['required', 'date'],
            'responsible_worker' => ['required', 'string', 'max:255'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'observations' => ['nullable', 'string', 'max:3000'],
        ]);

        // Cálculo automático de duración si se proporcionan horas
        $duration = $validated['duration_minutes'] ?? null;
        if (! $duration && ! empty($validated['start_time']) && ! empty($validated['end_time'])) {
            try {
                $start = Carbon::createFromFormat('H:i', $validated['start_time']);
                $end = Carbon::createFromFormat('H:i', $validated['end_time']);
                if ($end->greaterThan($start)) {
                    $duration = $start->diffInMinutes($end);
                }
            } catch (\Exception $e) {
                // ignorar error de formato
            }
        }

        // Generar identificador de documento técnico
        $year = Carbon::parse($validated['execution_date'])->year;
        $num = str_pad(MaintenanceRecord::whereYear('created_at', $year)->count() + 1, 3, '0', STR_PAD_LEFT);
        $docName = "ACT-EXT-{$park->code}-{$year}-{$num}.pdf";

        $park->maintenanceRecords()->create([
            'maintenance_type' => $validated['maintenance_type'],
            'is_extraordinary' => true,
            'scheduled_date' => $validated['execution_date'],
            'execution_date' => $validated['execution_date'],
            'responsible_worker' => $validated['responsible_worker'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'duration_minutes' => $duration,
            'status' => 'ejecutado',
            'observations' => $validated['observations'] ?? null,
            'document_name' => $docName,
            'document_url' => "/storage/documents/{$docName}",
        ]);

        return redirect()
            ->route('parks.index')
            ->with('success', "Mantenimiento adicional registrado con éxito. Se generó el documento {$docName}.");
    }

    /**
     * Eliminar parque si fuera necesario
     */
    public function destroy(Park $park): RedirectResponse
    {
        $park->delete();

        return redirect()
            ->route('parks.index')
            ->with('success', 'El parque fue eliminado del sistema.');
    }
}
