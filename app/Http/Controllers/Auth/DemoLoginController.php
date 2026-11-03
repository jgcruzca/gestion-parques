<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\AccountBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Acceso de un clic para demostraciones: crea una cuenta temporal con datos de
 * ejemplo (espacio privado propio) y abre la sesión. Se limpian con `demo:purge`.
 */
class DemoLoginController extends Controller
{
    public const EMAIL_DOMAIN = 'demo.local';

    public function __invoke(): RedirectResponse
    {
        $user = User::create([
            'name' => 'Visitante demo',
            'email' => 'demo-'.Str::lower(Str::random(12)).'@'.self::EMAIL_DOMAIN,
            'password' => Hash::make(Str::random(40)),
        ]);

        Auth::login($user);

        app(AccountBootstrap::class)->run($user);

        return to_route('dashboard');
    }
}
