<?php

use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('el boton de demo crea una cuenta temporal con datos propios y abre sesion', function () {
    auth()->forgetUser();

    $this->post('/demo')->assertRedirect('/dashboard');

    $user = User::where('email', 'like', '%@demo.local')->sole();
    $this->assertAuthenticatedAs($user);

    expect(Park::count())->toBe(8)
        ->and(MaintenanceRecord::where('status', 'finalizado')->count())->toBeGreaterThan(0)
        ->and(MaintenanceRecord::where('status', 'en_proceso')->count())->toBe(1);

    $this->get('/dashboard')->assertOk();
});

test('dos visitantes demo no comparten datos', function () {
    auth()->forgetUser();

    $this->post('/demo');
    auth()->guard('web')->logout();
    Auth::forgetUser();
    $this->post('/demo');

    expect(User::where('email', 'like', '%@demo.local')->count())->toBe(2)
        ->and(Park::count())->toBe(8)
        ->and(Park::withoutGlobalScopes()->count())->toBe(16);
});

test('demo:purge elimina solo cuentas demo antiguas con todos sus datos', function () {
    auth()->forgetUser();

    $this->post('/demo');
    $vieja = User::where('email', 'like', '%@demo.local')->sole();
    $vieja->forceFill(['created_at' => now()->subDays(2)])->save();
    auth()->guard('web')->logout();
    Auth::forgetUser();

    $this->post('/demo');
    $real = User::factory()->create();

    $this->artisan('demo:purge')->assertSuccessful();

    expect(User::where('email', 'like', '%@demo.local')->count())->toBe(1)
        ->and(User::find($real->id))->not->toBeNull()
        ->and(Park::withoutGlobalScopes()->where('owner_id', $vieja->id)->count())->toBe(0)
        ->and(Park::withoutGlobalScopes()->count())->toBe(8);
});

test('el cron de limpieza exige el secreto', function () {
    config(['app.cron_secret' => 'abc']);
    auth()->forgetUser();

    $this->get('/cron/demo-purge')->assertForbidden();
    $this->get('/cron/demo-purge', ['Authorization' => 'Bearer abc'])->assertOk();
});
