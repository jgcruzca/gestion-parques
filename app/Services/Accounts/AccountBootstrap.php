<?php

namespace App\Services\Accounts;

use App\Models\GreenAreaType;
use App\Models\InstitutionalSetting;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRule;
use App\Models\MaintenanceType;
use App\Models\Park;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Prepara el espacio privado de una cuenta nueva: catálogos editables,
 * datos de la organización y registros de demostración ficticios (borrables).
 */
class AccountBootstrap
{
    public const AREA_TYPES = ['Parque', 'Óvalo', 'Berma', 'Berma Central', 'Jardín', 'Alameda', 'Plaza', 'Otro'];

    public const MAINTENANCE_TYPES = [
        'Riego', 'Limpieza', 'Poda', 'Corte de césped', 'Fumigación', 'Fertilización',
        'Deshierbe', 'Reposición de plantas', 'Reparación del punto de agua',
        'Mantenimiento de infraestructura', 'Retiro de un árbol caído', 'Otro',
    ];

    /** @var array<string, array<string, int>> tipo de área => [mantenimiento => frecuencia en días] */
    public const RULES = [
        'Parque' => ['Riego' => 4, 'Limpieza' => 5, 'Poda' => 45, 'Fertilización' => 90],
        'Óvalo' => ['Riego' => 3, 'Limpieza' => 7, 'Poda' => 30],
        'Alameda' => ['Riego' => 3, 'Limpieza' => 4, 'Poda' => 30],
        'Berma Central' => ['Riego' => 5, 'Limpieza' => 7, 'Poda' => 60],
        'Jardín' => ['Riego' => 2, 'Limpieza' => 3, 'Poda' => 20],
        'Plaza' => ['Riego' => 3, 'Limpieza' => 2, 'Poda' => 30],
    ];

    public function run(User $user, bool $withDemoData = true, bool $withRules = true): void
    {
        // Las operaciones del servicio actúan siempre en nombre de la cuenta indicada.
        $previous = Auth::hasUser() ? Auth::user() : null;
        Auth::setUser($user);

        try {
            DB::transaction(function () use ($withDemoData, $withRules): void {
                $this->seedCatalogs($withRules);
                $this->seedOrganization();

                if ($withDemoData) {
                    $this->seedDemoData();
                }
            });
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }
    }

    /** Elimina únicamente los registros de demostración de la cuenta autenticada. */
    public function clearDemoData(): int
    {
        return DB::transaction(function (): int {
            $parks = Park::query()->where('is_demo', true)->get();
            $workers = Worker::query()->where('is_demo', true)->get();

            // Los mantenimientos y programaciones se eliminan en cascada con el parque.
            $parks->each->delete();
            $workers->each->delete();

            return $parks->count() + $workers->count();
        });
    }

    public function hasDemoData(): bool
    {
        return Park::query()->where('is_demo', true)->exists()
            || Worker::query()->where('is_demo', true)->exists();
    }

    private function seedCatalogs(bool $withRules = true): void
    {
        foreach (self::AREA_TYPES as $name) {
            GreenAreaType::firstOrCreate(['name' => $name], ['description' => "Tipo de área verde: {$name}.", 'is_active' => true]);
        }

        foreach (self::MAINTENANCE_TYPES as $name) {
            MaintenanceType::firstOrCreate(['name' => $name], ['description' => "Tipo de mantenimiento: {$name}.", 'is_active' => true]);
        }

        foreach ($withRules ? self::RULES : [] as $area => $rules) {
            foreach ($rules as $maintenance => $days) {
                MaintenanceRule::firstOrCreate(
                    ['green_area_type' => $area, 'maintenance_type' => $maintenance],
                    ['frequency_days' => $days, 'advance_days' => 1, 'is_active' => true],
                );
            }
        }
    }

    private function seedOrganization(): void
    {
        InstitutionalSetting::firstOrCreate([], [
            'organization_name' => 'Mi organización',
            'unit_name' => 'Área de Parques y Mantenimiento',
            'management_year' => (string) now()->year,
            'document_footer' => 'Documento generado por Sistema Verde.',
        ]);
    }

    private function seedDemoData(): void
    {
        $workers = collect([
            ['first_name' => 'Laura', 'last_name' => 'Demo Rojas', 'position' => 'Jardinera', 'crew' => 'Cuadrilla A'],
            ['first_name' => 'Marco', 'last_name' => 'Demo Vega', 'position' => 'Operario de riego', 'crew' => 'Cuadrilla A'],
            ['first_name' => 'Elena', 'last_name' => 'Demo Campos', 'position' => 'Supervisora', 'crew' => 'Cuadrilla B'],
            ['first_name' => 'Diego', 'last_name' => 'Demo Salas', 'position' => 'Podador', 'crew' => 'Cuadrilla B'],
            ['first_name' => 'Rosa', 'last_name' => 'Demo Ibáñez', 'position' => 'Jardinera', 'crew' => 'Cuadrilla C'],
        ])->map(fn (array $data) => Worker::create($data + ['is_active' => true, 'is_demo' => true]));

        // [nombre, tipo, zona, dirección, lat, lng, área verde, área total, conservación, días desde el registro]
        $parks = [
            ['Parque Demo Central', 'Parque', 'Zona Norte', 'Av. Ejemplo 100', -12.0464, -77.0428, 5200, 7800, 'bueno', 40],
            ['Plaza Demo de la Amistad', 'Plaza', 'Zona Centro', 'Jr. Ejemplo 45', -12.0500, -77.0380, 900, 1600, 'bueno', 25],
            ['Óvalo Demo Los Jardines', 'Óvalo', 'Zona Sur', 'Cruce Av. Ejemplo / Calle 7', -12.0560, -77.0450, 420, 600, 'regular', 18],
            ['Alameda Demo del Río', 'Alameda', 'Zona Este', 'Paseo Ribereño s/n', -12.0420, -77.0300, 2300, 3100, 'bueno', 33],
            ['Jardín Demo Botánico', 'Jardín', 'Zona Norte', 'Calle Flores 12', -12.0380, -77.0460, 700, 900, 'bueno', 12],
            ['Parque Demo Los Pinos', 'Parque', 'Zona Oeste', 'Av. Los Pinos 800', -12.0510, -77.0560, 4100, 6000, 'regular', 50],
            ['Berma Demo Panamericana', 'Berma Central', 'Zona Sur', 'Av. Principal km 3', -12.0620, -77.0390, 1500, 1800, 'malo', 8],
            ['Plaza Demo San Martín', 'Plaza', 'Zona Centro', 'Plaza de Armas s/n', -12.0450, -77.0310, 1100, 2000, 'regular', 22],
        ];

        $created = collect($parks)->map(fn (array $p) => Park::create([
            'name' => $p[0], 'green_area_type' => $p[1], 'zone' => $p[2], 'address' => $p[3],
            'latitude' => $p[4], 'longitude' => $p[5], 'green_area' => $p[6], 'total_area' => $p[7],
            'conservation_status' => $p[8],
            'has_water_point' => $p[8] !== 'malo',
            'water_point_status' => $p[8] === 'malo' ? null : 'operativo',
            'is_active' => true,
            'is_demo' => true,
            'registration_date' => now()->subDays($p[9])->toDateString(),
        ]));

        // Reparte el historial: algunos mantenimientos ejecutados (a tiempo y con retraso),
        // uno en proceso y el resto queda pendiente o vencido según su fecha programada.
        $records = MaintenanceRecord::query()->orderBy('scheduled_date')->orderBy('id')->get();
        // Los más recientes ya vencidos se marcan como ejecutados (así hay actividad este mes);
        // los más antiguos quedan como vencidos para mostrar alertas.
        $finalizados = $records
            ->filter(fn ($r) => $r->scheduled_date && $r->scheduled_date->isPast())
            ->sortByDesc('scheduled_date')
            ->values();

        foreach ($finalizados->take(12) as $i => $record) {
            $worker = $workers[$i % $workers->count()];
            $delay = $i % 3 === 0 ? 2 : 0;
            $execution = $record->scheduled_date->copy()->addDays($delay);

            if ($execution->isFuture()) {
                $execution = now()->subDay();
            }

            $record->update([
                'status' => 'finalizado',
                'execution_date' => $execution->toDateString(),
                'start_time' => '08:00',
                'end_time' => '10:30',
                'duration_minutes' => 150,
                'worker_id' => $worker->id,
                'responsible_worker' => $worker->full_name,
                'work_description' => 'Mantenimiento de ejemplo generado con los datos de demostración.',
                'result' => 'completado',
            ]);
        }

        $enProceso = $finalizados->skip(12)->last() ?? $records->firstWhere('status', 'pendiente');
        $enProceso?->update([
            'status' => 'en_proceso',
            'execution_date' => now()->toDateString(),
            'start_time' => '09:00',
            'worker_id' => $workers[2]->id,
            'responsible_worker' => $workers[2]->full_name,
        ]);
    }
}
