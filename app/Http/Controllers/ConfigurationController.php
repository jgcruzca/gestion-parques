<?php

namespace App\Http\Controllers;

use App\Models\GreenAreaType;
use App\Models\InstitutionalSetting;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRule;
use App\Models\MaintenanceType;
use App\Models\Park;
use App\Models\ParkSchedule;
use App\Services\Accounts\AccountBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function index(): Response
    {
        $institution = InstitutionalSetting::query()->firstOrCreate([], [
            'organization_name' => 'Mi organización',
            'unit_name' => 'Área de Parques y Mantenimiento',
            'management_year' => (string) now()->year,
            'document_footer' => 'Documento generado por Sistema Verde.',
        ]);

        return Inertia::render('configuration/index', [
            'greenAreaTypes' => GreenAreaType::query()->orderBy('name')->get(),
            'maintenanceTypes' => MaintenanceType::query()->orderBy('name')->get(),
            'hasDemoData' => app(AccountBootstrap::class)->hasDemoData(),
            'maintenanceRules' => MaintenanceRule::query()->orderBy('green_area_type')->orderBy('maintenance_type')->get(),
            'institution' => [
                'organization_name' => $institution->organization_name,
                'unit_name' => $institution->unit_name,
                'logo_url' => $institution->logo_url,
                'address' => $institution->address,
                'manager_name' => $institution->manager_name,
                'management_year' => $institution->management_year,
                'document_footer' => $institution->document_footer,
            ],
        ]);
    }

    public function storeGreenAreaType(Request $request): RedirectResponse
    {
        GreenAreaType::create($this->validateCatalog($request, 'green_area_types'));

        return back()->with('success', 'Tipo de área verde registrado correctamente.');
    }

    public function updateGreenAreaType(Request $request, GreenAreaType $greenAreaType): RedirectResponse
    {
        $validated = $this->validateCatalog($request, 'green_area_types', $greenAreaType->id);
        $previousName = $greenAreaType->name;

        DB::transaction(function () use ($greenAreaType, $validated, $previousName) {
            $greenAreaType->update($validated);

            if ($previousName !== $validated['name']) {
                Park::query()->where('green_area_type', $previousName)->update(['green_area_type' => $validated['name']]);
                MaintenanceRule::query()->where('green_area_type', $previousName)->update(['green_area_type' => $validated['name']]);
            }
        });

        return back()->with('success', 'Tipo de área verde actualizado correctamente.');
    }

    public function toggleGreenAreaType(GreenAreaType $greenAreaType): RedirectResponse
    {
        $greenAreaType->update(['is_active' => !$greenAreaType->is_active]);

        return back()->with('success', 'Estado del tipo de área verde actualizado.');
    }

    public function storeMaintenanceType(Request $request): RedirectResponse
    {
        MaintenanceType::create($this->validateCatalog($request, 'maintenance_types'));

        return back()->with('success', 'Tipo de mantenimiento registrado correctamente.');
    }

    public function updateMaintenanceType(Request $request, MaintenanceType $maintenanceType): RedirectResponse
    {
        $validated = $this->validateCatalog($request, 'maintenance_types', $maintenanceType->id);
        $previousName = $maintenanceType->name;

        DB::transaction(function () use ($maintenanceType, $validated, $previousName) {
            $maintenanceType->update($validated);

            if ($previousName !== $validated['name']) {
                MaintenanceRule::query()->where('maintenance_type', $previousName)->update(['maintenance_type' => $validated['name']]);
                ParkSchedule::query()->where('maintenance_type', $previousName)->update(['maintenance_type' => $validated['name']]);
                MaintenanceRecord::query()->where('maintenance_type', $previousName)->update(['maintenance_type' => $validated['name']]);
            }
        });

        return back()->with('success', 'Tipo de mantenimiento actualizado correctamente.');
    }

    public function toggleMaintenanceType(MaintenanceType $maintenanceType): RedirectResponse
    {
        $maintenanceType->update(['is_active' => !$maintenanceType->is_active]);

        return back()->with('success', 'Estado del tipo de mantenimiento actualizado.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        MaintenanceRule::create($this->validateRule($request));

        return back()->with('success', 'Regla de programación registrada correctamente.');
    }

    public function updateRule(Request $request, MaintenanceRule $maintenanceRule): RedirectResponse
    {
        $maintenanceRule->update($this->validateRule($request, $maintenanceRule));

        return back()->with('success', 'Regla de programación actualizada correctamente.');
    }

    public function toggleRule(MaintenanceRule $maintenanceRule): RedirectResponse
    {
        $maintenanceRule->update(['is_active' => !$maintenanceRule->is_active]);

        return back()->with('success', 'Estado de la regla actualizado.');
    }

    public function clearDemoData(): RedirectResponse
    {
        $removed = app(AccountBootstrap::class)->clearDemoData();

        return back()->with('success', $removed > 0
            ? 'Los datos de demostración fueron eliminados. Tu espacio quedó listo para tus propios datos.'
            : 'No había datos de demostración para eliminar.');
    }

    public function updateInstitution(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'unit_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'management_year' => ['required', 'string', 'max:20'],
            'document_footer' => ['nullable', 'string', 'max:1000'],
        ]);

        $institution = InstitutionalSetting::query()->firstOrCreate([], [
            'organization_name' => $validated['organization_name'],
        ]);

        if ($request->hasFile('logo')) {
            if ($institution->logo_path) {
                Storage::disk('public')->delete($institution->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('institutional', 'public');
        }

        unset($validated['logo']);
        $institution->update($validated);

        return back()->with('success', 'Datos institucionales actualizados correctamente.');
    }

    private function validateCatalog(Request $request, string $table, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->where('owner_id', auth()->id())->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateRule(Request $request, ?MaintenanceRule $maintenanceRule = null): array
    {
        return $request->validate([
            'green_area_type' => ['required', 'string', 'max:255', Rule::exists('green_area_types', 'name')->where('owner_id', auth()->id())],
            'maintenance_type' => [
                'required',
                'string',
                'max:255',
                Rule::unique('maintenance_rules', 'maintenance_type')
                    ->where(fn ($query) => $query->where('owner_id', auth()->id())->where('green_area_type', $request->string('green_area_type')->toString()))
                    ->ignore($maintenanceRule?->id),
            ],
            'frequency_days' => ['required', 'integer', 'min:1'],
            'advance_days' => ['required', 'integer', 'min:0', 'lte:frequency_days'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
