<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ParkController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->name('dashboard');
    Route::post('dashboard/metas', [\App\Http\Controllers\DashboardController::class, 'updateTargets'])
        ->name('dashboard.targets.update');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Gestión de parques
    Route::get('/parques', [ParkController::class, 'index'])
        ->name('parks.index');

    Route::post('/parques', [ParkController::class, 'store'])
        ->name('parks.store');

    Route::put('/parques/{park}', [ParkController::class, 'update'])
        ->name('parks.update');

    Route::patch('/parques/{park}/toggle-status', [ParkController::class, 'toggleStatus'])
        ->name('parks.toggle-status');

    Route::post('/parques/{park}/mantenimiento-extraordinario', [ParkController::class, 'storeExtraordinaryMaintenance'])
        ->name('parks.extraordinary-maintenance');

    Route::delete('/parques/{park}', [ParkController::class, 'destroy'])
        ->name('parks.destroy');

    // Gestión de mantenimientos
    Route::get('/mantenimientos', \App\Http\Controllers\MaintenanceIndexController::class)
        ->name('maintenances.index');

    Route::get('/mantenimientos/{maintenance}', [\App\Http\Controllers\MaintenanceIndexController::class, 'show'])
        ->whereNumber('maintenance')
        ->name('maintenances.show');

    Route::get('/mantenimientos/auditorias/{audit}/documento', [\App\Http\Controllers\MaintenanceController::class, 'auditDocument'])
        ->whereNumber('audit')
        ->name('maintenances.audit-document');

    Route::post('/mantenimientos/extraordinario', [\App\Http\Controllers\MaintenanceController::class, 'storeExtraordinary'])
        ->name('maintenances.store-extraordinary');

    Route::post('/mantenimientos/{maintenance}/iniciar', [\App\Http\Controllers\MaintenanceController::class, 'iniciar'])
        ->name('maintenances.iniciar');

    Route::post('/mantenimientos/{maintenance}/finalizar', [\App\Http\Controllers\MaintenanceController::class, 'finalizar'])
        ->name('maintenances.finalizar');

    Route::post('/mantenimientos/{maintenance}/atender', [\App\Http\Controllers\MaintenanceController::class, 'atender'])
        ->name('maintenances.atender');

    Route::patch('/mantenimientos/{maintenance}/cancelar', [\App\Http\Controllers\MaintenanceController::class, 'cancelar'])
        ->name('maintenances.cancelar');

    Route::get('/mantenimientos/{maintenance}/documento', [\App\Http\Controllers\MaintenanceController::class, 'imprimirParte'])
        ->name('maintenances.documento');

    // Catálogo de trabajadores
    Route::get('/trabajadores', [\App\Http\Controllers\WorkerController::class, 'index'])
        ->name('workers.index');

    Route::post('/trabajadores', [\App\Http\Controllers\WorkerController::class, 'store'])
        ->name('workers.store');

    Route::put('/trabajadores/{worker}', [\App\Http\Controllers\WorkerController::class, 'update'])
        ->name('workers.update');

    Route::patch('/trabajadores/{worker}/toggle-status', [\App\Http\Controllers\WorkerController::class, 'toggleStatus'])
        ->name('workers.toggle-status');

    Route::get('/trabajadores/{worker}/mantenimientos', [\App\Http\Controllers\WorkerController::class, 'mantenimientos'])
        ->name('workers.mantenimientos');

    // Reportes y exportaciones
    Route::get('/reportes', [\App\Http\Controllers\ReportController::class, 'index'])
        ->name('reports.index');

    Route::get('/reportes/exportar/csv', [\App\Http\Controllers\ReportController::class, 'csv'])
        ->name('reports.csv');

    Route::get('/reportes/exportar/pdf', [\App\Http\Controllers\ReportController::class, 'pdf'])
        ->name('reports.pdf');

    Route::get('/reportes/imprimir', [\App\Http\Controllers\ReportController::class, 'print'])
        ->name('reports.print');

    // Configuración general
    Route::redirect('/plantas', '/configuracion')
        ->name('legacy.plants');

    Route::get('/configuracion', [\App\Http\Controllers\ConfigurationController::class, 'index'])
        ->name('configuration.index');

    Route::post('/configuracion/tipos-area', [\App\Http\Controllers\ConfigurationController::class, 'storeGreenAreaType'])
        ->name('configuration.green-area-types.store');
    Route::put('/configuracion/tipos-area/{greenAreaType}', [\App\Http\Controllers\ConfigurationController::class, 'updateGreenAreaType'])
        ->name('configuration.green-area-types.update');
    Route::patch('/configuracion/tipos-area/{greenAreaType}/estado', [\App\Http\Controllers\ConfigurationController::class, 'toggleGreenAreaType'])
        ->name('configuration.green-area-types.toggle');

    Route::post('/configuracion/tipos-mantenimiento', [\App\Http\Controllers\ConfigurationController::class, 'storeMaintenanceType'])
        ->name('configuration.maintenance-types.store');
    Route::put('/configuracion/tipos-mantenimiento/{maintenanceType}', [\App\Http\Controllers\ConfigurationController::class, 'updateMaintenanceType'])
        ->name('configuration.maintenance-types.update');
    Route::patch('/configuracion/tipos-mantenimiento/{maintenanceType}/estado', [\App\Http\Controllers\ConfigurationController::class, 'toggleMaintenanceType'])
        ->name('configuration.maintenance-types.toggle');

    Route::post('/configuracion/reglas', [\App\Http\Controllers\ConfigurationController::class, 'storeRule'])
        ->name('configuration.rules.store');
    Route::put('/configuracion/reglas/{maintenanceRule}', [\App\Http\Controllers\ConfigurationController::class, 'updateRule'])
        ->name('configuration.rules.update');
    Route::patch('/configuracion/reglas/{maintenanceRule}/estado', [\App\Http\Controllers\ConfigurationController::class, 'toggleRule'])
        ->name('configuration.rules.toggle');

    Route::delete('/configuracion/datos-demo', [\App\Http\Controllers\ConfigurationController::class, 'clearDemoData'])
        ->name('configuration.demo.clear');

    Route::post('/configuracion/datos-institucionales', [\App\Http\Controllers\ConfigurationController::class, 'updateInstitution'])
        ->name('configuration.institution.update');

    // Asistente conversacional (solo lectura, navegación y reportes)
    Route::post('/asistente/consultar', [\App\Http\Controllers\AsistenteController::class, 'consultar'])
        ->middleware('throttle:asistente')
        ->name('asistente.consultar');

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
