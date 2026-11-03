<?php

use App\Models\GreenAreaType;
use App\Models\MaintenanceRecord;
use App\Models\Park;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

function registrar(string $email): User
{
    test()->post('/register', [
        'name' => 'Persona '.$email,
        'email' => $email,
        'password' => 'password-segura-123',
        'password_confirmation' => 'password-segura-123',
    ])->assertRedirect('/dashboard');

    $user = User::where('email', $email)->firstOrFail();
    Auth::forgetUser();
    auth()->guard('web')->logout();

    return $user;
}

test('una cuenta nueva recibe catalogos, reglas y datos de demostracion propios', function () {
    auth()->forgetUser();
    $user = registrar('a@example.com');

    $this->actingAs($user);

    expect(GreenAreaType::count())->toBe(8)
        ->and(Park::count())->toBe(8)
        ->and(Park::where('is_demo', false)->count())->toBe(0)
        ->and(Worker::count())->toBe(5)
        ->and(MaintenanceRecord::count())->toBeGreaterThan(0)
        ->and(Park::pluck('owner_id')->unique()->all())->toBe([$user->id]);
});

test('cada cuenta solo ve y modifica sus propios datos', function () {
    auth()->forgetUser();
    $a = registrar('a@example.com');
    $b = registrar('b@example.com');

    $parqueDeA = Park::withoutGlobalScopes()->where('owner_id', $a->id)->firstOrFail();

    $this->actingAs($b)->get('/parques')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('parks/index'));

    expect(Park::count())->toBe(8)
        ->and(Park::withoutGlobalScopes()->count())->toBe(16);

    // Un parque ajeno es inaccesible aunque se conozca su id.
    $this->actingAs($b)
        ->patch("/parques/{$parqueDeA->id}/toggle-status")
        ->assertNotFound();

    $this->actingAs($b)
        ->delete("/parques/{$parqueDeA->id}")
        ->assertNotFound();

    expect(Park::withoutGlobalScopes()->find($parqueDeA->id))->not->toBeNull();
});

test('el mismo codigo de parque puede existir en cuentas distintas', function () {
    auth()->forgetUser();
    $a = registrar('a@example.com');
    $b = registrar('b@example.com');

    $codigos = fn (User $user) => Park::withoutGlobalScopes()->where('owner_id', $user->id)->orderBy('id')->pluck('code')->all();

    expect($codigos($a))->toBe($codigos($b))
        ->and($codigos($a))->toHaveCount(8);
});

test('eliminar los datos de demostracion solo afecta a la cuenta que lo pide', function () {
    auth()->forgetUser();
    $a = registrar('a@example.com');
    $b = registrar('b@example.com');

    $this->actingAs($a)
        ->delete('/configuracion/datos-demo')
        ->assertRedirect();

    expect(Park::withoutGlobalScopes()->where('owner_id', $a->id)->count())->toBe(0)
        ->and(Worker::withoutGlobalScopes()->where('owner_id', $a->id)->count())->toBe(0)
        ->and(MaintenanceRecord::withoutGlobalScopes()->where('owner_id', $a->id)->count())->toBe(0)
        ->and(Park::withoutGlobalScopes()->where('owner_id', $b->id)->count())->toBe(8)
        ->and(GreenAreaType::withoutGlobalScopes()->where('owner_id', $a->id)->count())->toBe(8);

    $this->actingAs($a)->get('/configuracion')
        ->assertInertia(fn (Assert $page) => $page->where('hasDemoData', false));
});

test('los datos propios no se borran junto con los de demostracion', function () {
    auth()->forgetUser();
    $a = registrar('a@example.com');

    $this->actingAs($a);
    Park::create(['name' => 'Mi parque real', 'green_area_type' => 'Parque', 'is_active' => true]);

    $this->delete('/configuracion/datos-demo')->assertRedirect();

    expect(Park::pluck('name')->all())->toBe(['Mi parque real']);
});
