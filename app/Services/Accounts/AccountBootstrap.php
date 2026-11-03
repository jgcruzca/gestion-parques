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
        ])->map(fn (array $data) => Worker::create($data + ['is_active' => true, 'is_demo' => true]));

        $parks = [
            ['name' => 'Parque Demo Central', 'green_area_type' => 'Parque', 'zone' => 'Zona Norte', 'address' => 'Av. Ejemplo 100', 'latitude' => -12.0464, 'longitude' => -77.0428, 'green_area' => 5200, 'total_area' => 7800],
            ['name' => 'Plaza Demo de la Amistad', 'green_area_type' => 'Plaza', 'zone' => 'Zona Centro', 'address' => 'Jr. Ejemplo 45', 'latitude' => -12.0500, 'longitude' => -77.0380, 'green_area' => 900, 'total_area' => 1600],
            ['name' => 'Óvalo Demo Los Jardines', 'green_area_type' => 'Óvalo', 'zone' => 'Zona Sur', 'address' => 'Cruce Av. Ejemplo / Calle 7', 'latitude' => -12.0560, 'longitude' => -77.0450, 'green_area' => 420, 'total_area' => 600],
        ];

        $created = collect($parks)->map(fn (array $data) => Park::create($data + [
            'conservation_status' => 'bueno',
            'has_water_point' => true,
            'is_active' => true,
            'is_demo' => true,
            'registration_date' => now()->subDays(30)->toDateString(),
        ]));

        // Un mantenimiento ya ejecutado para que los indicadores muestren actividad.
        $record = MaintenanceRecord::query()->where('park_id', $created->first()->id)->orderBy('scheduled_date')->first();
        $worker = $workers->first();

        $record?->update([
            'status' => 'finalizado',
            'execution_date' => now()->subDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'duration_minutes' => 120,
            'worker_id' => $worker->id,
            'responsible_worker' => $worker->full_name,
            'work_description' => 'Mantenimiento de ejemplo generado con los datos de demostración.',
            'result' => 'completado',
        ]);
    }
}
